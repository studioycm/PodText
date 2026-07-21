<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\LegacyOwnerMediaDiagnosticCode;
use App\Enums\MediaAttachmentRole;
use App\Models\Media;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MediaIdentityResolver
{
    /** @var array<string, array<string, Collection<int, Media>>> */
    private array $primedLegacyPaths = [];

    /** @var array<string, array<string, Media|null>> */
    private array $primedReferenceKeys = [];

    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly MediaRecordScope $scope,
    ) {}

    public function resolve(
        ?string $referenceKey,
        ?string $legacyPath,
        ImageUploadPurpose $purpose,
    ): ?Media {
        $referenceKey = filled($referenceKey) ? mb_strtoupper(trim((string) $referenceKey)) : null;
        $legacyPath = filled($legacyPath) ? trim((string) $legacyPath) : null;

        if ($referenceKey !== null) {
            $media = array_key_exists($referenceKey, $this->primedReferenceKeys[$purpose->value] ?? [])
                ? $this->primedReferenceKeys[$purpose->value][$referenceKey]
                : $this->scope->findByReferenceKey($referenceKey, $purpose);

            if (! $media instanceof Media || ! $this->scope->allows($media, $purpose)) {
                throw new InvalidArgumentException('The media reference key is unavailable for this purpose.');
            }

            if ($legacyPath !== null && $legacyPath !== $media->path) {
                throw new InvalidArgumentException('The media reference key and legacy path disagree.');
            }

            return $media;
        }

        if ($legacyPath === null) {
            return null;
        }

        return $this->uniqueMediaForPath($legacyPath, $purpose);
    }

    public function path(
        ?string $referenceKey,
        ?string $legacyPath,
        ImageUploadPurpose $purpose,
    ): ?string {
        $media = $this->resolve($referenceKey, $legacyPath, $purpose);

        if ($media instanceof Media) {
            return $media->path;
        }

        if (filled($referenceKey) || blank($legacyPath)) {
            return null;
        }

        return $this->validatedLegacyPath((string) $legacyPath, $purpose);
    }

    /** @param iterable<int, string|null> $paths */
    public function primeLegacyPaths(iterable $paths, ImageUploadPurpose $purpose): void
    {
        $normalized = collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->map(function (string $path): string {
                try {
                    return $this->policy->normalizePath($path);
                } catch (InvalidArgumentException) {
                    return $path;
                }
            })
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return;
        }

        $matches = Media::query()
            ->whereIn('path', $normalized->all())
            ->get()
            ->groupBy(fn (Media $media): string => (string) $media->path);

        foreach ($normalized as $path) {
            $this->primedLegacyPaths[$purpose->value][$path] = $matches->get($path, collect());
        }
    }

    /** @param iterable<int, string|null> $referenceKeys */
    public function primeReferenceKeys(iterable $referenceKeys, ImageUploadPurpose $purpose): void
    {
        $keys = collect($referenceKeys)
            ->filter(fn (mixed $key): bool => is_string($key) && filled($key))
            ->map(fn (string $key): string => mb_strtoupper(trim($key)))
            ->filter(fn (string $key): bool => (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $key))
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return;
        }

        $matches = Media::query()
            ->whereIn('reference_key', $keys->all())
            ->get()
            ->keyBy(fn (Media $media): string => mb_strtoupper((string) $media->reference_key));

        foreach ($keys as $key) {
            $this->primedReferenceKeys[$purpose->value][$key] = $matches->get($key);
        }
    }

    public function validatedLegacyPath(string $legacyPath, ImageUploadPurpose $purpose): string
    {
        $path = $this->policy->normalizePath($legacyPath);

        if ($this->policy->purposeForPath($path) !== $purpose) {
            throw new InvalidArgumentException('The legacy path is incompatible with this purpose.');
        }

        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = collect($this->policy->mimeTypesFor($purpose))
            ->map(fn (string $mimeType): string => $this->policy->canonicalExtension($mimeType))
            ->unique()
            ->all();

        if (! in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('The legacy path extension is incompatible with this purpose.');
        }

        return $path;
    }

    private function uniqueMediaForPath(string $path, ImageUploadPurpose $purpose): ?Media
    {
        try {
            $path = $this->policy->normalizePath($path);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('The legacy media path is invalid.', previous: $exception);
        }

        $matches = $this->primedLegacyPaths[$purpose->value][$path]
            ?? Media::query()
                ->where('path', $path)
                ->limit(2)
                ->get();

        if ($matches->count() > 1) {
            throw new UnsafeLegacyOwnerMediaException(new LegacyOwnerMediaDiagnostic(
                LegacyOwnerMediaDiagnosticCode::DuplicateLegacyRows,
                'unknown', 0,
                $purpose === ImageUploadPurpose::ContentGroupCover ? MediaAttachmentRole::Cover : MediaAttachmentRole::PrimaryImage,
                hash('sha256', 'legacy-duplicate-path\0'.$path.'\0'.implode(',', $matches->pluck('id')->sort()->all())),
            ), 'The legacy media path matches duplicate media rows.');
        }

        /** @var Media|null $media */
        $media = $matches->first();

        if ($media instanceof Media && ! $this->scope->allows($media, $purpose)) {
            throw new UnsafeLegacyOwnerMediaException(new LegacyOwnerMediaDiagnostic(
                LegacyOwnerMediaDiagnosticCode::DisallowedLegacyPath,
                'unknown',
                0,
                $purpose === ImageUploadPurpose::ContentGroupCover ? MediaAttachmentRole::Cover : MediaAttachmentRole::PrimaryImage,
                hash('sha256', "legacy-path\0{$path}\0{$media->getKey()}"),
            ), 'The legacy media path belongs to a disallowed media row.');
        }

        return $media;
    }
}
