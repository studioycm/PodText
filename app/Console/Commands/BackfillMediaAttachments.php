<?php

namespace App\Console\Commands;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Support\Media\LegacyMediaTransitionPlanner;
use App\Support\Media\MediaRecordScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BackfillMediaAttachments extends Command
{
    protected $signature = 'media:backfill-attachments {--apply : Persist unambiguous attachment rows} {--digest= : Exact reviewed transition manifest digest required for apply}';

    protected $description = 'Report or atomically backfill media attachments from reviewed legacy owner paths.';

    public function handle(LegacyMediaTransitionPlanner $planner, MediaRecordScope $scope): int
    {
        $manifest = $planner->manifest();
        $planner->assertClosed($manifest);

        if ($this->option('apply') && (! is_string($this->option('digest')) || ! hash_equals((string) $this->option('digest'), $manifest->digest()))) {
            $this->components->error('Apply requires the exact reviewed transition manifest --digest.');

            return self::FAILURE;
        }

        $counts = array_fill_keys(['eligible', 'created', 'existing', 'missing', 'missing_reference_key', 'duplicate', 'disallowed', 'invalid_path', 'conflict'], 0);
        $counts['transition_pending'] = collect($manifest->entries)
            ->whereIn('disposition', ['key_only', 'normalize_existing', 'sanitize_svg', 'import_exact_path'])
            ->count();
        $counts['detach_to_default'] = collect($manifest->entries)->where('disposition', 'detach_to_default')->count();
        $counts['blocked'] = collect($manifest->entries)->where('disposition', 'blocked')->count();

        foreach ($manifest->entries as $entry) {
            $mediaId = (int) ($entry['media_id'] ?? 0);
            if ($mediaId < 1 || ($entry['active_references'] ?? []) === []) {
                continue;
            }

            $result = $this->review($entry, $scope);
            $counts[$result]++;

            if (! $this->option('apply') || $result !== 'eligible') {
                continue;
            }

            try {
                $created = DB::transaction(fn (): int => $this->commit($entry, $planner, $scope));
                $counts[$created > 0 ? 'created' : 'existing']++;
            } catch (Throwable $exception) {
                $counts['conflict']++;
                $this->components->warn("Media {$mediaId}: {$exception->getMessage()}");
            }
        }

        $mode = $this->option('apply') ? 'Apply' : 'Dry run';
        $this->components->info($mode.': '.collect($counts)->map(fn (int $count, string $key): string => "{$key}={$count}")->implode(', '));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $entry */
    private function review(array $entry, MediaRecordScope $scope): string
    {
        if (($entry['reason'] ?? null) === 'duplicate_storage_identity') {
            return 'duplicate';
        }
        $media = Media::query()->find((int) $entry['media_id']);
        if (! $media instanceof Media) {
            return 'missing';
        }
        if (blank($media->reference_key)) {
            return $scope->allowsForBackfill($media, $this->purposeForEntry($entry)) ? 'missing_reference_key' : 'disallowed';
        }
        if (! $scope->allows($media, $this->purposeForEntry($entry))) {
            return 'disallowed';
        }

        return $this->ownersForEntry($entry) === [] ? 'missing' : 'eligible';
    }

    /** @param array<string, mixed> $entry */
    private function commit(array $entry, LegacyMediaTransitionPlanner $planner, MediaRecordScope $scope): int
    {
        $mediaId = (int) $entry['media_id'];
        /** @var Media|null $media */
        $media = Media::query()->whereKey($mediaId)->lockForUpdate()->first();
        if (! $media instanceof Media) {
            throw new RuntimeException('The reviewed media row disappeared.');
        }

        $owners = $this->lockOwners($entry);
        if ($owners === []) {
            throw new RuntimeException('The reviewed owners no longer hold the reviewed path.');
        }
        $planner->assertEntryCurrent($entry);

        if (blank($media->reference_key) || ! $scope->allows($media, $this->purposeForEntry($entry))) {
            throw new RuntimeException('The reviewed media is no longer attachment-eligible.');
        }

        $types = collect($owners)->pluck('type')->unique()->all();
        $ids = collect($owners)->pluck('id')->all();
        $existing = MediaAttachment::query()
            ->whereIn('attachable_type', $types)
            ->whereIn('attachable_id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (MediaAttachment $attachment): string => "{$attachment->attachable_type}:{$attachment->attachable_id}:{$attachment->role->value}:{$attachment->position}");

        $created = 0;
        foreach ($owners as $owner) {
            $key = "{$owner['type']}:{$owner['id']}:{$owner['role']->value}:0";
            $attachment = $existing->get($key);
            if ($attachment instanceof MediaAttachment) {
                if ((int) $attachment->media_id !== $mediaId) {
                    throw new RuntimeException('A matching owner role already points to another media row.');
                }

                continue;
            }
            // Any other role/position for this owner is a concurrent or
            // malformed shape; do not silently add a second relationship.
            if ($existing->contains(fn (MediaAttachment $item): bool => $item->attachable_type === $owner['type'] && (int) $item->attachable_id === $owner['id'])) {
                throw new RuntimeException('The owner attachment shape changed before commit.');
            }
            MediaAttachment::query()->create([
                'media_id' => $mediaId,
                'attachable_type' => $owner['type'],
                'attachable_id' => $owner['id'],
                'role' => $owner['role']->value,
                'position' => 0,
            ]);
            $created++;
        }

        return $created;
    }

    /** @param array<string, mixed> $entry @return array<int, array{type: string, id: int, role: MediaAttachmentRole, path: string}> */
    private function ownersForEntry(array $entry): array
    {
        $path = (string) ($entry['path'] ?? '');
        $owners = [];
        foreach (($entry['active_references'] ?? []) as $token) {
            if (preg_match('/^content_group:(\d+):cover$/', (string) $token, $matches) === 1) {
                $owners[] = ['type' => 'content_group', 'id' => (int) $matches[1], 'role' => MediaAttachmentRole::Cover, 'path' => $path];
            }
            if (preg_match('/^content_item:(\d+):primary_image$/', (string) $token, $matches) === 1) {
                $owners[] = ['type' => 'content_item', 'id' => (int) $matches[1], 'role' => MediaAttachmentRole::PrimaryImage, 'path' => $path];
            }
        }

        return $owners;
    }

    /** @param array<string, mixed> $entry @return array<int, array{type: string, id: int, role: MediaAttachmentRole, path: string}> */
    private function lockOwners(array $entry): array
    {
        $owners = $this->ownersForEntry($entry);
        $groupIds = collect($owners)->where('type', 'content_group')->pluck('id')->all();
        $itemIds = collect($owners)->where('type', 'content_item')->pluck('id')->all();
        $path = (string) $entry['path'];
        $groups = ContentGroup::query()->whereKey($groupIds)->where('cover_path', $path)->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $items = ContentItem::query()->whereKey($itemIds)->where('image_path', $path)->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        if (count($groups) !== count($groupIds) || count($items) !== count($itemIds)) {
            throw new RuntimeException('An owner path changed before attachment commit.');
        }

        return $owners;
    }

    /** @param array<string, mixed> $entry */
    private function purposeForEntry(array $entry): ImageUploadPurpose
    {
        return ImageUploadPurpose::from((string) ($entry['purpose'] ?? throw new RuntimeException('The reviewed media has no upload purpose.')));
    }
}
