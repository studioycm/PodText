<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionDisposition;
use App\Models\Media;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

class MediaAcquisitionManager
{
    public function __construct(
        private readonly ImageUploadValidator $validator,
        private readonly MediaAcquisitionNamer $namer,
        private readonly CuratorMediaAdmission $admission,
        private readonly SafeExternalImageFetcher $externalFetcher,
        private readonly StorageImageCandidateBrowser $storageCandidates,
        private readonly CuratorImageUploadPolicy $policy,
        private readonly MediaImageMetadataStripper $metadataStripper,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function acquireUpload(
        TemporaryUploadedFile|UploadedFile $upload,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
    ): Media {
        $image = $this->validator->validateUploadedFileForAdmission($upload, $purpose);

        return $this->admitNewFile($image, $actor, $metadata);
    }

    /**
     * @param  array<int, TemporaryUploadedFile|UploadedFile>  $uploads
     * @param  array<string, mixed>  $metadata
     */
    public function acquireUploads(
        array $uploads,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
    ): MediaUploadBatchResult {
        $entries = array_values($uploads);

        /** @var Collection<int, array{index: int, reason: string}> $failed */
        $failed = collect();

        /** @var array<int, ValidatedImage> $validated */
        $validated = [];

        foreach ($entries as $index => $upload) {
            try {
                $validated[$index] = $this->validator->validateUploadedFileForAdmission($upload, $purpose);
            } catch (InvalidArgumentException) {
                $failed->push(['index' => $index, 'reason' => 'validation']);
            }
        }

        if ($failed->isNotEmpty()) {
            return new MediaUploadBatchResult(
                successful: collect(),
                failed: $failed,
                notAttemptedIndexes: array_values(array_diff(array_keys($entries), $failed->pluck('index')->all())),
            );
        }

        /** @var Collection<int, MediaAcquisitionResult> $successful */
        $successful = collect();
        $admittedIndexes = [];
        $indexes = array_keys($validated);

        foreach ($indexes as $position => $index) {
            try {
                $successful->push(new MediaAcquisitionResult(
                    media: $this->admitNewFile($validated[$index], $actor, $metadata),
                    disposition: MediaAcquisitionDisposition::Created,
                ));
                $admittedIndexes[] = $index;
            } catch (AuthorizationException $exception) {
                throw $exception;
            } catch (Exception $exception) {
                report($exception);

                return new MediaUploadBatchResult(
                    successful: $successful,
                    failed: collect([['index' => $index, 'reason' => 'admission']]),
                    notAttemptedIndexes: array_slice($indexes, $position + 1),
                    admittedIndexes: $admittedIndexes,
                );
            }
        }

        return new MediaUploadBatchResult(successful: $successful, admittedIndexes: $admittedIndexes);
    }

    /** @param array<string, mixed> $metadata */
    public function acquireBytes(
        string $contents,
        string $originalFilename,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
        bool $externalFilename = false,
    ): Media {
        $image = $externalFilename
            ? $this->validator->validateExternalBytes($contents, $originalFilename, $purpose)
            : $this->validator->validateAdmissionBytes($contents, $originalFilename, $purpose);

        return $this->admitNewFile($image, $actor, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public function acquireExternalUrl(
        string $url,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
    ): Media {
        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: 'external-image'));

        return $this->acquireBytes(
            contents: $this->externalFetcher->fetch($url),
            originalFilename: basename($path) ?: 'external-image',
            purpose: $purpose,
            actor: $actor,
            metadata: $metadata,
            externalFilename: true,
        );
    }

    /** @return array<int, array{token: string, filename: string, source: string}> */
    public function storageCandidates(string $search = ''): array
    {
        return $this->storageCandidates->browse($search);
    }

    /** @param array<string, mixed> $metadata */
    public function acquireStorageCandidate(
        string $token,
        ImageUploadPurpose $purpose,
        User $actor,
        array $metadata = [],
    ): MediaAcquisitionResult {
        $candidate = $this->storageCandidates->resolve($token);
        $lockKey = 'media-acquisition:storage:'.hash('sha256', $candidate->disk."\0".$candidate->path);
        $lockSeconds = min(max((int) config('media.acquisition.storage_lock_seconds', 60), 1), 60);
        $waitSeconds = min(max((int) config('media.acquisition.storage_lock_wait_seconds', 3), 0), 10);

        return Cache::lock($lockKey, $lockSeconds)->block(
            $waitSeconds,
            function () use ($token, $purpose, $actor, $metadata): MediaAcquisitionResult {
                $candidate = $this->storageCandidates->resolve($token);
                $existing = Media::query()
                    ->where('disk', $candidate->disk)
                    ->where('path', $candidate->path)
                    ->first();

                if ($existing instanceof Media) {
                    Gate::forUser($actor)->authorize('select', $existing);

                    return new MediaAcquisitionResult(
                        media: $existing,
                        disposition: MediaAcquisitionDisposition::Reused,
                    );
                }

                $filesystem = Storage::disk($candidate->disk);
                $size = $filesystem->size($candidate->path);

                if ($size < 1 || $size > $this->policy->maxKilobytes() * 1024) {
                    throw new InvalidArgumentException('The Storage candidate exceeds the allowed size or is empty.');
                }

                $contents = $filesystem->get($candidate->path);
                $image = $this->validator->validateAdmissionBytes($contents, $candidate->filename, $purpose);

                if ($candidate->disk === 'public' && $candidate->mode === 'register' && ! $image->isSvg()) {
                    return new MediaAcquisitionResult(
                        media: $this->admission->create($image, 'public', $candidate->path, $actor, $metadata),
                        disposition: MediaAcquisitionDisposition::Registered,
                    );
                }

                return new MediaAcquisitionResult(
                    media: $this->admitNewFile($image, $actor, $metadata),
                    disposition: MediaAcquisitionDisposition::Copied,
                );
            },
        );
    }

    /** @param array<string, mixed> $metadata */
    private function admitNewFile(ValidatedImage $image, User $actor, array $metadata): Media
    {
        $image = $this->metadataStripper->stripForAdmission($image);
        $path = $this->namer->destination($image);
        $stored = Storage::disk('public')->put($path, $image->contents, ['visibility' => 'public']);

        if (! $stored) {
            throw new RuntimeException('The acquired image could not be written.');
        }

        try {
            return $this->admission->create($image, 'public', $path, $actor, $metadata);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }
}
