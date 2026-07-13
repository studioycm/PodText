<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormVerificationChannel: string implements HasLabel
{
    case Email = 'email';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return __("admin.form_verification_channels.{$this->value}");
    }
}
