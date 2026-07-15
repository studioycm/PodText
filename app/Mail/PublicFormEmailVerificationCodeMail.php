<?php

namespace App\Mail;

use App\Support\Forms\Verification\FormVerificationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicFormEmailVerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $formName,
        public readonly string $mailLocale,
    ) {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('public.forms.verification.mail.subject', ['site' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.public-form-email-verification-code',
            with: [
                'code' => $this->code,
                'formName' => $this->formName,
                'locale' => $this->mailLocale,
                'siteName' => config('app.name'),
                'expiresAfterMinutes' => FormVerificationManager::expiresAfterMinutes(),
            ],
        );
    }
}
