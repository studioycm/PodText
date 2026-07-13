<?php

use App\Enums\FormVerificationChannel;
use App\Enums\FormVerificationResult;
use App\Mail\PublicFormEmailVerificationCodeMail;
use App\Models\FormVerificationCode;
use App\Support\Forms\Verification\FormVerificationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
    Mail::fake();
});

function mail1SendVerificationCode(string $address = 'submitter@example.com', string $token = 'guest-token'): string
{
    app(FormVerificationManager::class)->send(
        channel: FormVerificationChannel::Email,
        address: $address,
        formKey: 'request_transcription',
        formName: 'Request transcription',
        guestToken: $token,
        ipAddress: '127.0.0.1',
        locale: 'he',
    );

    $code = null;

    Mail::assertQueued(PublicFormEmailVerificationCodeMail::class, function (PublicFormEmailVerificationCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return $mail->formName === 'Request transcription'
            && $mail->mailLocale === 'he'
            && preg_match('/^\d{6}$/', $mail->code) === 1
            && $mail->queue === 'default';
    });

    return (string) $code;
}

it('sends email codes and invalidates previous active codes for the same address and form', function (): void {
    mail1SendVerificationCode(token: 'first-token');

    $first = FormVerificationCode::query()->firstOrFail();

    $this->travel(FormVerificationManager::RESEND_COOLDOWN_SECONDS + 1)->seconds();

    mail1SendVerificationCode(token: 'second-token');

    expect(FormVerificationCode::query()->count())->toBe(2)
        ->and($first->refresh()->consumed_at)->not->toBeNull()
        ->and(FormVerificationCode::query()->latest('id')->firstOrFail()->consumed_at)->toBeNull();
});

it('verifies a correct code and consumes it once', function (): void {
    $code = mail1SendVerificationCode();
    $manager = app(FormVerificationManager::class);

    expect($manager->verify(
        FormVerificationChannel::Email,
        ' submitter@example.com ',
        'request_transcription',
        'guest-token',
        $code,
    ))->toBe(FormVerificationResult::Verified);

    $verifiedAt = $manager->consume(
        FormVerificationChannel::Email,
        'submitter@example.com',
        'request_transcription',
        'guest-token',
    );

    expect($verifiedAt)->not->toBeNull()
        ->and($manager->consume(
            FormVerificationChannel::Email,
            'submitter@example.com',
            'request_transcription',
            'guest-token',
        ))->toBeNull();
});

it('kills a code after five incorrect attempts', function (): void {
    mail1SendVerificationCode();
    $manager = app(FormVerificationManager::class);

    foreach (range(1, FormVerificationManager::MAX_ATTEMPTS - 1) as $attempt) {
        expect($manager->verify(
            FormVerificationChannel::Email,
            'submitter@example.com',
            'request_transcription',
            'guest-token',
            '000000',
        ))->toBe(FormVerificationResult::Invalid);
    }

    expect($manager->verify(
        FormVerificationChannel::Email,
        'submitter@example.com',
        'request_transcription',
        'guest-token',
        '000000',
    ))->toBe(FormVerificationResult::AttemptsExceeded)
        ->and(FormVerificationCode::query()->firstOrFail()->refresh()->consumed_at)->not->toBeNull();
});

it('expires old codes and blocks resend during the cooldown window', function (): void {
    mail1SendVerificationCode();

    app(FormVerificationManager::class)->send(
        channel: FormVerificationChannel::Email,
        address: 'another@example.com',
        formKey: 'request_transcription',
        formName: 'Request transcription',
        guestToken: 'guest-token',
        ipAddress: '127.0.0.1',
        locale: 'he',
    );

    expect(fn () => app(FormVerificationManager::class)->send(
        channel: FormVerificationChannel::Email,
        address: 'another@example.com',
        formKey: 'request_transcription',
        formName: 'Request transcription',
        guestToken: 'guest-token',
        ipAddress: '127.0.0.1',
        locale: 'he',
    ))->toThrow(ValidationException::class);

    $this->travel(FormVerificationManager::EXPIRES_AFTER_MINUTES + 1)->minutes();

    expect(app(FormVerificationManager::class)->verify(
        FormVerificationChannel::Email,
        'submitter@example.com',
        'request_transcription',
        'guest-token',
        '111111',
    ))->toBe(FormVerificationResult::Expired);
});
