<?php

namespace App\Support\Forms\Verification;

use App\Enums\FormVerificationChannel;
use App\Enums\FormVerificationResult;
use App\Models\FormVerificationCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormVerificationManager
{
    private const ADDRESS_HOURLY_LIMIT = 5;

    private const IP_HOURLY_LIMIT = 20;

    public function __construct(
        private readonly EmailFormVerificationChannel $email,
    ) {}

    public function send(
        FormVerificationChannel $channel,
        string $address,
        string $formKey,
        string $formName,
        string $guestToken,
        ?string $ipAddress = null,
        ?string $locale = null,
    ): FormVerificationCode {
        $address = $this->normalizeAddress($channel, $address);
        $guestTokenHash = $this->hashToken($guestToken);

        $this->ensureSendAllowed($channel, $address, $formKey, $ipAddress);

        FormVerificationCode::query()
            ->where('channel', $channel)
            ->where('address', $address)
            ->where('form_key', $formKey)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = FormVerificationCode::query()->create([
            'channel' => $channel,
            'address' => $address,
            'code_hash' => $this->hashCode($code),
            'form_key' => $formKey,
            'guest_token_hash' => $guestTokenHash,
            'expires_at' => now()->addMinutes(self::expiresAfterMinutes()),
            'attempts' => 0,
        ]);

        $this->driver($channel)->send(
            address: $address,
            code: $code,
            formName: $formName,
            locale: $locale ?: app()->getLocale(),
        );

        RateLimiter::hit($this->cooldownKey($channel, $address, $formKey), self::resendCooldownSeconds());
        RateLimiter::hit($this->hourlyAddressKey($channel, $address), 3600);

        if (filled($ipAddress)) {
            RateLimiter::hit($this->hourlyIpKey($channel, (string) $ipAddress), 3600);
        }

        return $record;
    }

    public function verify(
        FormVerificationChannel $channel,
        string $address,
        string $formKey,
        string $guestToken,
        string $code,
    ): FormVerificationResult {
        $record = $this->latestForChallenge($channel, $address, $formKey, $guestToken);

        if (! $record) {
            return FormVerificationResult::NotFound;
        }

        if ($record->consumed_at !== null) {
            return FormVerificationResult::Consumed;
        }

        if ($record->expires_at->isPast()) {
            $record->update(['consumed_at' => now()]);

            return FormVerificationResult::Expired;
        }

        if ($record->attempts >= self::maxAttempts()) {
            $record->update(['consumed_at' => now()]);

            return FormVerificationResult::AttemptsExceeded;
        }

        if (! hash_equals($record->code_hash, $this->hashCode($code))) {
            $record->increment('attempts');

            if ($record->refresh()->attempts >= self::maxAttempts()) {
                $record->update(['consumed_at' => now()]);

                return FormVerificationResult::AttemptsExceeded;
            }

            return FormVerificationResult::Invalid;
        }

        $record->update(['verified_at' => $record->verified_at ?? now()]);

        return FormVerificationResult::Verified;
    }

    public function consume(
        FormVerificationChannel $channel,
        string $address,
        string $formKey,
        string $guestToken,
    ): ?Carbon {
        $record = $this->latestForChallenge($channel, $address, $formKey, $guestToken);

        if (! $record || $record->consumed_at !== null || $record->verified_at === null || $record->expires_at->isPast()) {
            return null;
        }

        $verifiedAt = $record->verified_at;

        $record->update(['consumed_at' => now()]);

        return $verifiedAt;
    }

    public function hashToken(string $guestToken): string
    {
        return hash_hmac('sha256', $guestToken, $this->hashKey());
    }

    public static function expiresAfterMinutes(): int
    {
        return (int) config('forms.otp.expires_minutes');
    }

    public static function maxAttempts(): int
    {
        return (int) config('forms.otp.max_attempts');
    }

    public static function resendCooldownSeconds(): int
    {
        return (int) config('forms.otp.resend_cooldown_seconds');
    }

    private function ensureSendAllowed(
        FormVerificationChannel $channel,
        string $address,
        string $formKey,
        ?string $ipAddress,
    ): void {
        $cooldownKey = $this->cooldownKey($channel, $address, $formKey);

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            throw ValidationException::withMessages([
                'verification' => __('public.forms.verification.cooldown', [
                    'seconds' => RateLimiter::availableIn($cooldownKey),
                ]),
            ]);
        }

        if (RateLimiter::tooManyAttempts($this->hourlyAddressKey($channel, $address), self::ADDRESS_HOURLY_LIMIT)) {
            throw ValidationException::withMessages([
                'verification' => __('public.forms.verification.hourly_limited'),
            ]);
        }

        if (filled($ipAddress) && RateLimiter::tooManyAttempts($this->hourlyIpKey($channel, (string) $ipAddress), self::IP_HOURLY_LIMIT)) {
            throw ValidationException::withMessages([
                'verification' => __('public.forms.verification.hourly_limited'),
            ]);
        }
    }

    private function latestForChallenge(
        FormVerificationChannel $channel,
        string $address,
        string $formKey,
        string $guestToken,
    ): ?FormVerificationCode {
        return FormVerificationCode::query()
            ->forChallenge(
                channel: $channel,
                address: $this->normalizeAddress($channel, $address),
                formKey: $formKey,
                guestTokenHash: $this->hashToken($guestToken),
            )
            ->latest('id')
            ->first();
    }

    private function driver(FormVerificationChannel $channel): FormVerificationChannelDriver
    {
        return match ($channel) {
            FormVerificationChannel::Email => $this->email,
            FormVerificationChannel::Phone => throw new \LogicException('Phone form verification is not implemented yet.'),
        };
    }

    private function normalizeAddress(FormVerificationChannel $channel, string $address): string
    {
        return match ($channel) {
            FormVerificationChannel::Email => Str::of($address)->trim()->lower()->toString(),
            FormVerificationChannel::Phone => trim($address),
        };
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->hashKey());
    }

    private function hashKey(): string
    {
        return (string) (config('app.key') ?: config('app.name'));
    }

    private function cooldownKey(FormVerificationChannel $channel, string $address, string $formKey): string
    {
        return "form-verification:cooldown:{$channel->value}:{$formKey}:".sha1($address);
    }

    private function hourlyAddressKey(FormVerificationChannel $channel, string $address): string
    {
        return "form-verification:hourly-address:{$channel->value}:".sha1($address);
    }

    private function hourlyIpKey(FormVerificationChannel $channel, string $ipAddress): string
    {
        return "form-verification:hourly-ip:{$channel->value}:".sha1($ipAddress);
    }
}
