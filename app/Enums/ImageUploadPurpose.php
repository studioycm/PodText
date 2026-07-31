<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImageUploadPurpose: string implements HasLabel
{
    case ContentGroupCover = 'content_group_cover';
    case ContentItemPrimaryImage = 'content_item_primary_image';
    case HeaderLogo = 'header_logo';
    case TeamImage = 'team_image';
    case AboutImage = 'about_image';
    case DefaultImage = 'default_image';

    public function root(): string
    {
        return match ($this) {
            self::ContentGroupCover => 'content-groups/covers',
            self::ContentItemPrimaryImage => 'content-items/images',
            self::HeaderLogo => 'header',
            self::TeamImage => 'team',
            self::AboutImage => 'about',
            self::DefaultImage => 'default-images',
        };
    }

    public function allowsSvg(): bool
    {
        return true;
    }

    public function getLabel(): string
    {
        return __("admin.image_upload_purposes.{$this->value}");
    }
}
