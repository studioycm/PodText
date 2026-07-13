<?php

namespace App\Support\Forms\Verification;

interface FormVerificationChannelDriver
{
    public function send(string $address, string $code, string $formName, string $locale): void;
}
