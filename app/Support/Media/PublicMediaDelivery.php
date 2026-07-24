<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicMediaDelivery
{
    public const AudienceDenied = 'audience_denied';

    public const MissingFile = 'missing_file';

    public const UnsanitizedInlineSvg = 'unsanitized_inline_svg';

    /** @var array<string, string|null> */
    private array $decisions = [];

    /** @var array<string, bool> */
    private array $inlineDecisions = [];

    /** @var array<string, bool> */
    private array $fileExistence = [];

    public function __construct(private readonly SvgUploadSanitizer $svgSanitizer) {}

    public function fallbackReason(Media $media): ?string
    {
        $cacheKey = $this->cacheKey($media);

        if (array_key_exists($cacheKey, $this->decisions)) {
            return $this->decisions[$cacheKey];
        }

        if ($media->disk !== 'public' || $media->visibility !== 'public') {
            return $this->decisions[$cacheKey] = self::AudienceDenied;
        }

        if (! array_key_exists((string) $media->disk, config('filesystems.disks', []))) {
            return $this->decisions[$cacheKey] = self::MissingFile;
        }

        if (! $this->fileExists($media)) {
            return $this->decisions[$cacheKey] = self::MissingFile;
        }

        if (! $this->canRenderInline($media)) {
            return $this->decisions[$cacheKey] = self::UnsanitizedInlineSvg;
        }

        return $this->decisions[$cacheKey] = null;
    }

    public function canDisplay(Media $media): bool
    {
        return $this->fallbackReason($media) === null;
    }

    public function forget(Media $media): void
    {
        $cacheKey = $this->cacheKey($media);
        unset(
            $this->decisions[$cacheKey],
            $this->inlineDecisions[$cacheKey],
            $this->fileExistence[$cacheKey],
        );
    }

    public function fileExists(Media $media): bool
    {
        $cacheKey = $this->cacheKey($media);

        if (array_key_exists($cacheKey, $this->fileExistence)) {
            return $this->fileExistence[$cacheKey];
        }

        if (! array_key_exists((string) $media->disk, config('filesystems.disks', []))) {
            return $this->fileExistence[$cacheKey] = false;
        }

        try {
            return $this->fileExistence[$cacheKey] = Storage::disk((string) $media->disk)
                ->exists((string) $media->path);
        } catch (Throwable) {
            return $this->fileExistence[$cacheKey] = false;
        }
    }

    public function canRenderInline(Media $media): bool
    {
        if (! $this->isSvg($media)) {
            return true;
        }

        $cacheKey = $this->cacheKey($media);

        return $this->inlineDecisions[$cacheKey]
            ??= $this->hasSafeInlineSvg($media);
    }

    private function cacheKey(Media $media): string
    {
        return implode(':', [
            $media->getKey(),
            $media->disk,
            $media->path,
            $media->type,
            $media->ext,
            $media->updated_at?->getTimestamp() ?? 0,
        ]);
    }

    private function isSvg(Media $media): bool
    {
        $mimeType = mb_strtolower(trim(explode(';', (string) $media->type, 2)[0]));

        return $mimeType === 'image/svg+xml'
            || mb_strtolower((string) $media->ext) === 'svg'
            || mb_strtolower(pathinfo((string) $media->path, PATHINFO_EXTENSION)) === 'svg';
    }

    private function hasSafeInlineSvg(Media $media): bool
    {
        try {
            $stream = Storage::disk((string) $media->disk)->readStream((string) $media->path);

            if (! is_resource($stream)) {
                return false;
            }

            try {
                $contents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }

            if (! is_string($contents) || $contents === '') {
                return false;
            }

            $sanitized = $this->svgSanitizer->sanitize($contents);

            return hash_equals($contents, $sanitized);
        } catch (Throwable) {
            return false;
        }
    }
}
