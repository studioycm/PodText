<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ImportConnectionStatus: string implements HasColor, HasLabel
{
    case Untested = 'untested';
    case Connected = 'connected';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __("admin.importer.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Untested => 'gray',
            self::Connected => 'success',
            self::Failed => 'danger',
        };
    }
}
