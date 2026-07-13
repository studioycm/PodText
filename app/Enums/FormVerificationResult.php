<?php

namespace App\Enums;

enum FormVerificationResult: string
{
    case Verified = 'verified';
    case NotFound = 'not_found';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case AttemptsExceeded = 'attempts_exceeded';
    case Consumed = 'consumed';

    public function message(): string
    {
        return __("public.forms.verification.results.{$this->value}");
    }
}
