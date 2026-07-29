<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Settings\AdminUxSettings;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class CuratorImageUploadPolicy
{
    public const MAX_KILOBYTES = 4096;

    public const MAX_DIMENSION_PIXELS = 6000;

    public const MAX_CONFIGURABLE_KILOBYTES = 10240;

    public const MAX_CONFIGURABLE_DIMENSION_PIXELS = 10000;

    public function maxKilobytes(): int
    {
        return min(max($this->integerSetting('media_acquisition_max_kilobytes', self::MAX_KILOBYTES), 64), self::MAX_CONFIGURABLE_KILOBYTES);
    }

    public function maxDimensionPixels(): int
    {
        return min(max($this->integerSetting('media_acquisition_max_dimension', self::MAX_DIMENSION_PIXELS), 64), self::MAX_CONFIGURABLE_DIMENSION_PIXELS);
    }

    public function uploadBatchLimit(): int
    {
        return min(max($this->integerSetting('media_acquisition_upload_batch_limit', 10), 1), 20);
    }

    public function uploadQueueLimit(): int
    {
        return min(max($this->integerSetting('media_upload_queue_limit', 40), $this->uploadBatchLimit()), 100);
    }

    /**
     * Admissions above this stay allowed — the admin is informed, not blocked.
     */
    public function heavyUploadWarningKilobytes(): int
    {
        return min(max($this->integerSetting('media_heavy_upload_warning_kilobytes', 2048), 64), self::MAX_KILOBYTES);
    }

    public function pickerBrowseLimit(): int
    {
        return min(max($this->integerSetting('media_picker_browse_limit', 25), 10), 100);
    }

    public function pickerSearchLimit(): int
    {
        return min(max($this->integerSetting('media_picker_search_limit', 50), 10), 100);
    }

    /**
     * @var array<string, array<int, string>>
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
    ];

    /**
     * @return array<int, string>
     */
    public function rasterMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp'];
    }

    /**
     * @return array<int, string>
     */
    public function globalMimeTypes(): array
    {
        return array_keys(self::MIME_EXTENSIONS);
    }

    /**
     * @return array<int, string>
     */
    public function clientExtensions(): array
    {
        return collect(self::MIME_EXTENSIONS)->flatten()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public function mimeTypesFor(ImageUploadPurpose $purpose): array
    {
        return $purpose->allowsSvg()
            ? $this->globalMimeTypes()
            : $this->rasterMimeTypes();
    }

    public function allowsMime(ImageUploadPurpose $purpose, string $mimeType): bool
    {
        return in_array(mb_strtolower($mimeType), $this->mimeTypesFor($purpose), true);
    }

    public function extensionMatchesMime(string $extension, string $mimeType): bool
    {
        return in_array(
            mb_strtolower(ltrim($extension, '.')),
            self::MIME_EXTENSIONS[mb_strtolower($mimeType)] ?? [],
            true,
        );
    }

    public function canonicalExtension(string $mimeType): string
    {
        return match (mb_strtolower($mimeType)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => throw new InvalidArgumentException("Unsupported image MIME type [{$mimeType}]."),
        };
    }

    public function clientExtension(string $filename): string
    {
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->clientExtensions(), true)) {
            throw new InvalidArgumentException('The client file extension is not allowed.');
        }

        return $extension;
    }

    public function cleanedOriginalFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', trim($filename)));
        $basename = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename);
        $basename = is_string($basename) ? trim($basename) : '';

        if ($basename === '' || in_array($basename, ['.', '..'], true)) {
            return 'image';
        }

        return Str::limit($basename, 255, '');
    }

    public function generatedPath(ImageUploadPurpose $purpose, string $mimeType): string
    {
        return $purpose->root().'/'.Str::ulid().'.'.$this->canonicalExtension($mimeType);
    }

    public function normalizeRoot(string $root): string
    {
        $normalized = $this->normalizeRelativeValue($root);

        if (! in_array($normalized, $this->roots(), true)) {
            throw new InvalidArgumentException('The media root is not app-owned.');
        }

        return $normalized;
    }

    public function normalizePath(string $path): string
    {
        $normalized = $this->normalizeRelativeValue($path);
        $purpose = $this->purposeForPath($normalized);
        $relative = substr($normalized, strlen($purpose->root()) + 1);

        if ($relative === '' || str_contains($relative, '/')) {
            throw new InvalidArgumentException('The media path must contain one generated filename beneath its root.');
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $relative)) {
            throw new InvalidArgumentException('The media filename is not normalized.');
        }

        return $normalized;
    }

    public function purposeForPath(string $path): ImageUploadPurpose
    {
        $normalized = $this->normalizeRelativeValue($path);

        foreach (ImageUploadPurpose::cases() as $purpose) {
            if (str_starts_with($normalized, $purpose->root().'/')) {
                return $purpose;
            }
        }

        throw new InvalidArgumentException('The media path is outside an app-owned root.');
    }

    /**
     * @return array<int, string>
     */
    public function roots(): array
    {
        return array_map(
            static fn (ImageUploadPurpose $purpose): string => $purpose->root(),
            ImageUploadPurpose::cases(),
        );
    }

    private function normalizeRelativeValue(string $value): string
    {
        $value = trim($value);

        if (
            $value === ''
            || str_contains($value, "\0")
            || str_contains($value, '\\')
            || str_contains($value, '%')
            || str_starts_with($value, '/')
            || str_ends_with($value, '/')
            || str_contains($value, '//')
        ) {
            throw new InvalidArgumentException('The media path is not normalized.');
        }

        $segments = explode('/', $value);

        if (collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..')) {
            throw new InvalidArgumentException('The media path contains a traversal segment.');
        }

        return implode('/', $segments);
    }

    private function integerSetting(string $property, int $default): int
    {
        try {
            return (int) app(AdminUxSettings::class)->{$property};
        } catch (Throwable) {
            return $default;
        }
    }
}
