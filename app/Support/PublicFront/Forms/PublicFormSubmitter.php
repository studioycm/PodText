<?php

namespace App\Support\PublicFront\Forms;

use App\Models\PublicFormSubmission;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PublicFormSubmitter
{
    public function __construct(
        private readonly PublicFormPayloadValidator $validator,
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

        return PublicFormSubmission::query()->create([
            'form_key' => $definition['key'],
            'form_name_snapshot' => $definition['name'],
            'payload' => $payload,
            'source_url' => $sourceUrl ?? url()->current(),
            'submitter_ip_hash' => $this->hashValue(request()->ip()),
            'user_agent_hash' => $this->hashValue(request()->userAgent()),
            'metadata' => $metadata,
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
