<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TranscriptionPresentationMode: string implements HasLabel
{
    case Collapsible = 'collapsible';
    case Modal = 'modal';
    case SlideOver = 'slideover';

    public function getLabel(): string
    {
        return __("admin.transcription_presentation_modes.{$this->value}");
    }
}
