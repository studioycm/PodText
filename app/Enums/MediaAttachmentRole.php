<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaAttachmentRole: string implements HasLabel
{
    case Cover = 'cover';
    case PrimaryImage = 'primary_image';

    public function getLabel(): string
    {
        return __('admin.media_attachment_roles.'.$this->value);
    }

    public function purpose(): ImageUploadPurpose
    {
        return match ($this) {
            self::Cover => ImageUploadPurpose::ContentGroupCover,
            self::PrimaryImage => ImageUploadPurpose::ContentItemPrimaryImage,
        };
    }

    public function ownerAlias(): string
    {
        return match ($this) {
            self::Cover => 'content_group',
            self::PrimaryImage => 'content_item',
        };
    }
}
