<?php

namespace App\Support\Media;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImageUploadRules
{
    public const MAX_KILOBYTES = 2048;

    public const MAX_DIMENSION_PIXELS = 3000;

    /**
     * @return array<int, string>
     */
    public static function rasterMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp'];
    }

    /**
     * @return array<int, string>
     */
    public static function logoMimeTypes(): array
    {
        return [...self::rasterMimeTypes(), 'image/svg+xml'];
    }

    public static function rasterImage(): File
    {
        return File::image()
            ->types(['jpg', 'jpeg', 'png', 'webp'])
            ->max(self::MAX_KILOBYTES)
            ->dimensions(
                Rule::dimensions()
                    ->maxWidth(self::MAX_DIMENSION_PIXELS)
                    ->maxHeight(self::MAX_DIMENSION_PIXELS),
            );
    }

    public static function logoImage(): File
    {
        return File::image(allowSvg: true)
            ->types(['jpg', 'jpeg', 'png', 'webp', 'svg'])
            ->max(self::MAX_KILOBYTES);
    }
}
