<?php

namespace App\Support\PublicFront\Forms;

use App\Enums\FormVerificationChannel;
use App\Enums\FormVerificationResult;
use App\Models\PublicFormSubmission;
use App\Support\Forms\Verification\FormVerificationManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PublicFormSubmitter
{
    public function __construct(
        private readonly PublicFormPayloadValidator $validator,
        private readonly PublicFormVerificationPolicy $verificationPolicy,
        private readonly FormVerificationManager $verificationManager,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     *
     * @throws ValidationException
     */
    public function submit(
        array $definition,
        array $data,
        string $honeypot = '',
        ?string $sourceUrl = null,
        array $metadata = [],
        ?string $verificationToken = null,
        ?string $verificationCode = null,
    ): PublicFormSubmission {
        if (filled($honeypot)) {
            throw ValidationException::withMessages([
                'form' => __('public.forms.unavailable'),
            ]);
        }

        $rateLimitKey = $this->rateLimitKey($definition);
        $maxAttempts = (int) ($definition['settings']['rate_limit_attempts'] ?? 5);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            throw ValidationException::withMessages([
                'form' => __('public.forms.rate_limited', [
                    'seconds' => RateLimiter::availableIn($rateLimitKey),
                ]),
            ]);
        }

        RateLimiter::hit($rateLimitKey, (int) ($definition['settings']['rate_limit_decay_seconds'] ?? 600));

        $payload = $this->validator->validate($definition, $data);
        $verificationChannel = null;
        $verificationVerifiedAt = null;

        if ($this->verificationPolicy->requiresEmailVerification($definition)) {
            $email = $this->verificationPolicy->submitterEmail($definition, $payload);

            if (blank($email) || blank($verificationToken)) {
                throw ValidationException::withMessages([
                    'verification' => __('public.forms.verification.required'),
                ]);
            }

            if (filled($verificationCode)) {
                $result = $this->verificationManager->verify(
                    channel: FormVerificationChannel::Email,
                    address: (string) $email,
                    formKey: (string) $definition['key'],
                    guestToken: (string) $verificationToken,
                    code: (string) $verificationCode,
                );

                if ($result !== FormVerificationResult::Verified) {
                    throw ValidationException::withMessages([
                        'verification' => $result->message(),
                    ]);
                }
            }

            $verificationVerifiedAt = $this->verificationManager->consume(
                channel: FormVerificationChannel::Email,
                address: (string) $email,
                formKey: (string) $definition['key'],
                guestToken: (string) $verificationToken,
            );

            if ($verificationVerifiedAt === null) {
                throw ValidationException::withMessages([
                    'verification' => __('public.forms.verification.required'),
                ]);
            }

            $verificationChannel = FormVerificationChannel::Email->value;
        }

        return PublicFormSubmission::query()->create([
            'form_key' => $definition['key'],
            'form_name_snapshot' => $definition['name'],
            'payload' => $payload,
            'source_url' => $sourceUrl ?? url()->current(),
            'submitter_ip_hash' => $this->hashValue(request()->ip()),
            'user_agent_hash' => $this->hashValue(request()->userAgent()),
            'metadata' => $metadata,
            'verification_channel' => $verificationChannel,
            'verification_verified_at' => $verificationVerifiedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function rateLimitKey(array $definition): string
    {
        return 'public-form:'.$definition['key'].':'.($this->hashValue(request()->ip().'|'.request()->userAgent()) ?? 'unknown');
    }

    private function hashValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash_hmac('sha256', $value, config('app.key') ?: config('app.name'));
    }
}
