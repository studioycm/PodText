<?php

namespace App\Support\Media;

use App\Enums\LegacyMediaTransitionDisposition;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Executes only exact rows from a reviewed, closed transition manifest. */
class LegacyMediaTransitionExecutor
{
    public function __construct(
        private readonly LegacyMediaTransitionPlanner $planner,
        private readonly LegacyMediaRegistrationPlanner $registrationPlanner,
        private readonly MediaFilesystemMutationCoordinator $filesystem,
        private readonly MediaMutationFence $fence,
        private readonly MediaMutationLease $lease,
    ) {}

    /** @param array<int, int> $mediaIds @return array<int, string> */
    public function apply(array $mediaIds, string $expectedDigest, User $actor): array
    {
        $manifest = $this->planner->manifest();
        $this->planner->assertClosed($manifest);

        if (! hash_equals($expectedDigest, $manifest->digest())) {
            throw new RuntimeException('The reviewed legacy transition manifest changed before apply.');
        }

        $ids = collect($mediaIds)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->sort()->values();
        if ($ids->count() !== 1) {
            throw new RuntimeException('Exactly one reviewed media ID is required per legacy transition apply.');
        }

        return $ids->map(function (int $mediaId) use ($manifest, $actor): string {
            $entry = collect($manifest->entries)->firstWhere('media_id', $mediaId);
            if (! is_array($entry)) {
                throw new RuntimeException("Media [{$mediaId}] is not in the reviewed manifest.");
            }

            if (($entry['disposition'] ?? null) === LegacyMediaTransitionDisposition::Blocked->value) {
                $proofStatus = $this->proofStatus($mediaId);
                if ($proofStatus === MediaMutationStatus::Completed) {
                    return 'already_transitioned';
                }
                if (in_array($proofStatus, [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending], true)) {
                    return 'cleanup_pending';
                }
            }

            $disposition = LegacyMediaTransitionDisposition::from((string) $entry['disposition']);
            if ($disposition === LegacyMediaTransitionDisposition::KeyOnly) {
                return $this->issueKeyOnly($mediaId, $entry, $actor);
            }
            if (in_array($disposition, [LegacyMediaTransitionDisposition::NormalizeExisting, LegacyMediaTransitionDisposition::SanitizeSvg], true)) {
                return $this->normalizeExisting($mediaId, $entry, $actor);
            }

            throw new RuntimeException("Media [{$mediaId}] has no executable transition in this package.");
        })->all();
    }

    /** Execute one reviewed rowless path; `media_id=0` is never a selector. */
    public function applyPath(string $path, string $expectedDigest, User $actor): string
    {
        $manifest = $this->planner->manifest();
        $this->planner->assertClosed($manifest);
        if (! hash_equals($expectedDigest, $manifest->digest())) {
            if ($this->verifiedCompletedRegistration($path, $expectedDigest, $actor)) {
                return 'already_registered';
            }
            throw new RuntimeException('The reviewed legacy transition manifest changed before apply.');
        }
        $entry = collect($manifest->entries)->first(fn (array $entry): bool => (int) ($entry['media_id'] ?? 0) === 0 && hash_equals((string) ($entry['path'] ?? ''), $path));
        if (! is_array($entry)) {
            if ($this->verifiedCompletedRegistration($path, $expectedDigest, $actor)) {
                return 'already_registered';
            }
        }
        if (! is_array($entry) || ($entry['disposition'] ?? null) !== LegacyMediaTransitionDisposition::ImportExactPath->value) {
            throw new RuntimeException('The exact path is not an importable reviewed rowless transition.');
        }
        $plan = $this->registrationPlanner->plan($path);
        if (! hash_equals((string) ($entry['source_sha256'] ?? ''), $plan->sourceSha256) || ! hash_equals((string) ($entry['normalized_sha256'] ?? ''), $plan->validatedImage->sha256)) {
            throw new RuntimeException('The reviewed rowless media proof changed before import.');
        }
        $media = $this->filesystem->registerExistingPublicAsset($plan, $actor, $expectedDigest);

        return "import_exact_path_completed:{$media->getKey()}";
    }

    private function verifiedCompletedRegistration(string $path, string $digest, User $actor): bool
    {
        $operation = MediaMutationOperation::query()->with('media')->where('operation', MediaMutationOperationType::Registration->value)->where('source_disk', 'public')->where('source_path', $path)->where('status', MediaMutationStatus::Completed->value)->latest('id')->first();
        if (! $operation instanceof MediaMutationOperation
            || ! hash_equals((string) data_get($operation->context, 'reviewed_manifest_digest'), $digest)
            || ! $operation->media instanceof Media
            || blank($operation->media_reference_key)) {
            return false;
        }
        Gate::forUser($actor)->authorize('create', new Media);
        $media = $operation->media;
        if ($media->reference_key !== $operation->media_reference_key || $media->disk !== $operation->destination_disk || $media->path !== $operation->destination_path) {
            return false;
        }
        try {
            return Storage::disk((string) $operation->destination_disk)->exists((string) $operation->destination_path)
                && hash_equals((string) $operation->destination_sha256, hash('sha256', Storage::disk((string) $operation->destination_disk)->get((string) $operation->destination_path)));
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $entry */
    private function issueKeyOnly(int $mediaId, array $entry, User $actor): string
    {
        return DB::transaction(function () use ($mediaId, $entry, $actor): string {
            $media = Media::query()->whereKey($mediaId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('transitionLegacy', $media);

            if (filled($media->reference_key)) {
                return 'already_transitioned';
            }
            $source = Storage::disk('public')->get((string) $media->path);
            if (! hash_equals((string) $entry['source_sha256'], hash('sha256', $source))) {
                throw new RuntimeException('The exact-byte legacy media proof changed before key issuance.');
            }
            if (! hash_equals((string) $entry['source_sha256'], (string) $entry['normalized_sha256'])) {
                throw new RuntimeException('The legacy row requires normalization, not key-only issuance.');
            }
            $this->planner->assertEntryCurrent($entry);

            $fresh = collect($this->planner->manifest()->entries)->firstWhere('media_id', $mediaId);
            if (! is_array($fresh) || ($fresh['disposition'] ?? null) !== LegacyMediaTransitionDisposition::KeyOnly->value || ! hash_equals((string) $entry['source_sha256'], (string) ($fresh['source_sha256'] ?? ''))) {
                throw new RuntimeException('The reviewed legacy transition plan changed before key issuance.');
            }
            $key = (string) Str::ulid();
            $operation = $this->fence->beginLegacyTransition($mediaId, [
                'disk' => (string) $media->disk, 'path' => (string) $media->path, 'reference_key' => null,
            ], $actor, [
                'operation_key' => (string) Str::ulid(), 'operation' => MediaMutationOperationType::ReferenceKeyBackfill,
                'status' => MediaMutationStatus::Staged, 'purpose' => app(CuratorImageUploadPolicy::class)->purposeForPath((string) $media->path)->value,
                'idempotency_key' => hash('sha256', "legacy-key-only\0{$mediaId}\0{$entry['source_sha256']}"),
                'source_disk' => 'public', 'source_path' => $media->path, 'source_sha256' => $entry['source_sha256'],
                'destination_disk' => 'public', 'destination_path' => $media->path, 'destination_sha256' => $entry['source_sha256'],
                'context' => ['legacy_transition' => true, 'proof' => 'exact_normalized_bytes'], 'attempts' => 1,
                'lease_token' => (string) Str::ulid(), 'lease_expires_at' => now()->addMinutes(5), 'started_at' => now(),
            ]);
            // Key-only has no byte copy, but it still crosses the same fenced
            // staged/copy/commit lifecycle so an open journal is never hidden.
            $this->fence->markCopied($operation);
            $lockedOperation = $this->fence->lockForCommit($operation);
            $this->lease->run($media, fn () => $this->lease->runReferenceKeyIssuance(fn () => $media->forceFill(['reference_key' => $key])->save()));
            $lockedOperation->update(['media_reference_key' => $key, 'status' => MediaMutationStatus::Committed, 'committed_at' => now()]);
            $this->fence->finishCleanup($operation);

            return 'key_only_completed';
        });
    }

    /** @param array<string, mixed> $entry */
    private function normalizeExisting(int $mediaId, array $entry, User $actor): string
    {
        $row = Media::query()->findOrFail($mediaId);
        if (filled($row->reference_key)) {
            return 'already_transitioned';
        }
        $plan = $this->registrationPlanner->plan((string) $entry['path'], expectedExistingMediaId: $mediaId);
        if ($plan->existingMediaId !== $mediaId || ! hash_equals((string) $entry['source_sha256'], $plan->sourceSha256)) {
            throw new RuntimeException('The reviewed legacy media row changed before normalization.');
        }
        $updated = $this->filesystem->transitionLegacyExisting($plan, $entry, $actor);
        $operation = MediaMutationOperation::query()
            ->where('media_id', $updated->getKey())
            ->where('operation', MediaMutationOperationType::LegacyTransition)
            ->latest('id')
            ->first();

        if (! $operation instanceof MediaMutationOperation || $operation->status !== MediaMutationStatus::Completed) {
            return 'cleanup_pending';
        }

        return ($entry['disposition'] ?? null) === LegacyMediaTransitionDisposition::SanitizeSvg->value
            ? 'sanitize_svg_completed'
            : 'normalize_existing_completed';
    }

    private function proofStatus(int $mediaId): ?MediaMutationStatus
    {
        $media = Media::query()->find($mediaId);
        if (! $media instanceof Media || blank($media->reference_key)) {
            return null;
        }
        $operation = MediaMutationOperation::query()
            ->where('media_id', $mediaId)
            ->where('media_reference_key', $media->reference_key)
            ->where('destination_disk', $media->disk)
            ->where('destination_path', $media->path)
            ->whereIn('operation', [MediaMutationOperationType::ReferenceKeyBackfill, MediaMutationOperationType::LegacyTransition])
            ->whereIn('status', [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending, MediaMutationStatus::Completed])
            ->latest('id')
            ->first();

        if (! $operation instanceof MediaMutationOperation || ! is_string($operation->destination_sha256)) {
            return null;
        }

        try {
            return Storage::disk((string) $operation->destination_disk)->exists((string) $operation->destination_path)
                && hash_equals($operation->destination_sha256, hash('sha256', Storage::disk((string) $operation->destination_disk)->get((string) $operation->destination_path)))
                    ? $operation->status
                    : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
