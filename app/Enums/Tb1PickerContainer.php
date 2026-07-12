<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Tb1PickerContainer: string implements HasLabel
{
    case Modal = 'modal';
    case SlideOver = 'slideover';

    public function getLabel(): string
    {
        return __("admin.tb1_picker_containers.{$this->value}");
    }
}
