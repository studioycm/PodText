<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Settings\PublicContentSettings;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelSettings\Models\SettingsProperty;

class LegacyMediaReferenceSwitcher
{
    public function __construct(
        private readonly LegacyMediaRegistrationPlanner $planner,
        private readonly SettingsMediaIdentityProjector $settingsProjector,
    ) {}

    /**
     * The caller must own the public-content Settings group mutex whenever
     * the reviewed plan contains Settings snapshots.
     */
    public function switchWithinCoordinatedTransaction(
        LegacyMediaRegistrationPlan $plan,
        Media $media,
        bool $inPlaceTransition = false,
        array $reviewedEntry = [],
    ): void {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Legacy media references may only be switched inside a database transaction.');
        }

        $groups = ContentGroup::query()
            ->where('cover_path', $plan->sourcePath)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'cover_path']);
        $items = ContentItem::query()
            ->where('image_path', $plan->sourcePath)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'image_path']);
        $groupIds = collect($groups->modelKeys())->map(fn (mixed $id): int => (int) $id)->all();
        $itemIds = collect($items->modelKeys())->map(fn (mixed $id): int => (int) $id)->all();

        if ($groupIds !== $plan->contentGroupIds || $itemIds !== $plan->contentItemIds) {
            throw new RuntimeException('The legacy media owner references changed after review.');
        }

        $attachments = ($groupIds !== [] || $itemIds !== []) ? MediaAttachment::query()
            ->where(function ($query) use ($groupIds, $itemIds): void {
                if ($groupIds !== []) {
                    $query->orWhere(function ($query) use ($groupIds): void {
                        $query->where('attachable_type', 'content_group')->whereIn('attachable_id', $groupIds);
                    });
                }

                if ($itemIds !== []) {
                    $query->orWhere(function ($query) use ($itemIds): void {
                        $query->where('attachable_type', 'content_item')->whereIn('attachable_id', $itemIds);
                    });
                }
            })
            ->lockForUpdate()
            ->get()
            : collect();

        foreach ($attachments as $attachment) {
            $expectedRole = $attachment->attachable_type === 'content_group' ? MediaAttachmentRole::Cover->value : MediaAttachmentRole::PrimaryImage->value;
            if ((int) $attachment->media_id !== (int) $media->getKey() || $attachment->role->value !== $expectedRole || (int) $attachment->position !== 0) {
                throw new RuntimeException('A legacy media owner has a conflicting attachment after review.');
            }
        }
        if ($reviewedEntry !== []) {
            $expected = collect(data_get($reviewedEntry, 'review_state.attachments', []))->map(fn (array $row): array => [
                'media_id' => (int) $row['media_id'], 'attachable_type' => $row['attachable_type'], 'attachable_id' => (int) $row['attachable_id'], 'role' => $row['role'], 'position' => (int) $row['position'],
            ])->sort()->values()->all();
            $actual = MediaAttachment::query()->where('media_id', $media->getKey())->lockForUpdate()->get(['media_id', 'attachable_type', 'attachable_id', 'role', 'position'])->map(fn (MediaAttachment $row): array => [
                'media_id' => (int) $row->media_id, 'attachable_type' => $row->attachable_type, 'attachable_id' => (int) $row->attachable_id, 'role' => $row->role->value, 'position' => (int) $row->position,
            ])->sort()->values()->all();
            if ($expected !== $actual) {
                throw new RuntimeException('The legacy media attachment snapshot changed after review.');
            }
        }

        $properties = SettingsProperty::query()
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
            ->orderBy('name')
            ->lockForUpdate()
            ->get();
        // For a new registration the source still has no Curator row and may
        // be re-planned here. For an in-place transition the row has already
        // moved to its verified destination before owner references are
        // switched, so re-planning the old path would incorrectly demand a
        // row that no longer owns it. The coordinator re-plans while the raw
        // row is still locked immediately before that move.
        foreach ($properties as $property) {
            $snapshot = $plan->settingsSnapshots[(string) $property->name] ?? null;
            if (is_array($snapshot) && ! hash_equals($snapshot['sha256'], hash('sha256', (string) $property->payload))) {
                throw new RuntimeException('The legacy settings payload changed after review.');
            }
        }

        if (! $inPlaceTransition) {
            $fresh = $this->planner->plan($plan->sourcePath);

            if (! hash_equals($plan->fingerprint(), $fresh->fingerprint())) {
                throw new RuntimeException('The legacy media registration plan changed after review.');
            }
        }

        foreach ($groups as $group) {
            MediaAttachment::query()->firstOrCreate([
                'media_id' => $media->getKey(), 'attachable_type' => 'content_group',
                'attachable_id' => $group->getKey(), 'role' => MediaAttachmentRole::Cover,
            ], ['position' => 0]);
            $group->forceFill(['cover_path' => $media->path])->save();
        }

        foreach ($items as $item) {
            MediaAttachment::query()->firstOrCreate([
                'media_id' => $media->getKey(), 'attachable_type' => 'content_item',
                'attachable_id' => $item->getKey(), 'role' => MediaAttachmentRole::PrimaryImage,
            ], ['position' => 0]);
            $item->forceFill(['image_path' => $media->path])->save();
        }

        if ($plan->settingsSnapshots !== []) {
            $payload = $properties->mapWithKeys(function (SettingsProperty $property): array {
                $decoded = json_decode((string) $property->payload, true, flags: JSON_THROW_ON_ERROR);

                return [(string) $property->name => is_array($decoded) ? $decoded : []];
            })->all();
            $projection = $this->settingsProjector->registerLegacyPath(
                $payload,
                $plan->sourcePath,
                $plan->purpose,
                $media,
            );

            if (collect($projection['locations'])->sort()->values()->all() !== $plan->settingsLocations()) {
                throw new RuntimeException('The legacy settings media references changed after review.');
            }

            foreach (array_keys($plan->settingsSnapshots) as $name) {
                $property = $properties->firstWhere('name', $name)
                    ?? throw new RuntimeException("The public settings property [{$name}] disappeared after review.");

                if ($property->locked) {
                    throw new RuntimeException("The public settings property [{$name}] became locked after review.");
                }

                $property->forceFill([
                    'payload' => json_encode($projection['payload'][$name], JSON_THROW_ON_ERROR),
                ])->save();
            }
        }
    }
}
