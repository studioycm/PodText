<?php

namespace App\Enums;

enum ContentItemTagExportScope: string
{
    case EnabledOnly = 'enabled_only';
    case AllTags = 'all_tags';

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromOptions(array $options): self
    {
        return self::tryFrom((string) ($options['tag_scope'] ?? '')) ?? self::EnabledOnly;
    }
}
