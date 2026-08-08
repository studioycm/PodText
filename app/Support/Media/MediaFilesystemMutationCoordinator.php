<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationRepairResult;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaMutationOperation;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
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
        private readonly PublicFrontConfigCache $publicFrontConfigCache,
        private readonly SettingsCacheFactory $settingsCacheFactory,
        private readonly PublicContentSettingsWriteCoordinator $settingsWriteCoordinator,
        private readonly StoredMediaValidator $storedMediaValidator,
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

    public function rename(Media $media, User $actor): Media
    {
        return $this->mutateExisting($media, $actor, MediaMutationOperationType::Rename);
    }

    /**
     * Re-derives a sanitized copy of the stored bytes and swaps it into the
     * record through the same journaled machinery as rename/swap. Refusal
     * classes throw from validation before anything is journaled.
     */
    public function sanitize(Media $media, User $actor): Media
    {
        return $this->mutateExisting($media, $actor, MediaMutationOperationType::Sanitize);
    }

    /**
     * Moves an unmanaged/root file into the managed covers root through the
     * full journaled machinery, keeping the row id and reference key and
     * rewriting the legacy path references inside the same commit. Bytes are
     * preserved when they validate; only normalization-requiring content
     * (SVG output) differs. Admin-trusted rows relocate verbatim.
     */
    public function relocate(Media $media, User $actor): Media
    {
        $trusted = $this->scope->findInventoryOrFail($media->getKey());
        Gate::forUser($actor)->authorize('relocate', $trusted);
        $this->assertUniqueStorageIdentity($trusted);

        $sourceIsManaged = true;

        try {
            $this->policy->purposeForPath((string) $trusted->path);
        } catch (\InvalidArgumentException) {
            $sourceIsManaged = false;
        }

        if ($sourceIsManaged) {
            throw new RuntimeException('The media record is already managed.');
        }

        if (! Storage::disk('public')->exists((string) $trusted->path)) {
            throw new RuntimeException('The source media file is missing.');
        }

        $settingsPaths = $this->references->settingsIdentityCandidates()['paths'];

        if (in_array((string) $trusted->path, $settingsPaths, true)) {
            throw new RuntimeException('The relocation source is referenced by settings payloads.');
        }

        $purpose = ImageUploadPurpose::ContentGroupCover;
        $sourceContents = $this->readBoundedArtifact('public', (string) $trusted->path);
        $sourceSha256 = hash('sha256', $sourceContents);

        if ($trusted->trusted_at !== null) {
            $destinationImage = new ValidatedImage(
                purpose: $purpose,
                contents: $sourceContents,
                mimeType: (string) $trusted->type,
                extension: $this->policy->canonicalExtension((string) $trusted->type),
                size: strlen($sourceContents),
                width: is_numeric($trusted->width) && (int) $trusted->width > 0 ? (int) $trusted->width : null,
                height: is_numeric($trusted->height) && (int) $trusted->height > 0 ? (int) $trusted->height : null,
                sha256: $sourceSha256,
                displayFilename: pathinfo((string) $trusted->path, PATHINFO_FILENAME),
                originalFilename: basename((string) $trusted->path),
            );
        } else {
            // Content-first validation: the source's claimed extension is
            // exactly what a legacy mismatch gets wrong, so the proof derives
            // mime and canonical extension from the bytes and the relocation
            // normalizes the record.
            $proof = $this->validator->validateExternalBytes(
                $sourceContents,
                pathinfo((string) $trusted->path, PATHINFO_FILENAME),
                $purpose,
            );

            // D4b: original bytes are preserved when they validate; only
            // normalization-requiring content (the SVG sanitizer's output)
            // replaces them.
            $destinationImage = $proof->mimeType === 'image/svg+xml'
                ? $proof
                : new ValidatedImage(
                    purpose: $purpose,
                    contents: $sourceContents,
                    mimeType: $proof->mimeType,
                    extension: $proof->extension,
                    size: strlen($sourceContents),
                    width: $proof->width,
                    height: $proof->height,
                    sha256: $sourceSha256,
                    displayFilename: $proof->displayFilename,
                    originalFilename: $proof->originalFilename,
                );
        }

        $operationKey = (string) Str::ulid();
        $stagingPath = "media-staging/{$operationKey}/relocated.{$destinationImage->extension}";
        $quarantinePath = "media-quarantine/{$operationKey}/original.".(string) $trusted->ext;
        $operation = $this->fence->begin($trusted, $actor, 'relocate', [
            'operation_key' => $operationKey,
            'operation' => MediaMutationOperationType::Relocation,
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
            'context' => [
                'source_directory' => dirname((string) $trusted->path),
                'source_name' => pathinfo((string) $trusted->path, PATHINFO_FILENAME),
                'source_last_modified' => $this->lastModified('public', (string) $trusted->path),
            ],
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;

        try {
            $this->putVerified('local', $quarantinePath, $sourceContents, $sourceSha256);
            $this->putVerified('local', $stagingPath, $destinationImage->contents, $destinationImage->sha256);
            $destinationPath = $this->allocateDestination($purpose, $destinationImage->mimeType);
            $this->fence->updateStaged($operation, ['destination_path' => $destinationPath]);
            $this->putVerified('public', $destinationPath, Storage::disk('local')->get($stagingPath), $destinationImage->sha256, visibility: 'public');
            $this->fence->markCopied($operation);

            $updated = DB::transaction(function () use ($trusted, $actor, $destinationImage, $destinationPath, $operation): Media {
                $locked = Media::query()->lockForUpdate()->findOrFail($trusted->getKey());
                $lockedOperation = $this->fence->lockForCommit($operation);
                Gate::forUser($actor)->authorize('relocate', $locked);
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
     * Issues a portable reference key to a row that never had one — the only
     * sanctioned re-opening of the key immutability invariant, running inside
     * the lease issuance window with a journaled ReferenceKeyBackfill
     * operation and, when the row predates the asset kernel, the missing
     * MediaAsset and Curator provider binding.
     */
    public function mintReferenceKey(Media $media, User $actor): Media
    {
        $trusted = $this->scope->findInventoryOrFail($media->getKey());
        Gate::forUser($actor)->authorize('mintReferenceKey', $trusted);

        if (filled($trusted->reference_key)) {
            throw new \InvalidArgumentException('The media record already has a reference key.');
        }

        $proof = $this->storedMediaValidator->validateForReferenceKeyBackfill($trusted);
        $purpose = $this->policy->purposeForPath((string) $trusted->path);
        $operationKey = (string) Str::ulid();
        $operation = $this->fence->begin($trusted, $actor, 'mintReferenceKey', [
            'operation_key' => $operationKey,
            'operation' => MediaMutationOperationType::ReferenceKeyBackfill,
            'status' => MediaMutationStatus::Staged,
            'purpose' => $purpose->value,
            'destination_disk' => 'public',
            'destination_path' => $trusted->path,
            'destination_sha256' => $proof->sha256,
            'context' => $this->sourceContext($trusted),
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;

        try {
            $this->fence->markCopied($operation);

            $updated = DB::transaction(function () use ($trusted, $actor, $operation): Media {
                $locked = Media::query()->lockForUpdate()->findOrFail($trusted->getKey());
                $lockedOperation = $this->fence->lockForCommit($operation);
                Gate::forUser($actor)->authorize('mintReferenceKey', $locked);

                if (
                    filled($locked->reference_key)
                    || $locked->disk !== $trusted->disk
                    || $locked->path !== $trusted->path
                ) {
                    throw new RuntimeException('The media record changed during reference key minting.');
                }

                $referenceKey = (string) Str::ulid();
                $this->lease->run($locked, fn (): mixed => $this->lease->runReferenceKeyIssuance(
                    function () use ($locked, $referenceKey): void {
                        $locked->forceFill(['reference_key' => $referenceKey])->save();
                    },
                ));

                if (! MediaProviderBinding::query()
                    ->where('provider', 'curator')
                    ->where('provider_record_key', (string) $locked->getKey())
                    ->exists()) {
                    $asset = MediaAsset::query()->create(['reference_key' => $referenceKey]);
                    MediaProviderBinding::query()->create([
                        'media_asset_id' => $asset->getKey(),
                        'provider' => 'curator',
                        'provider_record_key' => (string) $locked->getKey(),
                    ]);
                }

                $lockedOperation->update([
                    'status' => MediaMutationStatus::Committed,
                    'committed_at' => now(),
                    // The journal opened on a key-less row; the issued key is
                    // the committed identity truth.
                    'media_reference_key' => $referenceKey,
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
        $quarantinePath = is_string($sourceContents)
            ? "media-quarantine/{$operationKey}/".basename((string) $trusted->path)
            : null;
        $operation = $this->fence->begin($trusted, $actor, 'delete', [
            'operation_key' => $operationKey,
            'operation' => MediaMutationOperationType::Delete,
            'status' => MediaMutationStatus::Staged,
            'purpose' => $this->policy->purposeForPath((string) $trusted->path)->value,
            'source_disk' => 'public',
            'source_path' => $trusted->path,
            'source_sha256' => $sourceSha256,
            'quarantine_disk' => is_string($sourceContents) ? 'local' : null,
            'quarantine_path' => $quarantinePath,
            'quarantine_sha256' => $sourceSha256,
            'context' => array_merge($this->sourceContext($trusted), [
                'source_missing' => ! is_string($sourceContents),
                'alt' => $trusted->alt,
                'title' => $trusted->title,
                'caption' => $trusted->caption,
                'description' => $trusted->description,
            ]),
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;

        try {
            // A missing source is deletable: the row is the lie, and the
            // journal records the absence instead of a quarantine copy.
            if (is_string($sourceContents)) {
                $this->putVerified('local', (string) $quarantinePath, $sourceContents, $sourceSha256);
            }

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

            return MediaMutationRepairResult::ManualReviewRequired->value;
        }

        if (in_array($operation->status, [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending], true)) {
            $this->completeCommittedCleanup($operation);

            return $operation->fresh()->status === MediaMutationStatus::Completed
                ? MediaMutationRepairResult::CompletedCleanup->value
                : MediaMutationRepairResult::CleanupPending->value;
        }

        if ($this->databaseReflectsCommittedMutation($operation)) {
            $operation = $this->updateClaimedRepairOperation($operation, [
                'status' => MediaMutationStatus::Committed,
                'committed_at' => $operation->committed_at ?? now(),
            ]);

            if (! $operation instanceof MediaMutationOperation) {
                return MediaMutationRepairResult::LeaseLost->value;
            }

            $this->completeCommittedCleanup($operation);

            $status = $operation->fresh()->status;

            return match ($status) {
                MediaMutationStatus::Completed => MediaMutationRepairResult::RecoveredCommitted->value,
                MediaMutationStatus::CleanupPending => MediaMutationRepairResult::CleanupPending->value,
                default => MediaMutationRepairResult::LeaseLost->value,
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
                ? MediaMutationRepairResult::ManualReviewRequired->value
                : MediaMutationRepairResult::LeaseLost->value;
        }

        $updated = $this->updateClaimedRepairOperation($operation, [
            'status' => MediaMutationStatus::Failed,
            'last_error' => 'Uncommitted mutation rolled back by repair.',
            'failed_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);

        return $updated instanceof MediaMutationOperation
            ? MediaMutationRepairResult::RolledBackUncommitted->value
            : MediaMutationRepairResult::LeaseLost->value;
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
        $ability = match ($operationType) {
            MediaMutationOperationType::Rename => 'rename',
            MediaMutationOperationType::Sanitize => 'repair',
            default => 'swap',
        };
        $trusted = $operationType === MediaMutationOperationType::Sanitize
            ? $this->scope->findInventoryOrFail($media->getKey())
            : $this->scope->findOrFail($media->getKey());
        Gate::forUser($actor)->authorize($ability, $trusted);
        $this->assertUniqueStorageIdentity($trusted);

        try {
            $purpose = $this->policy->purposeForPath((string) $trusted->path);
        } catch (\InvalidArgumentException $unmanagedSource) {
            if ($operationType !== MediaMutationOperationType::Sanitize) {
                throw $unmanagedSource;
            }

            // Sanitizing an unmanaged/root source relocates it: the clean
            // copy lands in the covers root — the approved destination for
            // unmanaged sources — so the repaired row re-enters the managed
            // scope with both its safety and path reasons resolved.
            $purpose = ImageUploadPurpose::ContentGroupCover;
        }

        $sourceMissing = ! Storage::disk('public')->exists((string) $trusted->path);

        // A swap replaces the bytes anyway, so a missing source may not block
        // its own restoration — that is the missing-file repair cohort.
        // Operations that derive their output from the source keep the throw.
        if ($sourceMissing && $replacement === null) {
            throw new RuntimeException('The source media file is missing.');
        }

        $sourceContents = $sourceMissing
            ? null
            : $this->readBoundedArtifact('public', (string) $trusted->path);
        $sourceProof = null;

        if (is_string($sourceContents)) {
            try {
                $sourceProof = $this->validator->validateBytes($sourceContents, basename((string) $trusted->path), $purpose);
            } catch (\InvalidArgumentException $sourceRejection) {
                // An unsafe source (for example a refusal-class SVG) may not
                // block its own replacement; the raw source still lands in
                // quarantine below.
                if ($replacement === null) {
                    throw $sourceRejection;
                }

                $sourceProof = null;
            }
        }

        if (
            $sourceProof !== null
            && ($sourceProof->mimeType !== $trusted->type || $sourceProof->extension !== $trusted->ext)
        ) {
            throw new RuntimeException('The source media bytes disagree with the record.');
        }

        $destinationImage = $replacement ?? $sourceProof;

        if (! $destinationImage instanceof ValidatedImage) {
            throw new RuntimeException('The mutation destination bytes are unavailable.');
        }

        if ($destinationImage->purpose !== $purpose) {
            throw new RuntimeException('The replacement purpose is incompatible with the media record.');
        }

        $operationKey = (string) Str::ulid();
        $stagingPath = "media-staging/{$operationKey}/replacement.{$destinationImage->extension}";
        $quarantinePath = $sourceMissing
            ? null
            : "media-quarantine/{$operationKey}/original.".(string) $trusted->ext;
        $sourceSha256 = is_string($sourceContents) ? hash('sha256', $sourceContents) : null;
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
            'quarantine_disk' => $sourceMissing ? null : 'local',
            'quarantine_path' => $quarantinePath,
            'quarantine_sha256' => $sourceSha256,
            'context' => array_merge($this->sourceContext($trusted), [
                'source_missing' => $sourceMissing,
            ]),
            'attempts' => 1,
            'lease_token' => (string) Str::ulid(),
            'lease_expires_at' => now()->addMinutes(5),
            'started_at' => now(),
        ]);
        $committed = false;
        $destinationPath = null;

        try {
            if (is_string($sourceContents)) {
                $this->putVerified('local', (string) $quarantinePath, $sourceContents, $sourceSha256);
            }
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

            if (in_array($operation->operation, [MediaMutationOperationType::Rename, MediaMutationOperationType::Swap, MediaMutationOperationType::Sanitize, MediaMutationOperationType::Relocation, MediaMutationOperationType::Delete, MediaMutationOperationType::Registration, MediaMutationOperationType::LegacyTransition], true)) {
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

            if (in_array($operation->operation, [MediaMutationOperationType::Registration, MediaMutationOperationType::LegacyTransition], true)) {
                $this->forgetRegistrationSettingsCaches();
            }

            if (in_array($operation->operation, [MediaMutationOperationType::Rename, MediaMutationOperationType::Swap, MediaMutationOperationType::Sanitize, MediaMutationOperationType::Relocation, MediaMutationOperationType::Delete, MediaMutationOperationType::Registration, MediaMutationOperationType::LegacyTransition], true)) {
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
        $this->references->forgetSettingsPayloads();
    }

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
                return MediaMutationRepairResult::AlreadyComplete->value;
            }

            if ($locked->lease_expires_at?->isFuture()) {
                return MediaMutationRepairResult::LeaseActive->value;
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
            in_array($operation->operation, [MediaMutationOperationType::Registration, MediaMutationOperationType::LegacyTransition], true)
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

        try {
            $normalizedPath = $this->policy->normalizePath($path);
            $sourcePurpose = $this->policy->purposeForPath($normalizedPath);
        } catch (\InvalidArgumentException) {
            // A root/unmanaged source (a sanitize that relocated it) has no
            // curation directory to clean.
            return;
        }

        $sourceDirectory = dirname($normalizedPath);
        $name = pathinfo($normalizedPath, PATHINFO_FILENAME);
        $curationDirectory = "{$sourceDirectory}/{$name}";
        $purpose = ImageUploadPurpose::tryFrom((string) $operation->purpose);

        if (
            ! $purpose instanceof ImageUploadPurpose
            || $sourceDirectory !== $purpose->root()
            || $sourcePurpose !== $purpose
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

        $context = is_array($operation->context) ? $operation->context : [];
        $sourceMissingWaiver = (bool) ($context['source_missing'] ?? false)
            && in_array($operationType, [MediaMutationOperationType::Swap, MediaMutationOperationType::Delete], true);

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

            if (
                $pathField === 'source_path'
                && in_array($operationType, [
                    MediaMutationOperationType::Sanitize,
                    MediaMutationOperationType::Relocation,
                ], true)
            ) {
                // A sanitize may originate from an unmanaged/root source; its
                // syntax is still constrained, but no purpose root applies.
                if (
                    str_contains((string) $path, '..')
                    || str_contains((string) $path, '//')
                    || preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', (string) $path) !== 1
                ) {
                    throw new RuntimeException('The journaled source_path syntax is invalid.');
                }
            } else {
                $normalized = $this->policy->normalizePath((string) $path);

                if ($normalized !== $path || $this->policy->purposeForPath($normalized) !== $purpose) {
                    throw new RuntimeException("The journaled {$pathField} is outside its purpose root.");
                }
            }

            if ($pathField === 'source_path' && $sourceMissingWaiver) {
                // The source never existed at operation time; there is no
                // checksum truth to assert for it.
                continue;
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
                MediaMutationOperationType::Rename, MediaMutationOperationType::Swap, MediaMutationOperationType::Sanitize, MediaMutationOperationType::Relocation, MediaMutationOperationType::Registration, MediaMutationOperationType::LegacyTransition => [
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

        // A swap that restored a missing file, or a delete of a missing file,
        // has no original bytes to quarantine; the journal records that truth
        // as context.source_missing instead of a quarantine copy.
        if ($sourceMissingWaiver) {
            $required = array_values(array_diff($required, ['quarantine_path']));
        }

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
