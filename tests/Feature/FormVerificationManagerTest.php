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

it('uses the documented otp policy config defaults', function (): void {
    expect(config('forms.otp'))->toBe([
        'expires_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ])->and(file_get_contents(base_path('.env.example')))->toContain(
        'FORMS_OTP_EXPIRES_MINUTES=5',
        'FORMS_OTP_MAX_ATTEMPTS=5',
        'FORMS_OTP_RESEND_COOLDOWN_SECONDS=60',
    );
});

it('sends email codes and invalidates previous active codes for the same address and form', function (): void {
    config(['forms.otp.resend_cooldown_seconds' => 2]);

    mail1SendVerificationCode(token: 'first-token');

    $first = FormVerificationCode::query()->firstOrFail();

    $this->travel(FormVerificationManager::resendCooldownSeconds() + 1)->seconds();

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

it('kills a code after the configured number of incorrect attempts', function (): void {
    config(['forms.otp.max_attempts' => 2]);

    mail1SendVerificationCode();
    $manager = app(FormVerificationManager::class);

    foreach (range(1, FormVerificationManager::maxAttempts() - 1) as $attempt) {
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
    config(['forms.otp.expires_minutes' => 1]);
    $this->travelTo(now()->startOfSecond());

    mail1SendVerificationCode();

    expect(FormVerificationCode::query()->firstOrFail()->expires_at->equalTo(
        now()->addMinutes(FormVerificationManager::expiresAfterMinutes()),
    ))->toBeTrue();

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

    $this->travel(FormVerificationManager::expiresAfterMinutes() + 1)->minutes();

    expect(app(FormVerificationManager::class)->verify(
        FormVerificationChannel::Email,
        'submitter@example.com',
        'request_transcription',
        'guest-token',
        '111111',
    ))->toBe(FormVerificationResult::Expired);
});

it('renders singular and plural expiry copy in queued email content', function (): void {
    config(['forms.otp.expires_minutes' => 1]);
    app()->setLocale('he');

    (new PublicFormEmailVerificationCodeMail(
        code: '123456',
        formName: 'טופס בדיקה',
        mailLocale: 'he',
    ))->assertSeeInHtml('הקוד תקף לדקה אחת.');

    config(['forms.otp.expires_minutes' => 5]);
    app()->setLocale('en');

    (new PublicFormEmailVerificationCodeMail(
        code: '123456',
        formName: 'Test form',
        mailLocale: 'en',
    ))->assertSeeInHtml('This code expires in 5 minutes.');
});
