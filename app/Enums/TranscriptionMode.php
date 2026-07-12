<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TranscriptionMode: string implements HasLabel
{
    case Single = 'single';
    case Multi = 'multi';

    public function getLabel(): string
    {
        return __("admin.transcription_modes.{$this->value}");
    }
}
