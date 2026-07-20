<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class MediaFilesystemMutationCoordinator
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly ImageUploadValidator $validator,
        private readonly MediaRecordScope $scope,
        private readonly MediaReferenceFinder $references,
        private readonly MediaMutationLease $lease,
        private readonly MediaMutationFence $fence,
        private readonly MediaCacheInvalidator $cacheInvalidator,
        private readonly LegacyMediaRegistrationPlanner $registrationPlanner,
        private readonly LegacyMediaReferenceSwitcher $registrationSwitcher,
        private readonly PublicFrontConfigCache $publicFrontConfigCache,
        private readonly SettingsCacheFactory $settingsCacheFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createFromUpload(
        TemporaryUploadedFile|UploadedFile $file,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
    ): Media {
        return $this->createManyFromUploads([$file], $purpose, $actor, $metadata)->firstOrFail();
    }

    /**
     * @param  array<int, TemporaryUploadedFile|UploadedFile>  $files
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, Media>
     */
    public function createManyFromUploads(
        array $files,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
    ): Collection {
        Gate::forUser($actor)->authorize('create', $this->mediaModel());

        if (count($files) > 10 || $files === []) {
            throw new RuntimeException('Between one and ten images must be uploaded.');
        }

        if (count($files) > 1) {
            Gate::forUser($actor)->authorize('bulkUpload', $this->mediaModel());
        }

        $validated = collect($files)
            ->values()
            ->map(function (mixed $file) use ($purpose): ValidatedImage {
                [$contents, $clientFilename] = $this->readUpload($file);

                return $this->validator->validateBytes($contents, $clientFilename, $purpose);
            });

        return $this->createValidatedImages(
            $validated,
            $actor,
            $metadata,
            MediaMutationOperationType::Upload,
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createFromValidatedImage(
        ValidatedImage $image,
        User $actor,
        array $metadata = [],
        MediaMutationOperationType $operationType = MediaMutationOperationType::Upload,
        array $operationContext = [],
    ): Media {
        if (! in_array($operationType, [
            MediaMutationOperationType::Upload,
            MediaMutationOperationType::ExternalImport,
        ], true)) {
            throw new \InvalidArgumentException('Validated image creation supports upload and external import operations only.');
        }

        return $this->createValidatedImages(
            collect([$image]),
            $actor,
            $metadata,
            $operationType,
            $operationContext,
        )->firstOrFail();
    }

    public function registerExistingPublicAsset(
        LegacyMediaRegistrationPlan $plan,
        User $actor,
    ): Media {
        Gate::forUser($actor)->authorize('create', $this->mediaModel());
        $fresh = $this->registrationPlanner->plan($plan->sourcePath);

        if (
            ! hash_equals($plan->fingerprint(), $fresh->fingerprint())
            || ! hash_equals($plan->validatedImage->sha256, $fresh->validatedImage->sha256)
        ) {
            throw new RuntimeException('The legacy media registration plan changed before staging.');
        }

        $plan = $fresh;
        $operationKey = (string) Str::ulid();
        $stagingPath = "media-staging/{$operationKey}/normalized.{$plan->validatedImage->extension}";
        $quarantinePath = "media-quarantine/{$operationKey}/".basename($plan->sourcePath);
        $operation = MediaMutationOperation::query()->create([
            'operation_key' => $operationKey,
            'user_id' => $actor->getKey(),
            'operation' => MediaMutationOperationType::Registration,
            'status' => MediaMutationStatus::Staged,
            'purpose' => $plan->purpose->value,
            'idempotency_key' => hash('sha256', 'registration\0'.$plan->sourcePath.'\0'.$plan->sourceSha256),
            'source_disk' => 'public',
            'source_path' => $plan->sourcePath,
            'source_sha256' => $plan->sourceSha256,
            'destination_disk' => 'public',
            'destination_sha256' => $plan->validatedImage->sha256,
            'staging_disk' => 'local',
            'staging_path' => $stagingPath,
            'staging_sha256' => $plan->validatedImage->sha256,
            'quarantine_disk' => 'local',
            'quarantine_path' => $quarantinePath,
            'quarantine_sha256' => $plan->sourceSha256,
            'context' => [
                'plan_fingerprint' => $plan->fingerprint(),
                'content_group_ids' => $plan->contentGroupIds,
                'content_item_ids' => $plan->contentItemIds,
                'settings_locations' => $plan->settingsLocations(),
                'expected_reference_count' => $plan->referenceCount(),
                'source_directory' => dirname($plan->sourcePath),
                'source_name' => pathinfo($plan->sourcePath, PATHINFO_FILENAME),
                'source_last_modified' => $this->lastModified('public', $plan->sourcePath),
            ],
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;

        try {
            $this->putVerified('local', $quarantinePath, $plan->sourceContents, $plan->sourceSha256);
            $this->putVerified('local', $stagingPath, $plan->validatedImage->contents, $plan->validatedImage->sha256);
            $destinationPath = $this->allocateDestination($plan->purpose, $plan->validatedImage->mimeType);
            $this->fence->updateStaged($operation, ['destination_path' => $destinationPath]);
            $this->putVerified(
                'public',
                $destinationPath,
                Storage::disk('local')->get($stagingPath),
                $plan->validatedImage->sha256,
                visibility: 'public',
            );
            $this->fence->markCopied($operation);

            $media = DB::transaction(function () use ($plan, $actor, $destinationPath, $operation): Media {
                $lockedOperation = $this->fence->lockForCommit($operation);
                $mediaClass = $this->mediaModel();
                /** @var Media $media */
                $media = new $mediaClass;
                $media->forceFill([
                    'disk' => 'public',
                    'directory' => $plan->purpose->root(),
                    'visibility' => 'public',
                    'name' => pathinfo($destinationPath, PATHINFO_FILENAME),
                    'path' => $destinationPath,
                    'width' => $plan->validatedImage->width,
                    'height' => $plan->validatedImage->height,
                    'size' => $plan->validatedImage->size,
                    'type' => $plan->validatedImage->mimeType,
                    'ext' => $plan->validatedImage->extension,
                    'title' => Str::limit((string) $plan->validatedImage->displayFilename, 255, ''),
                ]);
                $this->lease->runCreation(fn (): bool => $media->save());
                Gate::forUser($actor)->authorize('select', $media);
                Gate::forUser($actor)->authorize('attach', $media);
                $this->registrationSwitcher->switch($plan, $media);
                $lockedOperation->update([
                    'media_id' => $media->getKey(),
                    'media_id_snapshot' => $media->getKey(),
                    'media_reference_key' => $media->reference_key,
                    'status' => MediaMutationStatus::Committed,
                    'committed_at' => now(),
                ]);

                return $media->refresh();
            });
            $committed = true;
        } catch (Throwable $exception) {
            if (! $committed) {
                $this->compensateUncommitted($operation, $exception);
            }

            throw $exception;
        }

        $this->completeCommittedCleanup($operation);

        return $media;
    }

    public function rename(Media $media, User $actor): Media
    {
        return $this->mutateExisting($media, $actor, MediaMutationOperationType::Rename);
    }

    public function swap(
        Media $media,
        TemporaryUploadedFile|UploadedFile $replacement,
        User $actor,
    ): Media {
        $trusted = $this->scope->findOrFail($media->getKey());
        Gate::forUser($actor)->authorize('swap', $trusted);
        $purpose = $this->policy->purposeForPath((string) $trusted->path);
        [$contents, $clientFilename] = $this->readUpload($replacement);
        $validated = $this->validator->validateBytes($contents, $clientFilename, $purpose);

        return $this->mutateExisting($trusted, $actor, MediaMutationOperationType::Swap, $validated);
    }

    /**
     * @param  iterable<int, Media>  $media
     */
    public function deleteMany(iterable $media, User $actor): void
    {
        $records = collect($media)
            ->map(fn (Media $record): Media => $this->scope->findOrFail($record->getKey()))
            ->unique(fn (Media $record): int => (int) $record->getKey())
            ->values();

        $this->references->prime($records);

        try {
            Gate::forUser($actor)->authorize('deleteAny', $this->mediaModel());
            $records->each(fn (Media $record) => Gate::forUser($actor)->authorize('delete', $record));
            $records->each(fn (Media $record) => $this->delete($record, $actor));
        } finally {
            $this->references->clearPrime();
        }
    }

    public function delete(Media $media, User $actor): void
    {
        $trusted = $this->scope->findOrFail($media->getKey());
        Gate::forUser($actor)->authorize('delete', $trusted);
        $this->assertUniqueStorageIdentity($trusted);

        $sourceContents = Storage::disk('public')->exists((string) $trusted->path)
            ? $this->readBoundedArtifact('public', (string) $trusted->path)
            : null;
        $sourceSha256 = is_string($sourceContents) ? hash('sha256', $sourceContents) : null;
        $operationKey = (string) Str::ulid();
        $quarantinePath = "media-quarantine/{$operationKey}/".basename((string) $trusted->path);
        $operation = $this->fence->begin($trusted, $actor, 'delete', [
            'operation_key' => $operationKey,
            'operation' => MediaMutationOperationType::Delete,
            'status' => MediaMutationStatus::Staged,
            'purpose' => $this->policy->purposeForPath((string) $trusted->path)->value,
            'source_disk' => 'public',
            'source_path' => $trusted->path,
            'source_sha256' => $sourceSha256,
            'quarantine_disk' => 'local',
            'quarantine_path' => $quarantinePath,
            'quarantine_sha256' => $sourceSha256,
            'context' => array_merge($this->sourceContext($trusted), [
                'source_missing' => ! is_string($sourceContents),
            ]),
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;

        try {
            if (! is_string($sourceContents)) {
                throw new RuntimeException('The source media file is missing; deletion was not committed.');
            }

            $this->putVerified('local', $quarantinePath, $sourceContents, $sourceSha256);
            $this->fence->markCopied($operation);

            DB::transaction(function () use ($trusted, $actor, $operation): void {
                $locked = Media::query()->lockForUpdate()->findOrFail($trusted->getKey());
                $lockedOperation = $this->fence->lockForCommit($operation);
                Gate::forUser($actor)->authorize('delete', $locked);
                $this->assertUniqueStorageIdentity($locked);

                if ($locked->disk !== 'public' || $locked->path !== $trusted->path) {
                    throw new RuntimeException('The media record changed during deletion.');
                }

                $lockedOperation->update([
                    'status' => MediaMutationStatus::Committed,
                    'committed_at' => now(),
                ]);
                $this->lease->run($locked, fn (): bool => (bool) $locked->delete());
            });
            $committed = true;
        } catch (Throwable $exception) {
            if (! $committed) {
                $this->compensateUncommitted($operation, $exception);
            }

            throw $exception;
        }

        $this->completeCommittedCleanup($operation);
    }

    public function repair(MediaMutationOperation $operation): string
    {
        $claimed = $this->claimRepairLease($operation);

        if (is_string($claimed)) {
            return $claimed;
        }

        $operation = $claimed;

        try {
            $this->assertOperationShape($operation);
        } catch (Throwable $exception) {
            $this->recordRepairConflict($operation, $exception);

            return 'manual_review_required';
        }

        if (in_array($operation->status, [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending], true)) {
            $this->completeCommittedCleanup($operation);

            return $operation->fresh()->status === MediaMutationStatus::Completed
                ? 'completed_cleanup'
                : 'cleanup_pending';
        }

        if ($this->databaseReflectsCommittedMutation($operation)) {
            $operation = $this->updateClaimedRepairOperation($operation, [
                'status' => MediaMutationStatus::Committed,
                'committed_at' => $operation->committed_at ?? now(),
            ]);

            if (! $operation instanceof MediaMutationOperation) {
                return 'lease_lost';
            }

            $this->completeCommittedCleanup($operation);

            $status = $operation->fresh()->status;

            return match ($status) {
                MediaMutationStatus::Completed => 'recovered_committed',
                MediaMutationStatus::CleanupPending => 'cleanup_pending',
                default => 'lease_lost',
            };
        }

        $destinationRemoved = $this->deleteUncommittedDestination($operation);
        $stagingRemoved = $this->deleteOwnedArtifact(
            (string) ($operation->staging_disk ?: 'local'),
            $operation->staging_path,
            $operation->staging_sha256,
        );
        $quarantineRemoved = $this->deleteOwnedArtifact(
            (string) ($operation->quarantine_disk ?: 'local'),
            $operation->quarantine_path,
            $operation->quarantine_sha256,
        );

        if (! $destinationRemoved || ! $stagingRemoved || ! $quarantineRemoved) {
            $updated = $this->updateClaimedRepairOperation($operation, [
                'status' => MediaMutationStatus::Failed,
                'last_error' => 'Uncommitted mutation retained an unknown or undeletable artifact for manual review.',
                'failed_at' => now(),
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);

            return $updated instanceof MediaMutationOperation
                ? 'manual_review_required'
                : 'lease_lost';
        }

        $updated = $this->updateClaimedRepairOperation($operation, [
            'status' => MediaMutationStatus::Failed,
            'last_error' => 'Uncommitted mutation rolled back by repair.',
            'failed_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);

        return $updated instanceof MediaMutationOperation
            ? 'rolled_back_uncommitted'
            : 'lease_lost';
    }

    /**
     * @param  Collection<int, ValidatedImage>  $images
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, Media>
     */
    private function createValidatedImages(
        Collection $images,
        User $actor,
        array $metadata,
        MediaMutationOperationType $operationType,
        array $operationContext,
    ): Collection {
        Gate::forUser($actor)->authorize('create', $this->mediaModel());

        /** @var array<int, array{image: ValidatedImage, path: string|null, operation: MediaMutationOperation}> $destinations */
        $destinations = [];
        $committed = false;

        try {
            foreach ($images as $image) {
                $operationKey = (string) Str::ulid();
                $stagingPath = "media-staging/{$operationKey}/normalized.{$image->extension}";
                $operation = MediaMutationOperation::query()->create([
                    'operation_key' => $operationKey,
                    'user_id' => $actor->getKey(),
                    'operation' => $operationType,
                    'status' => MediaMutationStatus::Staged,
                    'purpose' => $image->purpose->value,
                    'idempotency_key' => is_string($operationContext['idempotency_key'] ?? null)
                        ? $operationContext['idempotency_key']
                        : null,
                    'destination_disk' => 'public',
                    'destination_sha256' => $image->sha256,
                    'staging_disk' => 'local',
                    'staging_path' => $stagingPath,
                    'staging_sha256' => $image->sha256,
                    'context' => Arr::except($operationContext, ['idempotency_key']),
                    'attempts' => 1,
                    'lease_token' => (string) Str::ulid(),
                    'lease_expires_at' => now()->addMinutes(5),
                    'started_at' => now(),
                ]);
                $destinationIndex = count($destinations);
                $destinations[] = ['image' => $image, 'path' => null, 'operation' => $operation];

                $this->putVerified('local', $stagingPath, $image->contents, $image->sha256);
                $path = $this->allocateDestination(
                    $image->purpose,
                    $image->mimeType,
                    array_values(array_filter(array_column($destinations, 'path'), 'is_string')),
                );
                $destinations[$destinationIndex]['path'] = $path;
                $this->fence->updateStaged($operation, ['destination_path' => $path]);
                $this->putVerified('public', $path, Storage::disk('local')->get($stagingPath), $image->sha256, visibility: 'public');
                $this->fence->markCopied($operation);
            }

            $created = DB::transaction(function () use ($destinations, $metadata): Collection {
                return collect($destinations)->map(function (array $destination) use ($metadata): Media {
                    $image = $destination['image'];
                    $path = $destination['path'];

                    if (! is_string($path)) {
                        throw new RuntimeException('The media destination was not allocated.');
                    }

                    $lockedOperation = $this->fence->lockForCommit($destination['operation']);

                    $mediaClass = $this->mediaModel();
                    /** @var Media $media */
                    $media = new $mediaClass;
                    $media->forceFill([
                        'disk' => 'public',
                        'directory' => $image->purpose->root(),
                        'visibility' => 'public',
                        'name' => pathinfo($path, PATHINFO_FILENAME),
                        'path' => $path,
                        'width' => $image->width,
                        'height' => $image->height,
                        'size' => $image->size,
                        'type' => $image->mimeType,
                        'ext' => $image->extension,
                        'alt' => $this->metadata($metadata, 'alt', 255),
                        'title' => filled(Arr::get($metadata, 'title'))
                            ? $this->metadata($metadata, 'title', 255)
                            : Str::limit((string) $image->displayFilename, 255, ''),
                        'description' => $this->metadata($metadata, 'description', 65000),
                        'caption' => $this->metadata($metadata, 'caption', 65000),
                    ]);
                    $this->lease->runCreation(fn (): bool => $media->save());
                    $lockedOperation->update([
                        'media_id' => $media->getKey(),
                        'media_id_snapshot' => $media->getKey(),
                        'media_reference_key' => $media->reference_key,
                        'status' => MediaMutationStatus::Committed,
                        'committed_at' => now(),
                    ]);

                    return $media->refresh();
                });
            });
            $committed = true;
        } catch (Throwable $exception) {
            if (! $committed) {
                foreach ($destinations as $destination) {
                    $this->compensateUncommitted($destination['operation'], $exception);
                }
            }

            throw $exception;
        }

        foreach ($destinations as $destination) {
            $this->completeCommittedCleanup($destination['operation']);
        }

        return $created;
    }

    private function mutateExisting(
        Media $media,
        User $actor,
        MediaMutationOperationType $operationType,
        ?ValidatedImage $replacement = null,
    ): Media {
        $ability = $operationType === MediaMutationOperationType::Rename ? 'rename' : 'swap';
        $trusted = $this->scope->findOrFail($media->getKey());
        Gate::forUser($actor)->authorize($ability, $trusted);
        $this->assertUniqueStorageIdentity($trusted);
        $purpose = $this->policy->purposeForPath((string) $trusted->path);

        if (! Storage::disk('public')->exists((string) $trusted->path)) {
            throw new RuntimeException('The source media file is missing.');
        }

        $sourceContents = $this->readBoundedArtifact('public', (string) $trusted->path);
        $sourceProof = $this->validator->validateBytes($sourceContents, basename((string) $trusted->path), $purpose);

        if ($sourceProof->mimeType !== $trusted->type || $sourceProof->extension !== $trusted->ext) {
            throw new RuntimeException('The source media bytes disagree with the record.');
        }

        $destinationImage = $replacement ?? $sourceProof;

        if ($destinationImage->purpose !== $purpose) {
            throw new RuntimeException('The replacement purpose is incompatible with the media record.');
        }

        $operationKey = (string) Str::ulid();
        $stagingPath = "media-staging/{$operationKey}/replacement.{$destinationImage->extension}";
        $quarantinePath = "media-quarantine/{$operationKey}/original.".(string) $trusted->ext;
        $sourceSha256 = hash('sha256', $sourceContents);
        $operation = $this->fence->begin($trusted, $actor, $ability, [
            'operation_key' => $operationKey,
            'operation' => $operationType,
            'status' => MediaMutationStatus::Staged,
            'purpose' => $purpose->value,
            'source_disk' => 'public',
            'source_path' => $trusted->path,
            'source_sha256' => $sourceSha256,
            'destination_disk' => 'public',
            'destination_sha256' => $destinationImage->sha256,
            'staging_disk' => 'local',
            'staging_path' => $stagingPath,
            'staging_sha256' => $destinationImage->sha256,
            'quarantine_disk' => 'local',
            'quarantine_path' => $quarantinePath,
            'quarantine_sha256' => $sourceSha256,
            'context' => $this->sourceContext($trusted),
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;
        $destinationPath = null;

        try {
            $this->putVerified('local', $quarantinePath, $sourceContents, $sourceSha256);
            $this->putVerified('local', $stagingPath, $destinationImage->contents, $destinationImage->sha256);
            $destinationPath = $this->allocateDestination($purpose, $destinationImage->mimeType);
            $this->fence->updateStaged($operation, ['destination_path' => $destinationPath]);
            $this->putVerified('public', $destinationPath, Storage::disk('local')->get($stagingPath), $destinationImage->sha256, visibility: 'public');
            $this->fence->markCopied($operation);

            $updated = DB::transaction(function () use ($trusted, $actor, $ability, $destinationImage, $destinationPath, $operation): Media {
                $locked = Media::query()->lockForUpdate()->findOrFail($trusted->getKey());
                $lockedOperation = $this->fence->lockForCommit($operation);
                Gate::forUser($actor)->authorize($ability, $locked);
                $this->assertUniqueStorageIdentity($locked);

                if ($locked->disk !== 'public' || $locked->path !== $trusted->path) {
                    throw new RuntimeException('The media record changed during mutation.');
                }

                $this->lease->run($locked, function () use ($locked, $destinationImage, $destinationPath): void {
                    $locked->forceFill([
                        'disk' => 'public',
                        'directory' => $destinationImage->purpose->root(),
                        'visibility' => 'public',
                        'name' => pathinfo($destinationPath, PATHINFO_FILENAME),
                        'path' => $destinationPath,
                        'width' => $destinationImage->width,
                        'height' => $destinationImage->height,
                        'size' => $destinationImage->size,
                        'type' => $destinationImage->mimeType,
                        'ext' => $destinationImage->extension,
                        'curations' => null,
                    ])->save();
                });
                $lockedOperation->update([
                    'status' => MediaMutationStatus::Committed,
                    'committed_at' => now(),
                ]);

                return $locked->refresh();
            });
            $committed = true;
        } catch (Throwable $exception) {
            if (! $committed) {
                $this->compensateUncommitted($operation, $exception);
            }

            throw $exception;
        }

        $this->completeCommittedCleanup($operation);

        return $updated;
    }

    private function completeCommittedCleanup(MediaMutationOperation $operation): void
    {
        try {
            $operation = $this->fence->beginCleanup($operation);
            $this->assertOperationShape($operation);
            $this->assertCommittedState($operation);

            if (in_array($operation->operation, [MediaMutationOperationType::Rename, MediaMutationOperationType::Swap, MediaMutationOperationType::Delete, MediaMutationOperationType::Registration], true)) {
                $context = is_array($operation->context) ? $operation->context : [];

                if (! (bool) ($context['source_missing'] ?? false)) {
                    $this->assertArtifactMatches(
                        (string) ($operation->quarantine_disk ?: 'local'),
                        $operation->quarantine_path,
                        $operation->quarantine_sha256,
                        'source quarantine',
                    );
                }
            }

            if ($operation->operation === MediaMutationOperationType::Registration) {
                $this->forgetRegistrationSettingsCaches();
            }

            if (in_array($operation->operation, [MediaMutationOperationType::Rename, MediaMutationOperationType::Swap, MediaMutationOperationType::Delete, MediaMutationOperationType::Registration], true)) {
                $this->cleanupCommittedSource($operation);
            }

            if (filled($operation->destination_path)) {
                $this->cacheInvalidator->invalidate(
                    (string) ($operation->destination_disk ?: 'public'),
                    (string) $operation->destination_path,
                );
            }

            if (! $this->deleteOwnedArtifact(
                (string) ($operation->staging_disk ?: 'local'),
                $operation->staging_path,
                $operation->staging_sha256,
            )) {
                throw new RuntimeException('The verified staging artifact could not be removed.');
            }

            $this->fence->finishCleanup($operation);
        } catch (Throwable $exception) {
            try {
                $this->fence->markCleanupPending(
                    $operation,
                    Str::limit($exception->getMessage(), 2000, ''),
                );
            } catch (Throwable) {
                // A replacement repair lease owns the journal; the stale worker must not overwrite it.
            }
        }
    }

    private function forgetRegistrationSettingsCaches(): void
    {
        foreach ($this->settingsCacheFactory->all() as $settingsCache) {
            $settingsCache->clear();
        }

        app()->forgetInstance(PublicContentSettings::class);
        app(SettingsContainer::class)->clearCache();
        $this->publicFrontConfigCache->forget();
    }

    /**
     * @param  array<int, string>  $reserved
     */
    private function allocateDestination(ImageUploadPurpose $purpose, string $mimeType, array $reserved = []): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $path = $this->policy->generatedPath($purpose, $mimeType);

            if (
                in_array($path, $reserved, true)
                || Storage::disk('public')->exists($path)
                || Media::query()->where('disk', 'public')->where('path', $path)->exists()
            ) {
                continue;
            }

            return $path;
        }

        throw new RuntimeException('Unable to allocate a unique media destination.');
    }

    private function putVerified(
        string $disk,
        string $path,
        string $contents,
        ?string $sha256,
        ?string $visibility = null,
    ): void {
        $stored = $visibility === null
            ? Storage::disk($disk)->put($path, $contents)
            : Storage::disk($disk)->put($path, $contents, $visibility);

        if (! $stored) {
            throw new RuntimeException('The media bytes could not be copied.');
        }

        $copied = Storage::disk($disk)->get($path);

        if ($sha256 !== null && ! hash_equals($sha256, hash('sha256', $copied))) {
            throw new RuntimeException('The copied media checksum does not match.');
        }
    }

    private function assertUniqueStorageIdentity(Media $media): void
    {
        if (Media::query()
            ->where('disk', $media->disk)
            ->where('path', $media->path)
            ->whereKeyNot($media->getKey())
            ->exists()) {
            throw new RuntimeException('The media storage location is shared by duplicate records.');
        }

        if (Media::query()
            ->where('disk', $media->disk)
            ->where('directory', $media->directory)
            ->where('name', $media->name)
            ->whereKeyNot($media->getKey())
            ->exists()) {
            throw new RuntimeException('The Curator curation identity is shared by another media record.');
        }

        if ((string) $media->name !== pathinfo((string) $media->path, PATHINFO_FILENAME)) {
            throw new RuntimeException('The media name does not match its storage path.');
        }
    }

    /** @return array<string, int|string|null> */
    private function sourceContext(Media $media): array
    {
        $lastModified = null;

        try {
            if (Storage::disk((string) $media->disk)->exists((string) $media->path)) {
                $lastModified = Storage::disk((string) $media->disk)->lastModified((string) $media->path);
            }
        } catch (Throwable) {
            $lastModified = null;
        }

        return [
            'source_directory' => (string) $media->directory,
            'source_name' => (string) $media->name,
            'source_last_modified' => $lastModified,
        ];
    }

    private function databaseReflectsCommittedMutation(MediaMutationOperation $operation): bool
    {
        if ($operation->operation === MediaMutationOperationType::Delete) {
            return false;
        }

        try {
            $this->assertCommittedState($operation);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function claimRepairLease(MediaMutationOperation $operation): MediaMutationOperation|string
    {
        return DB::transaction(function () use ($operation): MediaMutationOperation|string {
            $locked = MediaMutationOperation::query()
                ->lockForUpdate()
                ->findOrFail($operation->getKey());

            $status = MediaMutationStatus::tryFrom((string) $locked->getRawOriginal('status'));

            if ($status === MediaMutationStatus::Completed) {
                return 'already_complete';
            }

            if ($locked->lease_expires_at?->isFuture()) {
                return 'lease_active';
            }

            $locked->forceFill([
                'attempts' => $locked->attempts + 1,
                'lease_token' => (string) Str::ulid(),
                'lease_expires_at' => now()->addMinutes(5),
            ])->save();

            return $locked->refresh();
        });
    }

    private function assertCommittedState(MediaMutationOperation $operation): void
    {
        if ($operation->operation === MediaMutationOperationType::Delete) {
            $mediaId = $operation->media_id ?: $operation->media_id_snapshot;

            if (blank($mediaId) || Media::query()->whereKey($mediaId)->exists()) {
                throw new RuntimeException('The delete operation is not reflected in the database.');
            }

            if (filled($operation->source_path) && Media::query()
                ->where('disk', (string) ($operation->source_disk ?: 'public'))
                ->where('path', $operation->source_path)
                ->exists()) {
                throw new RuntimeException('Another media record still owns the delete source path.');
            }

            return;
        }

        if (
            blank($operation->media_id)
            || blank($operation->media_reference_key)
            || blank($operation->destination_path)
        ) {
            throw new RuntimeException('The committed media identity is incomplete.');
        }

        $media = Media::query()->whereKey($operation->media_id)->first();

        if (
            ! $media instanceof Media
            || $media->reference_key !== $operation->media_reference_key
            || $media->disk !== (string) ($operation->destination_disk ?: 'public')
            || $media->path !== $operation->destination_path
        ) {
            throw new RuntimeException('The database media identity does not match the journal.');
        }

        $this->assertArtifactMatches(
            (string) ($operation->destination_disk ?: 'public'),
            $operation->destination_path,
            $operation->destination_sha256,
            'committed destination',
        );
    }

    private function cleanupCommittedSource(MediaMutationOperation $operation): void
    {
        if (blank($operation->source_path)) {
            return;
        }

        $disk = (string) ($operation->source_disk ?: 'public');
        $path = (string) $operation->source_path;

        if (
            $operation->operation === MediaMutationOperationType::Registration
            && $this->references->referencesForPath($path) !== []
        ) {
            throw new RuntimeException('The registered legacy source still has application references.');
        }

        if (Media::query()->where('disk', $disk)->where('path', $path)->exists()) {
            throw new RuntimeException('A media record still owns the cleanup source path.');
        }

        $context = is_array($operation->context) ? $operation->context : [];
        $name = is_string($context['source_name'] ?? null) ? $context['source_name'] : null;
        $lastModified = is_int($context['source_last_modified'] ?? null)
            ? $context['source_last_modified']
            : null;

        $this->cacheInvalidator->invalidate($disk, $path, $name, $lastModified);

        if (Storage::disk($disk)->exists($path)) {
            $this->assertArtifactMatches($disk, $path, $operation->source_sha256, 'cleanup source');
            $deleted = Storage::disk($disk)->delete($path);

            if (! $deleted && Storage::disk($disk)->exists($path)) {
                throw new RuntimeException('The committed source file could not be removed.');
            }
        }

        $normalizedPath = $this->policy->normalizePath($path);
        $sourceDirectory = dirname($normalizedPath);
        $name = pathinfo($normalizedPath, PATHINFO_FILENAME);
        $curationDirectory = "{$sourceDirectory}/{$name}";
        $purpose = ImageUploadPurpose::tryFrom((string) $operation->purpose);

        if (
            ! $purpose instanceof ImageUploadPurpose
            || $sourceDirectory !== $purpose->root()
            || $this->policy->purposeForPath($normalizedPath) !== $purpose
        ) {
            throw new RuntimeException('The curation cleanup directory is outside the journaled purpose root.');
        }

        if (Media::query()
            ->where('disk', $disk)
            ->where('directory', $sourceDirectory)
            ->where('name', $name)
            ->exists()) {
            throw new RuntimeException('Another media record still owns the curation cleanup identity.');
        }

        if (Storage::disk($disk)->directoryExists($curationDirectory)) {
            $deleted = Storage::disk($disk)->deleteDirectory($curationDirectory);

            if (! $deleted && Storage::disk($disk)->directoryExists($curationDirectory)) {
                throw new RuntimeException('The stale curation directory could not be removed.');
            }
        }
    }

    private function assertArtifactMatches(
        string $disk,
        ?string $path,
        ?string $expectedSha256,
        string $label,
    ): void {
        if (blank($path) || blank($expectedSha256) || ! Storage::disk($disk)->exists((string) $path)) {
            throw new RuntimeException("The {$label} is missing or has no checksum proof.");
        }

        $actualSha256 = $this->checksum($disk, (string) $path);

        if (! is_string($actualSha256) || ! hash_equals((string) $expectedSha256, $actualSha256)) {
            throw new RuntimeException("The {$label} checksum does not match the journal.");
        }
    }

    private function deleteUncommittedDestination(MediaMutationOperation $operation): bool
    {
        if (blank($operation->destination_path)) {
            return true;
        }

        $disk = (string) ($operation->destination_disk ?: 'public');
        $path = (string) $operation->destination_path;

        if (Media::query()->where('disk', $disk)->where('path', $path)->exists()) {
            return false;
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return true;
            }

            return $this->deleteOwnedArtifact($disk, $path, $operation->destination_sha256);
        } catch (Throwable) {
            return false;
        }
    }

    private function deleteOwnedArtifact(string $disk, ?string $path, ?string $expectedSha256): bool
    {
        try {
            if (blank($path) || ! Storage::disk($disk)->exists((string) $path)) {
                return true;
            }

            if (blank($expectedSha256)) {
                return false;
            }

            $actualSha256 = $this->checksum($disk, (string) $path);

            if (! is_string($actualSha256) || ! hash_equals((string) $expectedSha256, $actualSha256)) {
                return false;
            }

            $deleted = Storage::disk($disk)->delete((string) $path);

            return $deleted || ! Storage::disk($disk)->exists((string) $path);
        } catch (Throwable) {
            return false;
        }
    }

    private function checksum(string $disk, string $path): ?string
    {
        try {
            $stream = Storage::disk($disk)->readStream($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }

    /** @return array{string, string} */
    private function readUpload(mixed $file): array
    {
        if (! $file instanceof TemporaryUploadedFile && ! $file instanceof UploadedFile) {
            throw new \InvalidArgumentException('The upload state is invalid.');
        }

        if (($file->getSize() ?: 0) > CuratorImageUploadPolicy::MAX_KILOBYTES * 1024) {
            throw new \InvalidArgumentException('The uploaded image exceeds the allowed size.');
        }

        if ($file instanceof TemporaryUploadedFile) {
            $contents = $file->get();
        } else {
            $realPath = $file->getRealPath();
            $contents = is_string($realPath) ? file_get_contents($realPath) : false;
        }

        if (! is_string($contents)) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        return [$contents, $file->getClientOriginalName()];
    }

    private function metadata(array $metadata, string $key, int $limit): ?string
    {
        $value = Arr::get($metadata, $key);

        return is_string($value) && filled($value) ? Str::limit($value, $limit, '') : null;
    }

    private function fail(MediaMutationOperation $operation, Throwable $exception): void
    {
        $operation->update([
            'status' => MediaMutationStatus::Failed,
            'lease_token' => null,
            'lease_expires_at' => null,
            'last_error' => Str::limit($exception->getMessage(), 2000, ''),
            'failed_at' => now(),
        ]);
    }

    private function compensateUncommitted(MediaMutationOperation $operation, Throwable $exception): void
    {
        $expectedLeaseToken = $operation->lease_token;

        try {
            $operation->refresh();
        } catch (Throwable) {
            return;
        }

        if (
            ! is_string($expectedLeaseToken)
            || ! is_string($operation->lease_token)
            || ! hash_equals($expectedLeaseToken, $operation->lease_token)
        ) {
            return;
        }

        $failedArtifacts = [];

        if (! $this->deleteUncommittedDestination($operation)) {
            $failedArtifacts[] = 'destination';
        }

        if (! $this->deleteOwnedArtifact(
            (string) ($operation->staging_disk ?: 'local'),
            $operation->staging_path,
            $operation->staging_sha256,
        )) {
            $failedArtifacts[] = 'staging';
        }

        if (! $this->deleteOwnedArtifact(
            (string) ($operation->quarantine_disk ?: 'local'),
            $operation->quarantine_path,
            $operation->quarantine_sha256,
        )) {
            $failedArtifacts[] = 'quarantine';
        }

        $message = $exception->getMessage();

        if ($failedArtifacts !== []) {
            $message .= ' Compensation retained: '.implode(', ', $failedArtifacts).'.';
        }

        try {
            $this->fail($operation, new RuntimeException($message, previous: $exception));
        } catch (Throwable) {
            // Preserve the causal exception. The incomplete operation remains reportable.
        }
    }

    private function recordRepairConflict(MediaMutationOperation $operation, Throwable $exception): void
    {
        try {
            DB::transaction(function () use ($operation, $exception): void {
                $locked = MediaMutationOperation::query()
                    ->lockForUpdate()
                    ->findOrFail($operation->getKey());
                $claimedToken = (string) $operation->getRawOriginal('lease_token');
                $currentToken = (string) $locked->getRawOriginal('lease_token');

                if ($claimedToken === '' || ! hash_equals($claimedToken, $currentToken)) {
                    return;
                }

                $locked->forceFill([
                    'status' => MediaMutationStatus::Failed,
                    'last_error' => Str::limit('Repair refused: '.$exception->getMessage(), 2000, ''),
                    'failed_at' => now(),
                    'lease_token' => null,
                    'lease_expires_at' => null,
                ])->save();
            });
        } catch (Throwable) {
            // A corrupt or unavailable journal must never trigger filesystem mutation.
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateClaimedRepairOperation(
        MediaMutationOperation $operation,
        array $attributes,
    ): ?MediaMutationOperation {
        return DB::transaction(function () use ($operation, $attributes): ?MediaMutationOperation {
            $locked = MediaMutationOperation::query()
                ->lockForUpdate()
                ->findOrFail($operation->getKey());
            $claimedToken = (string) $operation->getRawOriginal('lease_token');
            $currentToken = (string) $locked->getRawOriginal('lease_token');

            if (
                $claimedToken === ''
                || ! hash_equals($claimedToken, $currentToken)
                || ! $locked->lease_expires_at?->isFuture()
            ) {
                return null;
            }

            $locked->forceFill($attributes)->save();

            return $locked->refresh();
        });
    }

    private function assertOperationShape(MediaMutationOperation $operation): void
    {
        $operationKey = (string) $operation->operation_key;
        $operationType = MediaMutationOperationType::tryFrom(
            (string) $operation->getRawOriginal('operation'),
        );
        $status = MediaMutationStatus::tryFrom(
            (string) $operation->getRawOriginal('status'),
        );

        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $operationKey) !== 1) {
            throw new RuntimeException('The media mutation operation key is invalid.');
        }

        if (! $operationType instanceof MediaMutationOperationType) {
            throw new RuntimeException('The media mutation operation type is invalid.');
        }

        if (! $status instanceof MediaMutationStatus) {
            throw new RuntimeException('The media mutation status is invalid.');
        }

        $purpose = ImageUploadPurpose::tryFrom((string) $operation->purpose);

        if (! $purpose instanceof ImageUploadPurpose) {
            throw new RuntimeException('The media mutation purpose is invalid.');
        }

        foreach ([
            ['source_disk', 'source_path', 'source_sha256'],
            ['destination_disk', 'destination_path', 'destination_sha256'],
        ] as [$diskField, $pathField, $checksumField]) {
            $path = $operation->getAttribute($pathField);

            if (blank($path)) {
                continue;
            }

            if ($operation->getAttribute($diskField) !== 'public') {
                throw new RuntimeException("The journaled {$pathField} disk is invalid.");
            }

            $normalized = $this->policy->normalizePath((string) $path);

            if ($normalized !== $path || $this->policy->purposeForPath($normalized) !== $purpose) {
                throw new RuntimeException("The journaled {$pathField} is outside its purpose root.");
            }

            $this->assertSha256($operation->getAttribute($checksumField), $checksumField);
        }

        foreach ([
            ['staging_disk', 'staging_path', 'staging_sha256', 'media-staging'],
            ['quarantine_disk', 'quarantine_path', 'quarantine_sha256', 'media-quarantine'],
        ] as [$diskField, $pathField, $checksumField, $root]) {
            $path = $operation->getAttribute($pathField);

            if (blank($path)) {
                continue;
            }

            if (
                $operation->getAttribute($diskField) !== 'local'
                || preg_match('#^'.preg_quote($root.'/'.$operationKey.'/', '#').'[A-Za-z0-9._-]+$#', (string) $path) !== 1
            ) {
                throw new RuntimeException("The journaled {$pathField} is outside its operation directory.");
            }

            $this->assertSha256($operation->getAttribute($checksumField), $checksumField);
        }

        $required = in_array($status, [
            MediaMutationStatus::Committed,
            MediaMutationStatus::CleanupPending,
            MediaMutationStatus::Completed,
        ], true)
            ? match ($operationType) {
                MediaMutationOperationType::Delete => ['source_path', 'quarantine_path'],
                MediaMutationOperationType::Rename, MediaMutationOperationType::Swap, MediaMutationOperationType::Registration => [
                    'source_path',
                    'destination_path',
                    'staging_path',
                    'quarantine_path',
                ],
                MediaMutationOperationType::Upload, MediaMutationOperationType::ExternalImport => [
                    'destination_path',
                    'staging_path',
                ],
                MediaMutationOperationType::ReferenceKeyBackfill => ['destination_path'],
            }
        : [];

        foreach ($required as $field) {
            if (blank($operation->getAttribute($field))) {
                throw new RuntimeException("The journaled {$field} is missing.");
            }
        }
    }

    private function assertSha256(mixed $checksum, string $field): void
    {
        if (! is_string($checksum) || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new RuntimeException("The journaled {$field} is invalid.");
        }
    }

    private function readBoundedArtifact(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The media source could not be read.');
        }

        $limit = CuratorImageUploadPolicy::MAX_KILOBYTES * 1024;

        try {
            $contents = stream_get_contents($stream, $limit + 1);
        } finally {
            fclose($stream);
        }

        if (! is_string($contents) || strlen($contents) > $limit) {
            throw new RuntimeException('The media source exceeds the allowed size.');
        }

        return $contents;
    }

    private function lastModified(string $disk, string $path): ?int
    {
        try {
            return Storage::disk($disk)->exists($path)
                ? Storage::disk($disk)->lastModified($path)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return class-string<Media> */
    private function mediaModel(): string
    {
        return config('curator.model', Media::class);
    }
}
