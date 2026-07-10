<?php

namespace App\Enums;

enum SettingsImportMode: string
{
    case Replace = 'replace';
    case AddOnly = 'add_only';

    public static function normalize(self|string|null $mode): self
    {
        if ($mode instanceof self) {
            return $mode;
        }

        return self::tryFrom((string) $mode) ?? self::Replace;
    }
}
