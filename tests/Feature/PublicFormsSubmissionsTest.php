<?php

use App\Enums\PublicFormSubmissionStatus;
use App\Filament\Pages\ManagePublicForms;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\PublicFormSubmissions\Pages\ListPublicFormSubmissions;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Livewire\Public\PublicFormModal;
use App\Models\PublicFormSubmission;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Forms\PublicFormSubmissionPresenter;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Settings\SettingsItemCloner;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Cache::flush();
    RateLimiter::clear('public-form:request_transcription:test');
    Mail::fake();
});

function clearStep6PublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep6PublicFrontConfig(array $config): void
{
    foreach ($config as $key => $value) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $key,
            ],
            [
                'locked' => false,
                'payload' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    clearStep6PublicFrontSettingsCache();
}

function step6PublicFormsConfig(array $overrides = []): array
{
    return [
        'public_forms' => [
            'definitions' => [
                [
                    'key' => 'request_transcription',
                    'name' => 'Request transcription',
                    'heading' => 'Request a transcription',
                    'description' => 'Send a safe public request.',
                    'submit_label' => 'Send request',
                    'success_message' => 'Request received.',
                    'enabled' => true,
                    'display_mode_default' => 'modal',
                    'settings' => [
                        'rate_limit_attempts' => 5,
                        'rate_limit_decay_seconds' => 600,
                    ],
                    'fields' => [
                        [
                            'key' => 'name',
                            'type' => 'text',
                            'label' => 'Name',
                            'required' => true,
                            'max_length' => 80,
                        ],
                        [
                            'key' => 'email',
                            'type' => 'email',
                            'label' => 'Email',
                            'required' => true,
                        ],
                        [
                            'key' => 'source_url',
                            'type' => 'url',
                            'label' => 'Source URL',
                            'required' => false,
                        ],
                        [
                            'key' => 'topic',
                            'type' => 'select',
                            'label' => 'Topic',
                            'required' => true,
                            'options' => [
                                [
                                    'value' => 'podcast',
                                    'label' => 'Podcast',
                                ],
                                [
                                    'value' => 'lecture',
                                    'label' => 'Lecture',
                                ],
                            ],
                        ],
                    ],
                    ...$overrides,
                ],
            ],
        ],
    ];
}

it('normalizes valid public form definitions through the public front validator', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate(step6PublicFormsConfig());
    $definition = $result->group('public_forms')['definitions'][0];

    expect($result->hasInvalidConfig())->toBeFalse()
        ->and($definition['key'])->toBe('request_transcription')
        ->and($definition['enabled'])->toBeTrue()
        ->and($definition['display_mode_default'])->toBe('modal')
        ->and($definition['fields'])->toHaveCount(4)
        ->and($definition['settings'])->toBe([
            'rate_limit_attempts' => 5,
            'rate_limit_decay_seconds' => 600,
        ]);
});

it('reports invalid field types unsafe config values and file upload fields', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'public_forms' => [
            'definitions' => [
                [
                    'key' => 'unsafe_form',
                    'name' => 'Unsafe Form',
                    'enabled' => true,
                    'display_mode_default' => 'drawer',
                    'fields' => [
                        [
                            'key' => 'bad_type',
                            'type' => 'file',
                            'label' => 'Upload',
                        ],
                        [
                            'key' => 'bad_label',
                            'type' => 'text',
                            'label' => '<script>alert(1)</script>',
                        ],
                        [
                            'key' => 'bad_help',
                            'type' => 'text',
                            'label' => 'Bad help',
                            'help_text' => 'text-red-500',
                        ],
                        [
                            'key' => 'bad_semantic',
                            'type' => 'text',
                            'label' => 'Bad semantic',
                            'validation_semantics' => 'App\\Rules\\UnsafeRule::class',
                        ],
                        [
                            'key' => 'bad_options',
                            'type' => 'select',
                            'label' => 'Bad options',
                            'options' => [
                                [
                                    'value' => 'safe',
                                    'label' => '<iframe src="https://example.com"></iframe>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())->map(fn ($invalidConfig): string => $invalidConfig->path)->all();
    $definition = $result->group('public_forms')['definitions'][0];

    expect($paths)->toContain('public_forms.definitions.0.display_mode_default')
        ->and($paths)->toContain('public_forms.definitions.0.fields.0.type')
        ->and($paths)->toContain('public_forms.definitions.0.fields.1.label')
        ->and($paths)->toContain('public_forms.definitions.0.fields.2.help_text')
        ->and($paths)->toContain('public_forms.definitions.0.fields.3.validation_semantics')
        ->and($paths)->toContain('public_forms.definitions.0.fields.4.options.0.label')
        ->and($definition['enabled'])->toBeTrue()
        ->and($definition['fields'])->toHaveCount(2)
        ->and(collect($definition['fields'])->pluck('key')->all())->toBe(['bad_help', 'bad_semantic'])
        ->and(class_exists('App\\Models\\PublicFormDefinition'))->toBeFalse();
});

it('saves public form definitions through the forms management page as JSON settings', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManagePublicForms::class)
        ->set('data.public_forms.definitions', [
            [
                'key' => 'volunteer',
                'name' => 'Volunteer',
                'heading' => 'Volunteer to transcribe',
                'description' => 'No uploads or emails in v1.',
                'submit_label' => 'Volunteer',
                'success_message' => 'Thanks.',
                'enabled' => true,
                'display_mode_default' => 'slide_over',
                'settings' => [
                    'rate_limit_attempts' => 3,
                    'rate_limit_decay_seconds' => 600,
                ],
                'fields' => [
                    [
                        'type' => 'textarea',
                        'data' => [
                            'key' => 'message',
                            'label' => 'Message',
                            'required' => true,
                            'max_length' => 500,
                        ],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep6PublicFrontSettingsCache();

    $definition = app(PublicFrontConfigReader::class)
        ->read()
        ->group('public_forms')['definitions'][0];

    expect($definition['key'])->toBe('volunteer')
        ->and($definition['display_mode_default'])->toBe('slide_over')
        ->and($definition['fields'][0]['type'])->toBe('textarea');
});

it('does not clobber public form definitions when saving unrelated public content settings', function (): void {
    saveStep6PublicFrontConfig(step6PublicFormsConfig());

    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.homepage_item_limit', 33)
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep6PublicFrontSettingsCache();

    $definition = app(PublicFrontConfigReader::class)
        ->read()
        ->group('public_forms')['definitions'][0];

    expect($definition['key'])->toBe('request_transcription')
        ->and($definition['fields'])->toHaveCount(4)
        ->and(app(PublicContentSettings::class)->homepage_item_limit)->toBe(33);
});

it('clones public form definitions with a disabled unique key', function (): void {
    $cloned = app(SettingsItemCloner::class)->clone(
        item: [
            'key' => 'request_transcription',
            'name' => 'Request transcription',
            'enabled' => true,
            'fields' => [
                [
                    'key' => 'message',
                    'type' => 'textarea',
                ],
            ],
        ],
        collection: [
            [
                'key' => 'request_transcription',
                'name' => 'Request transcription',
            ],
            [
                'key' => 'request_transcription_2',
                'name' => 'Request transcription Copy',
            ],
        ],
        copySuffix: 'Copy',
        overrides: ['enabled' => false],
    );

    expect($cloned['key'])->toBe('request_transcription_3')
        ->and($cloned['name'])->toBe('Request transcription Copy')
        ->and($cloned['enabled'])->toBeFalse()
        ->and($cloned['fields'][0]['key'])->toBe('message');
});

it('does not render or submit disabled public forms', function (): void {
    saveStep6PublicFrontConfig(step6PublicFormsConfig(['enabled' => false]));

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->assertDontSee('Request transcription')
        ->call('submit')
        ->assertHasErrors(['form']);

    expect(PublicFormSubmission::query()->count())->toBe(0);
});

it('submits enabled forms and stores only configured escaped payload fields', function (): void {
    saveStep6PublicFrontConfig(step6PublicFormsConfig());

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->set('data.name', '<script>alert(1)</script>')
        ->set('data.email', 'submitter@example.com')
        ->set('data.source_url', 'https://example.com/source')
        ->set('data.topic', 'podcast')
        ->set('data.unconfigured', 'ignored')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Request received.');

    $submission = PublicFormSubmission::query()->firstOrFail();

    expect($submission->form_key)->toBe('request_transcription')
        ->and($submission->form_name_snapshot)->toBe('Request transcription')
        ->and($submission->status)->toBe(PublicFormSubmissionStatus::New)
        ->and($submission->payload)->toMatchArray([
            'name' => '&lt;script&gt;alert(1)&lt;/script&gt;',
            'email' => 'submitter@example.com',
            'source_url' => 'https://example.com/source',
            'topic' => 'podcast',
        ])
        ->and($submission->submitter_ip_hash)->toBeString()
        ->and($submission->user_agent_hash)->toBeString()
        ->and($submission->metadata)->toBe(['display_mode' => 'modal']);

    Mail::assertNothingSent();
});

it('validates required email url and select fields before creating submissions', function (): void {
    saveStep6PublicFrontConfig(step6PublicFormsConfig());

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->call('submit')
        ->assertHasErrors(['data.name', 'data.email', 'data.topic']);

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->set('data.name', 'Submitter')
        ->set('data.email', 'not-an-email')
        ->set('data.source_url', 'javascript:alert(1)')
        ->set('data.topic', 'unknown')
        ->call('submit')
        ->assertHasErrors(['data.email', 'data.source_url', 'data.topic']);

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->set('data.name', 'Submitter')
        ->set('data.email', 'submitter@example.com')
        ->set('data.source_url', 'not-a-url')
        ->set('data.topic', 'lecture')
        ->call('submit')
        ->assertHasErrors(['data.source_url']);

    expect(PublicFormSubmission::query()->count())->toBe(0);
});

it('blocks honeypot submissions', function (): void {
    saveStep6PublicFrontConfig(step6PublicFormsConfig());

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->set('data.name', 'Submitter')
        ->set('data.email', 'submitter@example.com')
        ->set('data.topic', 'podcast')
        ->set('honeypot', 'filled by bot')
        ->call('submit')
        ->assertHasErrors(['form']);

    expect(PublicFormSubmission::query()->count())->toBe(0);
});

it('rate limits repeated submissions by form and request fingerprint', function (): void {
    saveStep6PublicFrontConfig(step6PublicFormsConfig([
        'settings' => [
            'rate_limit_attempts' => 1,
            'rate_limit_decay_seconds' => 600,
        ],
    ]));

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->set('data.name', 'First')
        ->set('data.email', 'first@example.com')
        ->set('data.topic', 'podcast')
        ->call('submit')
        ->assertHasNoErrors();

    Livewire::test(PublicFormModal::class, ['formKey' => 'request_transcription'])
        ->set('data.name', 'Second')
        ->set('data.email', 'second@example.com')
        ->set('data.topic', 'lecture')
        ->call('submit')
        ->assertHasErrors(['form']);

    expect(PublicFormSubmission::query()->count())->toBe(1);
});

it('lists submissions safely and manages review statuses in the admin resource', function (): void {
    $this->actingAs(User::factory()->create());

    $submission = PublicFormSubmission::factory()->create([
        'form_key' => 'request_transcription',
        'form_name_snapshot' => 'Request transcription',
        'payload' => [
            'message' => '<script>alert(1)</script>',
        ],
    ]);

    Livewire::test(ListPublicFormSubmissions::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$submission])
        ->assertSee('Request transcription')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->callAction(TestAction::make('markReviewed')->table($submission));

    expect($submission->refresh()->status)->toBe(PublicFormSubmissionStatus::Reviewed);

    Livewire::test(ListPublicFormSubmissions::class)
        ->callAction(TestAction::make('archive')->table($submission));

    expect($submission->refresh()->status)->toBe(PublicFormSubmissionStatus::Archived)
        ->and(app(PublicFormSubmissionPresenter::class)->plainTextPayload($submission))
        ->toContain('message: <script>alert(1)</script>');
});

it('protects public form submissions resource from guests', function (): void {
    $this->get(PublicFormSubmissionResource::getUrl('index'))
        ->assertRedirect('/admin/login');
});

it('keeps public form definitions as settings rather than models', function (): void {
    expect(class_exists('App\\Models\\PublicFormDefinition'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('public_form_definitions'))->toBeFalse();
});
