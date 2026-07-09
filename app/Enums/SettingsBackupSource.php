<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SettingsBackupSource: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case BeforeImport = 'before_import';
    case BeforeRestore = 'before_restore';
    case System = 'system';

    public function getLabel(): string
    {
        return __("admin.settings_backup_sources.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'success',
            self::BeforeImport => 'info',
            self::BeforeRestore => 'warning',
            self::System => 'gray',
        };
    }
}
