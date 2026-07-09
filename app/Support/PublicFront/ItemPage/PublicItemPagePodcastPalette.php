<?php

namespace App\Support\PublicFront\ItemPage;

use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicItemPagePodcastPalette
{
    /**
     * @return array<string, string>
     */
    public function colors(?string $coverPath): array
    {
        $sampled = $this->sampleColors($coverPath);

        return [
            'image_1' => $sampled[0] ?? '#2563eb',
            'image_2' => $sampled[1] ?? '#16a34a',
            'image_3' => $sampled[2] ?? '#dc2626',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sampleColors(?string $coverPath): array
    {
        if (! extension_loaded('gd') || blank($coverPath)) {
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
}
