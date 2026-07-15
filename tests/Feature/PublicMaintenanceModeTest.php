<?php

use App\Enums\FormVerificationChannel;
use App\Livewire\Public\ContentItemBrowser;
use App\Mail\PublicFormEmailVerificationCodeMail;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\PublicFormSubmission;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Maintenance\MaintenanceForm;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['settings.cache.enabled' => true]);

    Cache::flush();
    Http::preventStrayRequests();
    Mail::fake();
    step10rMp1ForgetPublicFrontState();
});

function mail1QueuedMaintenanceCode(): string
{
    $code = null;

    Mail::assertQueued(PublicFormEmailVerificationCodeMail::class, function (PublicFormEmailVerificationCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return $mail->formName === 'Maintenance contact'
            && preg_match('/^\d{6}$/', $mail->code) === 1;
    });

    return (string) $code;
}

function step10rMp1ForgetPublicFrontState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function step10rMp1SaveMaintenance(array $overrides): void
{
    $settings = app(PublicContentSettings::class);
    $settings->maintenance = [
        ...PublicFrontConfigRegistry::defaults()['maintenance'],
        ...$overrides,
    ];
    $settings->save();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function step10rMp2FormDefinition(array $overrides = []): array
{
    return [
        'key' => 'maintenance_contact',
        'name' => 'Maintenance contact',
        'heading' => 'Contact during maintenance',
        'description' => 'Send a short maintenance message.',
        'submit_label' => 'Send',
        'success_message' => 'Maintenance message received.',
        'enabled' => true,
        'display_mode_default' => 'modal',
        'settings' => [
            'rate_limit_attempts' => 5,
            'rate_limit_decay_seconds' => 600,
        ],
        'fields' => [
            [
                'key' => 'email',
                'type' => 'email',
                'label' => 'Email',
                'required' => true,
            ],
            [
                'key' => 'message',
                'type' => 'textarea',
                'label' => 'Message',
                'required' => true,
                'max_length' => 500,
            ],
        ],
        ...$overrides,
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $definitions
 */
function step10rMp2SavePublicForms(array $definitions): void
{
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = ['definitions' => $definitions];
    $settings->save();

    step10rMp1ForgetPublicFrontState();
}

function step10rMp1PublicContent(): array
{
    $group = ContentGroup::factory()->published()->create([
        'slug' => 'mp1-podcast',
        'title' => 'MP1 Podcast',
    ]);
    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->withTranscription()
        ->create([
            'slug' => 'mp1-episode',
            'title' => 'MP1 Public Episode',
        ]);

    return [$group, $item];
}

it('serves public urls as maintenance responses with retry-after when enabled', function (): void {
    [$group, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'title' => 'אתר בהכנה',
        'rich_html' => '<p data-maintenance-marker="mp1">MP1 maintenance marker</p>',
        'retry_after_hours' => 6,
    ]);

    foreach ([
        '/',
        '/search',
        '/podcasts',
        "/podcasts/{$group->slug}",
        "/items/{$group->slug}/{$item->slug}",
    ] as $uri) {
        $this->get($uri)
            ->assertStatus(503)
            ->assertHeader('Retry-After', '21600')
            ->assertSee('lang="he"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('<meta charset="utf-8">', false)
            ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1">', false)
            ->assertSee('data-maintenance-marker="mp1"', false)
            ->assertSee('MP1 maintenance marker')
            ->assertDontSee($item->title);
    }
});

it('lets admin users bypass maintenance while admin routes remain reachable for guests', function (): void {
    [$group, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'rich_html' => '<p data-maintenance-marker="mp1">Hidden from admins</p>',
    ]);

    $this->get('/admin')
        ->assertRedirect('/admin/login');

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('data-maintenance-marker="mp1"', false);

    $this->actingAs(User::factory()->create())
        ->get("/items/{$group->slug}/{$item->slug}")
        ->assertOk()
        ->assertSee($item->title)
        ->assertDontSee('data-maintenance-marker="mp1"', false);
});

it('lets admin users bypass maintenance during a livewire interaction', function (): void {
    [$group, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'rich_html' => '<p data-maintenance-marker="mp1-livewire">Hidden from admin Livewire requests</p>',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('search', 'MP1')
        ->assertSee($item->title)
        ->assertDontSee('data-maintenance-marker="mp1-livewire"', false);
});

it('leaves public routes normal when maintenance is disabled', function (): void {
    [, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => false,
        'rich_html' => '<p data-maintenance-marker="mp1">Disabled marker</p>',
    ]);

    foreach (['/', '/search', '/podcasts'] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertDontSee('data-maintenance-marker="mp1"', false);
    }

    $this->get('/')
        ->assertSee($item->title);
});

it('renders raw html override verbatim instead of the maintenance shell', function (): void {
    $rawHtml = '<!doctype html><html><body><main data-raw-maintenance="mp1"><script>window.mp1 = true;</script>Raw override</main></body></html>';

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'title' => 'Ignored title',
        'rich_html' => '<p data-maintenance-marker="mp1">Ignored rich content</p>',
        'raw_html_override' => $rawHtml,
    ]);

    $response = $this->get('/search')
        ->assertStatus(503)
        ->assertSee('<!doctype html>', false)
        ->assertSee('data-raw-maintenance="mp1"', false)
        ->assertSee('<script>window.mp1 = true;</script>', false)
        ->assertDontSee('data-maintenance-content', false)
        ->assertDontSee('Ignored rich content');

    expect(trim($response->getContent()))->toBe($rawHtml);
});

it('renders a configured plain maintenance form and stores submissions', function (): void {
    step10rMp2SavePublicForms([
        step10rMp2FormDefinition(),
    ]);
    step10rMp1SaveMaintenance([
        'enabled' => true,
        'title' => 'Maintenance form shell',
        'form_key' => 'maintenance_contact',
        'form_location' => MaintenanceForm::LOCATION_RENDERED_PAGE,
        'form_position' => MaintenanceForm::POSITION_AFTER_CONTENT,
    ]);

    $this->get('/search')
        ->assertStatus(503)
        ->assertSee('Maintenance form shell')
        ->assertSee('data-maintenance-form', false)
        ->assertSee('data-form-key="maintenance_contact"', false)
        ->assertSee('action="'.route('public.maintenance-form.submit').'"', false)
        ->assertSee('Contact during maintenance');

    $this->post(route('public.maintenance-form.submit'), [
        'source_url' => 'https://example.com/requested-page',
        'data' => [
            'email' => 'visitor@example.com',
            'message' => 'Please notify me.',
        ],
    ])
        ->assertStatus(503)
        ->assertSee('data-maintenance-form-success', false)
        ->assertSee('Maintenance message received.');

    $submission = PublicFormSubmission::query()->firstOrFail();

    expect($submission->form_key)->toBe('maintenance_contact')
        ->and($submission->payload)->toBe([
            'email' => 'visitor@example.com',
            'message' => 'Please notify me.',
        ])
        ->and($submission->source_url)->toBe('https://example.com/requested-page')
        ->and($submission->metadata)->toMatchArray([
            'display_mode' => 'maintenance_plain',
            'maintenance_form_location' => MaintenanceForm::LOCATION_RENDERED_PAGE,
            'maintenance_form_position' => MaintenanceForm::POSITION_AFTER_CONTENT,
        ]);
});

it('returns the maintenance page with inline validation errors for invalid form submissions', function (): void {
    step10rMp2SavePublicForms([
        step10rMp2FormDefinition(),
    ]);
    step10rMp1SaveMaintenance([
        'enabled' => true,
        'form_key' => 'maintenance_contact',
    ]);

    $this->post(route('public.maintenance-form.submit'), [
        'data' => [
            'email' => 'not-an-email',
            'message' => '',
        ],
    ])
        ->assertStatus(503)
        ->assertSee('data-maintenance-form-field-error="email"', false)
        ->assertSee('data-maintenance-form-field-error="message"', false);

    expect(PublicFormSubmission::query()->count())->toBe(0);
});

it('enforces email otp verification on the maintenance plain post form flow', function (): void {
    config(['forms.otp.expires_minutes' => 7]);

    step10rMp2SavePublicForms([
        step10rMp2FormDefinition([
            'settings' => [
                'rate_limit_attempts' => 5,
                'rate_limit_decay_seconds' => 600,
                'submitter_email_verification' => 'email_otp',
            ],
        ]),
    ]);
    step10rMp1SaveMaintenance([
        'enabled' => true,
        'form_key' => 'maintenance_contact',
    ]);

    $payload = [
        'source_url' => 'https://example.com/requested-page',
        'form_key' => 'maintenance_contact',
        'data' => [
            'email' => 'visitor@example.com',
            'message' => 'Please notify me.',
        ],
    ];

    $this->post(route('public.maintenance-form.submit'), $payload)
        ->assertStatus(503)
        ->assertSee(__('public.forms.verification.signed_token_invalid'));

    expect(PublicFormSubmission::query()->count())->toBe(0);

    $sendCodeResponse = $this->post(route('public.maintenance-form.send-code'), $payload)
        ->assertStatus(503)
        ->assertSee(__('public.forms.verification.sent'))
        ->assertSee('הקוד תקף ל-7 דקות.')
        ->assertSee('name="verification_token"', false)
        ->assertSee('.podtext-maintenance-form__input-action {', false)
        ->assertSee('flex-direction: row;', false)
        ->assertDontSee('flex-direction: row-reverse;', false)
        ->assertSee('data-suffix-position="inline-end"', false)
        ->assertSeeInOrder([
            'data-maintenance-form-email-verification-group',
            'data-maintenance-form-email',
            'data-maintenance-form-send-code',
            'data-maintenance-form-verification',
            'data-maintenance-form-code',
            'data-maintenance-form-code-expiry-hint',
            'name="data[message]"',
        ], false);

    $code = mail1QueuedMaintenanceCode();
    $html = $sendCodeResponse->getContent();

    preg_match('/<form method="POST" action="([^"]+)"/', $html, $matches);

    $signedAction = html_entity_decode($matches[1] ?? '');

    expect($signedAction)->not->toBe('');

    $this->post($signedAction, [
        ...$payload,
        'verification_code' => $code,
    ])
        ->assertStatus(503)
        ->assertSee('data-maintenance-form-success', false);

    $submission = PublicFormSubmission::query()->firstOrFail();

    expect($submission->verification_channel)->toBe(FormVerificationChannel::Email->value)
        ->and($submission->verification_verified_at)->not->toBeNull();
});

it('injects the maintenance form at the raw html marker and falls back when the marker is missing', function (): void {
    step10rMp2SavePublicForms([
        step10rMp2FormDefinition(),
    ]);

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'form_key' => 'maintenance_contact',
        'form_location' => MaintenanceForm::LOCATION_RAW_HTML,
        'raw_html_override' => '<!doctype html><main>Before '.MaintenanceForm::MARKER.' After</main>',
    ]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('Before', false)
        ->assertSee('data-maintenance-form', false)
        ->assertSee('After', false)
        ->assertDontSee('data-podtext-maintenance-form-fallback-container', false)
        ->assertDontSee(MaintenanceForm::MARKER, false);

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'form_key' => 'maintenance_contact',
        'form_location' => MaintenanceForm::LOCATION_RAW_HTML,
        'raw_html_override' => '<!doctype html><main>No marker</main>',
    ]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('data-podtext-maintenance-form-fallback-container', false)
        ->assertSee('data-podtext-maintenance-form-marker-missing', false)
        ->assertSee('data-maintenance-form', false);
});

it('keeps the maintenance form submission route unavailable when maintenance or the form is disabled', function (): void {
    step10rMp2SavePublicForms([
        step10rMp2FormDefinition(),
    ]);
    step10rMp1SaveMaintenance([
        'enabled' => false,
        'form_key' => 'maintenance_contact',
    ]);

    $this->post(route('public.maintenance-form.submit'))
        ->assertNotFound();

    step10rMp2SavePublicForms([
        step10rMp2FormDefinition(['enabled' => false]),
    ]);
    step10rMp1SaveMaintenance([
        'enabled' => true,
        'form_key' => 'maintenance_contact',
    ]);

    $this->post(route('public.maintenance-form.submit'))
        ->assertNotFound();
});

it('renders stale csrf maintenance form errors without exposing the live site', function (): void {
    step10rMp2SavePublicForms([
        step10rMp2FormDefinition(),
    ]);
    step10rMp1SaveMaintenance([
        'enabled' => true,
        'form_key' => 'maintenance_contact',
    ]);

    $request = Request::create('/maintenance/form', 'POST', [
        'source_url' => 'https://example.com/stale',
        'data' => [
            'email' => 'visitor@example.com',
            'message' => 'Still here.',
        ],
    ]);
    $route = Route::getRoutes()->getByName('public.maintenance-form.submit');

    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getContent())->toContain(__('public.maintenance_form.csrf_retry'))
        ->and($response->getContent())->toContain('data-maintenance-form-error')
        ->and(PublicFormSubmission::query()->count())->toBe(0);
});

it('falls back to translated maintenance content when no content is configured', function (): void {
    step10rMp1SaveMaintenance(['enabled' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee(__('public.maintenance.title'))
        ->assertSee(__('public.maintenance.body'));
});

it('keeps trusted maintenance html byte-identical during validation', function (): void {
    $title = "  <span>Title</span>\n ";
    $richHtml = '<p data-x="1">שלום <script>alert("rich")</script></p>';
    $rawHtml = "<!doctype html>\n<html><body><script>alert('raw')</script></body></html>";

    $maintenance = app(PublicFrontConfigReader::class)->fromArray([
        'maintenance' => [
            'enabled' => true,
            'title' => $title,
            'rich_html' => $richHtml,
            'raw_html_override' => $rawHtml,
            'retry_after_hours' => 48,
        ],
    ])->group('maintenance');

    expect($maintenance['title'])->toBe($title)
        ->and($maintenance['rich_html'])->toBe($richHtml)
        ->and($maintenance['raw_html_override'])->toBe($rawHtml)
        ->and($maintenance['retry_after_hours'])->toBe(48);
});

it('invalidates cached config immediately when the toggle is saved', function (): void {
    step10rMp1SaveMaintenance(['enabled' => false]);

    expect(app(PublicFrontConfigReader::class)->group('maintenance')['enabled'])->toBeFalse();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'rich_html' => '<p data-maintenance-marker="mp1-cache">Fresh maintenance</p>',
    ]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('data-maintenance-marker="mp1-cache"', false);
});
