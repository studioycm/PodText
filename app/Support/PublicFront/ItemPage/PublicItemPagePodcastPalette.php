<?php

namespace App\Support\PublicFront\ItemPage;

use App\Support\PublicFront\Colors\PublicFrontColor;
use App\Support\PublicFront\PublicFrontConfigCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicItemPagePodcastPalette
{
    /**
     * @return array<string, array{light: string, dark: string}>
     */
    public function colors(?string $coverPath): array
    {
        if (blank($coverPath) || $this->isRemotePath($coverPath)) {
            return $this->fallbackThemeColors();
        }

        $cacheKey = $this->cacheKey($coverPath);

        if ($cacheKey === null) {
            return $this->computeColors($coverPath);
        }

        return Cache::rememberForever($cacheKey, fn (): array => $this->computeColors($coverPath));
    }

    /**
     * @return array<string, array{light: string, dark: string}>
     */
    protected function computeColors(?string $coverPath): array
    {
        $sampled = $this->sampleColors($coverPath);

        return [
            'image_1' => PublicFrontColor::themeVariants($sampled[0] ?? $this->fallbackColors()['image_1']),
            'image_2' => PublicFrontColor::themeVariants($sampled[1] ?? $this->fallbackColors()['image_2']),
            'image_3' => PublicFrontColor::themeVariants($sampled[2] ?? $this->fallbackColors()['image_3']),
        ];
    }

    private function cacheKey(?string $coverPath): ?string
    {
        if (blank($coverPath) || $this->isRemotePath($coverPath)) {
            return null;
        }

        try {
            $path = Storage::disk('public')->path($coverPath);
        } catch (Throwable) {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mtime = filemtime($path);

        if ($mtime === false) {
            return null;
        }

        return app(PublicFrontConfigCache::class)->podcastPaletteKey($coverPath, $mtime);
    }

    /**
     * @return array<int, string>
     */
    private function sampleColors(?string $coverPath): array
    {
        if (! extension_loaded('gd') || blank($coverPath) || $this->isRemotePath($coverPath)) {
            return [];
        }

        try {
            $path = Storage::disk('public')->path($coverPath);
        } catch (Throwable) {
            return [];
        }

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $image = $this->createImage($path);

        if ($image === null) {
            return [];
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width < 1 || $height < 1) {
                return [];
            }

            return collect([
                [0.25, 0.35],
                [0.5, 0.5],
                [0.75, 0.65],
            ])
                ->map(fn (array $point): string => $this->samplePoint($image, $width, $height, $point[0], $point[1]))
                ->unique()
                ->values()
                ->all();
        } finally {
            imagedestroy($image);
        }
    }

    private function createImage(string $path): mixed
    {
        $type = @getimagesize($path)[2] ?? null;

        $image = match ($type) {
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        return $image ?: null;
    }

    private function samplePoint(mixed $image, int $width, int $height, float $xRatio, float $yRatio): string
    {
        $x = max(0, min($width - 1, (int) floor($width * $xRatio)));
        $y = max(0, min($height - 1, (int) floor($height * $yRatio)));
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

        return $this->hex((int) $color['red'], (int) $color['green'], (int) $color['blue']);
    }

    private function hex(int $red, int $green, int $blue): string
    {
        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    /**
     * @return array<string, string>
     */
    private function fallbackColors(): array
    {
        return [
            'image_1' => '#2563eb',
            'image_2' => '#16a34a',
            'image_3' => '#dc2626',
        ];
    }

    /**
     * @return array<string, array{light: string, dark: string}>
     */
    private function fallbackThemeColors(): array
    {
        return collect($this->fallbackColors())
            ->map(fn (string $color): array => PublicFrontColor::themeVariants($color))
            ->all();
    }

    private function isRemotePath(?string $coverPath): bool
    {
        return is_string($coverPath) && (str_contains($coverPath, '://') || str_starts_with($coverPath, '//'));
    }
}
