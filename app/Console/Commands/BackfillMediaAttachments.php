<?php

namespace App\Console\Commands;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaRecordScope;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class BackfillMediaAttachments extends Command
{
    protected $signature = 'media:backfill-attachments {--apply : Persist unambiguous attachment rows}';

    protected $description = 'Report or idempotently backfill media attachments from legacy owner paths.';

    public function handle(
        CuratorImageUploadPolicy $policy,
        MediaRecordScope $scope,
    ): int {
        $counts = [
            'eligible' => 0,
            'created' => 0,
            'existing' => 0,
            'missing' => 0,
            'missing_reference_key' => 0,
            'duplicate' => 0,
            'disallowed' => 0,
            'invalid_path' => 0,
            'conflict' => 0,
        ];

        $this->processOwners(ContentGroup::query(), 'cover_path', 'content_group', MediaAttachmentRole::Cover, $policy, $scope, $counts);
        $this->processOwners(ContentItem::query(), 'image_path', 'content_item', MediaAttachmentRole::PrimaryImage, $policy, $scope, $counts);

        $mode = $this->option('apply') ? 'Apply' : 'Dry run';
        $this->components->info($mode.': '.collect($counts)->map(fn (int $count, string $key): string => "{$key}={$count}")->implode(', '));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function processOwners(
        Builder $query,
        string $pathColumn,
        string $alias,
        MediaAttachmentRole $role,
        CuratorImageUploadPolicy $policy,
        MediaRecordScope $scope,
        array &$counts,
    ): void {
        $query
            ->whereNotNull($pathColumn)
            ->where($pathColumn, '!=', '')
            ->select(['id', $pathColumn])
            ->chunkById(100, function (Collection $owners) use (
                $pathColumn,
                $alias,
                $role,
                $policy,
                $scope,
                &$counts,
            ): void {
                $paths = [];

                foreach ($owners as $owner) {
                    try {
                        $path = $policy->normalizePath((string) $owner->getAttribute($pathColumn));

                        if ($policy->purposeForPath($path) !== $role->purpose()) {
                            throw new InvalidArgumentException('The path purpose is incompatible.');
                        }

                        $paths[(int) $owner->getKey()] = $path;
                    } catch (InvalidArgumentException) {
                        $counts['invalid_path']++;
                    }
                }

                $mediaByPath = Media::query()
                    ->whereIn('path', array_values(array_unique($paths)))
                    ->get()
                    ->groupBy(fn (Media $media): string => (string) $media->path);
                $existingByOwner = MediaAttachment::query()
                    ->where('attachable_type', $alias)
                    ->where('role', $role->value)
                    ->whereIn('attachable_id', array_keys($paths))
                    ->get()
                    ->keyBy(fn (MediaAttachment $attachment): int => (int) $attachment->attachable_id);

                foreach ($owners as $owner) {
                    $ownerId = (int) $owner->getKey();
                    $path = $paths[$ownerId] ?? null;

                    if (! is_string($path)) {
                        continue;
                    }

                    $matches = $mediaByPath->get($path, collect());

                    if ($matches->isEmpty()) {
                        $counts['missing']++;

                        continue;
                    }

                    if ($matches->count() !== 1) {
                        $counts['duplicate']++;

                        continue;
                    }

                    /** @var Media $media */
                    $media = $matches->first();

                    if (blank($media->reference_key)) {
                        $counts[$scope->allowsForBackfill($media, $role->purpose())
                            ? 'missing_reference_key'
                            : 'disallowed']++;

                        continue;
                    }

                    if (! $scope->allows($media, $role->purpose())) {
                        $counts['disallowed']++;

                        continue;
                    }

                    $existing = $existingByOwner->get($ownerId);

                    if ($existing instanceof MediaAttachment) {
                        $counts[(int) $existing->media_id === (int) $media->getKey() ? 'existing' : 'conflict']++;

                        continue;
                    }

                    $counts['eligible']++;

                    if (! $this->option('apply')) {
                        continue;
                    }

                    $attachment = MediaAttachment::query()->firstOrCreate(
                        [
                            'attachable_type' => $alias,
                            'attachable_id' => $ownerId,
                            'role' => $role->value,
                        ],
                        [
                            'media_id' => $media->getKey(),
                            'position' => 0,
                        ],
                    );

                    if (! $attachment->wasRecentlyCreated && (int) $attachment->media_id !== (int) $media->getKey()) {
                        $counts['conflict']++;

                        continue;
                    }

                    $counts[$attachment->wasRecentlyCreated ? 'created' : 'existing']++;
                }
            });
    }
}
