<?php

namespace App\Enums;

enum ImageUploadPurpose: string
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

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $purpose): array => [
                $purpose->value => __("admin.image_upload_purposes.{$purpose->value}"),
            ])
            ->all();
    }
}
