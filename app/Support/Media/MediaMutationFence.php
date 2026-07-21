<?php

namespace App\Support\Media;

use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class MediaMutationFence
{
    public function __construct(
        private readonly MediaRecordScope $scope,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function begin(
        Media $expected,
        User $actor,
        string $ability,
        array $attributes,
    ): MediaMutationOperation {
        return DB::transaction(function () use ($expected, $actor, $ability, $attributes): MediaMutationOperation {
            $locked = $this->scope->lockForUpdateByIds([$expected->getKey()])->firstOrFail();
            Gate::forUser($actor)->authorize($ability, $locked);

            if (
                $locked->disk !== $expected->disk
                || $locked->path !== $expected->path
                || $locked->reference_key !== $expected->reference_key
            ) {
                throw new RuntimeException('The media record changed before the mutation journal was created.');
            }

            $this->assertNoOpenMutation([(int) $locked->getKey()]);

            return MediaMutationOperation::query()->create(array_merge($attributes, [
                'media_id' => $locked->getKey(),
                'media_id_snapshot' => $locked->getKey(),
                'user_id' => $actor->getKey(),
                'media_reference_key' => $locked->reference_key,
            ]));
        });
    }

    /**
     * Lock a raw, intentionally out-of-scope Curator row for the one
     * transition operation that may make it trusted. Nothing using this path
     * gains ordinary media access.
     *
     * @param  array{disk: string, path: string, reference_key: string|null}  $identity
     * @param  array<string, mixed>  $attributes
     */
    public function beginLegacyTransition(
        int $mediaId,
        array $identity,
        User $actor,
        array $attributes,
    ): MediaMutationOperation {
        return DB::transaction(function () use ($mediaId, $identity, $actor, $attributes): MediaMutationOperation {
            /** @var Media|null $locked */
            $locked = Media::query()->whereKey($mediaId)->lockForUpdate()->first();

            if (! $locked instanceof Media) {
                throw new RuntimeException('The legacy media row disappeared before the mutation journal was created.');
            }

            Gate::forUser($actor)->authorize('transitionLegacy', $locked);

            if (
                $locked->disk !== $identity['disk']
                || $locked->path !== $identity['path']
                || $locked->reference_key !== $identity['reference_key']
            ) {
                throw new RuntimeException('The legacy media row changed before the mutation journal was created.');
            }

            $this->assertNoOpenMutation([$mediaId]);

            return MediaMutationOperation::query()->create(array_merge($attributes, [
                'media_id' => $mediaId,
                'media_id_snapshot' => $mediaId,
                'user_id' => $actor->getKey(),
                'media_reference_key' => $locked->reference_key,
            ]));
        });
    }

    /** @param iterable<int, int> $mediaIds */
    public function assertAttachmentAvailable(iterable $mediaIds): void
    {
        $this->assertTransaction();
        $ids = collect($mediaIds)->map(fn (int $id): int => $id)->unique()->values()->all();

        if ($ids === []) {
            return;
        }

        $active = MediaMutationOperation::query()
            ->whereIn('media_id', $ids)
            ->whereIn('operation', [
                MediaMutationOperationType::Rename->value,
                MediaMutationOperationType::Swap->value,
                MediaMutationOperationType::Delete->value,
                MediaMutationOperationType::LegacyTransition->value,
            ])
            ->whereIn('status', [
                MediaMutationStatus::Staged->value,
                MediaMutationStatus::Copied->value,
            ])
            ->lockForUpdate()
            ->exists();

        if ($active) {
            throw new RuntimeException('The media record has an active filesystem mutation.');
        }
    }

    /** Database-only owner repair must not race any unfinished media journal. */
    public function assertNoUnfinishedMutation(iterable $mediaIds): void
    {
        $this->assertTransaction();
        $ids = collect($mediaIds)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values()->all();
        if ($ids !== [] && MediaMutationOperation::query()->whereIn('media_id', $ids)->whereNotIn('status', [MediaMutationStatus::Completed->value, MediaMutationStatus::Failed->value])->lockForUpdate()->exists()) {
            throw new RuntimeException('The media record has an unfinished mutation.');
        }
    }

    public function lockForCommit(MediaMutationOperation $operation): MediaMutationOperation
    {
        $this->assertTransaction();
        $locked = $this->lockOwned($operation);

        if (
            $locked->status !== MediaMutationStatus::Copied
        ) {
            throw new RuntimeException('The media mutation journal lease no longer authorizes this commit.');
        }

        return $locked;
    }

    /** @param array<string, mixed> $attributes */
    public function updateStaged(MediaMutationOperation $operation, array $attributes): MediaMutationOperation
    {
        return DB::transaction(function () use ($operation, $attributes): MediaMutationOperation {
            $locked = $this->lockOwned($operation);

            if ($locked->status !== MediaMutationStatus::Staged) {
                throw new RuntimeException('The media mutation journal is no longer staged.');
            }

            $locked->update($attributes);

            return $locked->refresh();
        });
    }

    public function markCopied(MediaMutationOperation $operation): MediaMutationOperation
    {
        return $this->updateStaged($operation, ['status' => MediaMutationStatus::Copied]);
    }

    public function beginCleanup(MediaMutationOperation $operation): MediaMutationOperation
    {
        return DB::transaction(function () use ($operation): MediaMutationOperation {
            $locked = $this->lockOwned($operation);

            if (! in_array($locked->status, [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending], true)) {
                throw new RuntimeException('The media mutation journal is not ready for committed cleanup.');
            }

            $locked->update(['lease_expires_at' => now()->addMinutes(5)]);

            return $locked->refresh();
        });
    }

    public function finishCleanup(MediaMutationOperation $operation): void
    {
        DB::transaction(function () use ($operation): void {
            $locked = $this->lockOwned($operation);

            if (! in_array($locked->status, [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending], true)) {
                throw new RuntimeException('The media mutation journal cleanup state changed unexpectedly.');
            }

            $locked->update([
                'status' => MediaMutationStatus::Completed,
                'cleanup_completed_at' => now(),
                'completed_at' => now(),
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => null,
            ]);
        });
    }

    public function markCleanupPending(MediaMutationOperation $operation, string $error): void
    {
        DB::transaction(function () use ($operation, $error): void {
            $locked = $this->lockOwned($operation);
            $locked->update([
                'status' => MediaMutationStatus::CleanupPending,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error' => $error,
            ]);
        });
    }

    /** @param array<int, int> $mediaIds */
    private function assertNoOpenMutation(array $mediaIds): void
    {
        $open = MediaMutationOperation::query()
            ->whereIn('media_id', $mediaIds)
            ->whereIn('status', [
                MediaMutationStatus::Staged->value,
                MediaMutationStatus::Copied->value,
                MediaMutationStatus::Committed->value,
                MediaMutationStatus::CleanupPending->value,
            ])
            ->lockForUpdate()
            ->exists();

        if ($open) {
            throw new RuntimeException('The media record already has an incomplete mutation operation.');
        }
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Media mutation fencing requires an active database transaction.');
        }
    }

    private function lockOwned(MediaMutationOperation $operation): MediaMutationOperation
    {
        $locked = MediaMutationOperation::query()
            ->lockForUpdate()
            ->findOrFail($operation->getKey());

        if (
            ! is_string($operation->lease_token)
            || ! is_string($locked->lease_token)
            || ! hash_equals($locked->lease_token, $operation->lease_token)
            || ! $locked->lease_expires_at?->isFuture()
        ) {
            throw new RuntimeException('The media mutation journal lease is expired or owned by another worker.');
        }

        return $locked;
    }
}
