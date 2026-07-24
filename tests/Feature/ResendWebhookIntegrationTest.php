<?php

use App\Http\Middleware\RequireResendWebhookSecret;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Resend\Laravel\Events\EmailBounced;
use Resend\Laravel\Events\EmailComplained;
use Resend\Laravel\Events\EmailDelivered;
use Resend\Laravel\Events\EmailDeliveryDelayed;
use Resend\Laravel\Events\EmailFailed;
use Resend\Laravel\Events\EmailSent;
use Resend\Laravel\Events\EmailSuppressed;
use Resend\Laravel\Http\Controllers\WebhookController;
use Resend\Laravel\Http\Middleware\VerifyWebhookSignature;
use Resend\Laravel\Transport\ResendTransportFactory;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

dataset('resend operational events', [
    'sent' => ['email.sent', EmailSent::class],
    'delivered' => ['email.delivered', EmailDelivered::class],
    'delivery delayed' => ['email.delivery_delayed', EmailDeliveryDelayed::class],
    'bounced' => ['email.bounced', EmailBounced::class],
    'complained' => ['email.complained', EmailComplained::class],
    'failed' => ['email.failed', EmailFailed::class],
    'suppressed' => ['email.suppressed', EmailSuppressed::class],
]);

dataset('resend excluded events', [
    'clicked engagement' => ['email.clicked'],
    'opened engagement' => ['email.opened'],
    'received inbound' => ['email.received'],
    'contact' => ['contact.created'],
    'domain' => ['domain.created'],
    'scheduled unsupported by stable wrapper' => ['email.scheduled'],
]);

function resendWebhookTestSecret(): string
{
    return 'whsec_'.base64_encode('podtext-resend-webhook-test-secret');
}

function resendWebhookFixture(string $eventType = 'email.delivered'): string
{
    $fixture = file_get_contents(base_path('tests/Fixtures/resend/email-delivered.json'));

    if (! is_string($fixture)) {
        throw new RuntimeException('Unable to read the Resend webhook fixture.');
    }

    if ($eventType === 'email.delivered') {
        return $fixture;
    }

    $payload = json_decode($fixture, true, flags: JSON_THROW_ON_ERROR);
    $payload['type'] = $eventType;

    return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

/**
 * @return array<string, string>
 */
function resendWebhookServerHeaders(
    string $body,
    string $secret,
    ?int $timestamp = null,
    string $messageId = 'msg_resend_webhook_test',
): array {
    $timestamp ??= time();
    $encodedSecret = str_starts_with($secret, 'whsec_')
        ? substr($secret, strlen('whsec_'))
        : $secret;
    $decodedSecret = base64_decode($encodedSecret, true);

    if (! is_string($decodedSecret)) {
        throw new RuntimeException('The Resend test webhook secret must be valid base64.');
    }

    $signedContent = "{$messageId}.{$timestamp}.{$body}";
    $signature = base64_encode(hash_hmac('sha256', $signedContent, $decodedSecret, true));

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_SVIX_ID' => $messageId,
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => "v1,{$signature}",
    ];
}

function resendWebhookLogHandler(): TestHandler
{
    $handler = new TestHandler;
    $logger = Log::channel('resend_webhook')->getLogger();

    if (! $logger instanceof MonologLogger) {
        throw new RuntimeException('The Resend webhook log channel must use Monolog.');
    }

    $logger->setHandlers([$handler]);

    return $handler;
}

it('fails closed before dispatch when the webhook secret is missing or blank', function (?string $configuredSecret): void {
    config(['resend.webhook.secret' => $configuredSecret]);
    Event::fake([EmailDelivered::class]);

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: resendWebhookFixture(),
    );

    $response->assertServiceUnavailable();
    Event::assertNotDispatched(EmailDelivered::class);
})->with([null, '', '   ', '0']);

it('keeps the package route and built-in signature verifier behind the app guard', function (): void {
    config(['resend.webhook.secret' => resendWebhookTestSecret()]);

    $route = Route::getRoutes()->getByName('resend.webhook');
    $matchingRoutes = array_filter(
        Route::getRoutes()->getRoutes(),
        static fn ($candidate): bool => $candidate->uri() === 'resend/webhook'
            && in_array('POST', $candidate->methods(), true),
    );

    expect($matchingRoutes)->toHaveCount(1)
        ->and($route)->not->toBeNull()
        ->and($route->uri())->toBe('resend/webhook')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->getActionName())->toBe(WebhookController::class.'@handleWebhook')
        ->and($route->gatherMiddleware())
        ->toContain(RequireResendWebhookSecret::class, VerifyWebhookSignature::class)
        ->not->toContain('web');
});

it('rejects missing and invalid signatures without logging', function (string $signatureState): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture();
    config(['resend.webhook.secret' => $secret]);
    $handler = resendWebhookLogHandler();

    $server = match ($signatureState) {
        'missing' => [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        'invalid' => [
            ...resendWebhookServerHeaders($body, $secret),
            'HTTP_SVIX_SIGNATURE' => 'v1,'.base64_encode('invalid-signature'),
        ],
        'malformed' => [
            ...resendWebhookServerHeaders($body, $secret),
            'HTTP_SVIX_SIGNATURE' => 'v1',
        ],
    };

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: $server,
        content: $body,
    );

    $response->assertForbidden();
    expect($handler->getRecords())->toBeEmpty();
})->with(['missing', 'invalid', 'malformed']);

it('rejects stale signatures without logging', function (): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture();
    config(['resend.webhook.secret' => $secret]);
    $handler = resendWebhookLogHandler();

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: resendWebhookServerHeaders(
            body: $body,
            secret: $secret,
            timestamp: time() - 301,
        ),
        content: $body,
    );

    $response->assertForbidden();
    expect($handler->getRecords())->toBeEmpty();
});

it('dispatches the package event for a valid raw body signature', function (): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture();
    config(['resend.webhook.secret' => $secret]);
    Event::fake([EmailDelivered::class]);

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: resendWebhookServerHeaders($body, $secret),
        content: $body,
    );

    $response->assertOk();
    Event::assertDispatched(
        EmailDelivered::class,
        fn (EmailDelivered $event): bool => $event->payload['data']['email_id'] === 'email_operational_123',
    );
});

it('logs each approved operational event with the exact metadata allowlist', function (
    string $eventType,
    string $eventClass,
): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture($eventType);
    $messageId = 'msg_'.str_replace('.', '_', $eventType);
    config(['resend.webhook.secret' => $secret]);
    $handler = resendWebhookLogHandler();

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: resendWebhookServerHeaders(
            body: $body,
            secret: $secret,
            messageId: $messageId,
        ),
        content: $body,
    );

    $response->assertOk();

    $records = $handler->getRecords();

    expect(class_exists($eventClass))->toBeTrue()
        ->and($records)->toHaveCount(1)
        ->and($records[0]->level)->toBe(Level::Info)
        ->and($records[0]->message)->toBe('Resend email delivery webhook received.')
        ->and($records[0]->context)->toBe([
            'event_type' => $eventType,
            'svix_id' => $messageId,
            'email_id' => 'email_operational_123',
            'event_created_at' => '2026-07-24T01:02:03.000Z',
        ]);
})->with('resend operational events');

it('returns a server error when the operational log write fails so Resend can retry', function (): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture();
    config(['resend.webhook.secret' => $secret]);

    $logger = Log::channel('resend_webhook')->getLogger();

    if (! $logger instanceof MonologLogger) {
        throw new RuntimeException('The Resend webhook log channel must use Monolog.');
    }

    $logger->setHandlers([
        new class extends AbstractProcessingHandler
        {
            protected function write(LogRecord $record): void
            {
                throw new RuntimeException('Simulated Resend webhook log failure.');
            }
        },
    ]);

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: resendWebhookServerHeaders($body, $secret),
        content: $body,
    );

    $response->assertServerError();
});

it('acknowledges excluded events without writing operational logs', function (string $eventType): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture($eventType);
    config(['resend.webhook.secret' => $secret]);
    $handler = resendWebhookLogHandler();

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: resendWebhookServerHeaders($body, $secret),
        content: $body,
    );

    $response->assertOk();

    expect($handler->getRecords())->toBeEmpty();
})->with('resend excluded events');

it('never includes private payload markers in the operational log record', function (): void {
    $secret = resendWebhookTestSecret();
    $body = resendWebhookFixture();
    config(['resend.webhook.secret' => $secret]);
    $handler = resendWebhookLogHandler();

    $response = $this->call(
        'POST',
        '/resend/webhook',
        server: resendWebhookServerHeaders($body, $secret),
        content: $body,
    );

    $response->assertOk();

    $record = $handler->getRecords()[0];
    $logged = json_encode([
        'message' => $record->message,
        'context' => $record->context,
    ], JSON_THROW_ON_ERROR);

    expect($logged)
        ->not->toContain(
            'sender-private-marker',
            'recipient-private-marker',
            'subject-private-marker',
            'body-private-marker',
            'tag-private-marker',
            'failure-private-marker',
            'private-marker.example.test',
            '203.0.113.42',
            'svix-signature',
            'whsec_',
        );
});

it('configures a dedicated retained webhook log and documents separate credentials', function (): void {
    expect(config('logging.channels.resend_webhook'))->toBe([
        'driver' => 'daily',
        'path' => storage_path('logs/resend-webhook.log'),
        'level' => 'info',
        'days' => 14,
        'replace_placeholders' => true,
    ])->and(file_get_contents(base_path('.env.example')))
        ->toContain(
            'RESEND_API_KEY=',
            'RESEND_WEBHOOK_SECRET=',
        );
});

it('uses the legacy outbound key when the canonical key is blank and resolves the wrapper transport', function (): void {
    config([
        'resend.api_key' => '',
        'services.resend.key' => 're_legacy_test_only',
    ]);
    (new AppServiceProvider($this->app))->register();
    Mail::forgetMailers();

    expect(config('resend.api_key'))->toBe('re_legacy_test_only')
        ->and(Mail::mailer('resend')->getSymfonyTransport())
        ->toBeInstanceOf(ResendTransportFactory::class);
});
