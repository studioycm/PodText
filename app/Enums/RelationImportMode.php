<?php

namespace App\Enums;

enum RelationImportMode: string
{
    case Replace = 'replace';
    case AddOnly = 'add_only';

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromOptions(array $options): self
    {
        return self::tryFrom((string) ($options['relation_mode'] ?? '')) ?? self::Replace;
    }
}
