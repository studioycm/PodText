<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CuratorImageUploadPolicy
{
    public const MAX_KILOBYTES = 2048;

    public const MAX_DIMENSION_PIXELS = 3000;

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
}
