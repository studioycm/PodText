<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicFormEmailVerificationMode: string implements HasLabel
{
    case Off = 'off';
    case EmailOtp = 'email_otp';

    public function getLabel(): string
    {
        return __("admin.public_form_email_verification_modes.{$this->value}");
    }
}
