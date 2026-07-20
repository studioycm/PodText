<?php

namespace App\Filament\Forms;

use App\Enums\ImageUploadPurpose;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Support\Media\ImageFileNamer;
use InvalidArgumentException;

class MediaPickerField
{
    public static function make(string $name, string $family, bool $allowSvg = false): PathCuratorPicker
    {
        $purpose = self::purposeForFamily($family);

        if ($allowSvg && ! $purpose->allowsSvg()) {
            throw new InvalidArgumentException('SVG cannot be enabled for this image purpose.');
        }

        return PathCuratorPicker::make($name)
            ->purpose($purpose)
            ->referenceKeyIdentity()
            ->buttonLabel(__('admin.actions.pick_media'));
    }

    private static function purposeForFamily(string $family): ImageUploadPurpose
    {
        return match ($family) {
            ImageFileNamer::CONTENT_GROUP_COVER => ImageUploadPurpose::ContentGroupCover,
            ImageFileNamer::CONTENT_ITEM_IMAGE => ImageUploadPurpose::ContentItemPrimaryImage,
            ImageFileNamer::HEADER => ImageUploadPurpose::HeaderLogo,
            ImageFileNamer::TEAM => ImageUploadPurpose::TeamImage,
            ImageFileNamer::ABOUT => ImageUploadPurpose::AboutImage,
            ImageFileNamer::DEFAULT_IMAGES => ImageUploadPurpose::DefaultImage,
            default => throw new InvalidArgumentException("Unsupported image family [{$family}]."),
        };
    }
}
