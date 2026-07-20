<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class StoredMediaValidator
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly ImageUploadValidator $validator,
        private readonly MediaRecordScope $scope,
    ) {}

    public function validate(Media $media, ?ImageUploadPurpose $purpose = null): ValidatedImage
    {
        if (! $this->scope->allowsForBackfill($media, $purpose)) {
            throw new InvalidArgumentException('The stored media metadata is outside the app-owned image policy.');
        }

        $path = $this->policy->normalizePath((string) $media->path);
        $recordPurpose = $this->policy->purposeForPath($path);

        if (Media::query()
            ->where('disk', $media->disk)
            ->where('path', $media->path)
            ->whereKeyNot($media->getKey())
            ->exists() || Media::query()
            ->where('disk', $media->disk)
            ->where('directory', $media->directory)
            ->where('name', $media->name)
            ->whereKeyNot($media->getKey())
            ->exists()) {
            throw new InvalidArgumentException('The stored media identity is shared by another record.');
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                throw new InvalidArgumentException('The stored media file is missing.');
            }

            if (Storage::disk('public')->visibility($path) !== 'public') {
                throw new InvalidArgumentException('The stored media file is not public.');
            }

            $contents = $this->readBounded($path);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The stored media file could not be inspected.', previous: $exception);
        }

        $validated = $this->validator->validateBytes($contents, basename($path), $recordPurpose);

        if (
            $validated->mimeType !== $media->type
            || $validated->extension !== $media->ext
            || strlen($contents) !== (int) $media->size
            || (! $validated->isSvg() && (
                $validated->width !== (int) $media->width
                || $validated->height !== (int) $media->height
            ))
        ) {
            throw new InvalidArgumentException('The stored media bytes disagree with the record or require normalization.');
        }

        if (blank($media->reference_key)) {
            if (! hash_equals($contents, $validated->contents)) {
                throw new InvalidArgumentException('The stored media bytes require normalization before a reference key can be issued.');
            }
        } elseif (! $this->hasJournalChecksumProof($media, hash('sha256', $contents))) {
            throw new InvalidArgumentException('The stored media bytes lack a matching app-owned journal checksum proof.');
        }

        return $validated;
    }

    public function validateForReferenceKeyBackfill(Media $media): ValidatedImage
    {
        if ($media->type === 'image/svg+xml') {
            throw new InvalidArgumentException('Existing SVG media is pending the separately approved sanitation runbook.');
        }

        return $this->validate($media);
    }

    private function readBounded(string $path): string
    {
        $stream = Storage::disk('public')->readStream($path);

        if (! is_resource($stream)) {
            throw new InvalidArgumentException('The stored media file could not be read.');
        }

        try {
            $maximum = CuratorImageUploadPolicy::MAX_KILOBYTES * 1024;
            $contents = stream_get_contents($stream, $maximum + 1);

            if (! is_string($contents) || $contents === '' || strlen($contents) > $maximum) {
                throw new InvalidArgumentException('The stored media file is empty or exceeds the size limit.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    private function hasJournalChecksumProof(Media $media, string $sha256): bool
    {
        return MediaMutationOperation::query()
            ->where('media_id', $media->getKey())
            ->where('media_reference_key', $media->reference_key)
            ->where('destination_disk', 'public')
            ->where('destination_path', $media->path)
            ->where('destination_sha256', $sha256)
            ->whereIn('operation', [
                MediaMutationOperationType::Upload->value,
                MediaMutationOperationType::ExternalImport->value,
                MediaMutationOperationType::Registration->value,
                MediaMutationOperationType::Rename->value,
                MediaMutationOperationType::Swap->value,
                MediaMutationOperationType::ReferenceKeyBackfill->value,
            ])
            ->whereIn('status', [
                MediaMutationStatus::Committed->value,
                MediaMutationStatus::CleanupPending->value,
                MediaMutationStatus::Completed->value,
            ])
            ->exists();
    }
}
