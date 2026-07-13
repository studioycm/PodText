<?php

namespace App\Support\Forms\Verification;

use App\Mail\PublicFormEmailVerificationCodeMail;
use Illuminate\Support\Facades\Mail;

class EmailFormVerificationChannel implements FormVerificationChannelDriver
{
    public function send(string $address, string $code, string $formName, string $locale): void
    {
        Mail::to($address)->locale($locale)->queue(
            new PublicFormEmailVerificationCodeMail(
                code: $code,
                formName: $formName,
                mailLocale: $locale,
            ),
        );
    }
}
