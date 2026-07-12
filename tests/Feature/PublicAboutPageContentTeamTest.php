<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\About\PublicAboutPageRegistry;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
});

function clearStep7PublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep7PublicFrontConfig(array $config): void
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

    clearStep7PublicFrontSettingsCache();
}

function step7PublicFormsConfig(): array
{
    return [
        'definitions' => [
            [
                'key' => 'request_transcription',
                'name' => 'Request transcription',
                'heading' => 'Request a transcription',
                'submit_label' => 'Send request',
                'success_message' => 'Request received.',
                'enabled' => true,
                'display_mode_default' => 'modal',
                'fields' => [
                    [
                        'key' => 'name',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                    ],
                ],
                'settings' => [
                    'rate_limit_attempts' => 5,
                    'rate_limit_decay_seconds' => 600,
                ],
            ],
            [
                'key' => 'disabled_form',
                'name' => 'Disabled form',
                'enabled' => false,
                'display_mode_default' => 'modal',
                'fields' => [],
                'settings' => [
                    'rate_limit_attempts' => 5,
                    'rate_limit_decay_seconds' => 600,
                ],
            ],
        ],
    ];
}

function step7RichContentDoc(string $text = 'Rich safe text'): array
{
    return [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ],
        ],
    ];
}

function step7AboutConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'enabled' => true,
        'title' => 'מי אנחנו',
        'kicker' => 'על PodText',
        'description' => 'תיאור בטוח.',
        'settings' => [
            'team_heading' => 'הצוות',
            'team_description' => 'אנשים שמחזיקים את הפרויקט.',
            'team_layout' => 'grid',
        ],
        'blocks' => [
            [
                'key' => 'intro',
                'type' => 'markdown',
                'visible' => true,
                'sort' => 20,
                'heading' => 'פתיחה',
                'content' => 'Markdown **bold** <script>alert(1)</script> [bad](javascript:alert(1))',
            ],
            [
                'key' => 'rich',
                'type' => 'rich_content',
                'visible' => true,
                'sort' => 30,
                'heading' => 'תוכן עשיר',
                'rich_content' => step7RichContentDoc('Rich safe text <script>alert(1)</script>'),
            ],
            [
                'key' => 'hero_image',
                'type' => 'image',
                'visible' => true,
                'sort' => 40,
                'image_path' => 'about/hero.webp',
                'image_alt' => 'Hero image',
                'body' => 'Image caption',
            ],
            [
                'key' => 'request',
                'type' => 'form_cta',
                'visible' => true,
                'sort' => 50,
                'heading' => 'Need help?',
                'body' => 'Open a safe public form.',
                'form_key' => 'request_transcription',
                'display_mode' => 'modal',
                'button_label' => 'Open form',
            ],
            [
                'key' => 'disabled_request',
                'type' => 'form_cta',
                'visible' => true,
                'sort' => 60,
                'form_key' => 'disabled_form',
                'button_label' => 'Hidden disabled',
            ],
            [
                'key' => 'missing_request',
                'type' => 'form_cta',
                'visible' => true,
                'sort' => 70,
                'form_key' => 'missing_form',
                'button_label' => 'Hidden missing',
            ],
            [
                'key' => 'team',
                'type' => 'team_section',
                'visible' => true,
                'sort' => 80,
                'heading' => 'Our team',
            ],
        ],
        'team_profiles' => [
            [
                'key' => 'alice',
                'visible' => true,
                'sort' => 20,
                'image_path' => 'team/alice.png',
                'name' => 'Alice Admin',
                'title' => 'Editor',
                'description' => 'Keeps the archive tidy.',
            ],
            [
                'key' => 'bob',
                'visible' => true,
                'sort' => 10,
                'image_path' => 'team/bob.webp',
                'name' => 'Bob Builder',
                'title' => 'Transcriber',
                'description' => 'Builds transcript workflows.',
            ],
            [
                'key' => 'eve',
                'visible' => false,
                'sort' => 30,
                'image_path' => 'team/eve.jpg',
                'name' => 'Eve Hidden',
                'title' => 'Hidden',
                'description' => 'Not public.',
            ],
        ],
    ], $overrides);
}

it('normalizes valid about page content blocks and team profiles', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'about_page' => [
            ...step7AboutConfig(),
            'blocks' => [
                [
                    'key' => 'late',
                    'type' => 'heading',
                    'visible' => true,
                    'sort' => 30,
                    'heading' => 'Late heading',
                ],
                [
                    'key' => 'early',
                    'type' => 'callout',
                    'visible' => true,
                    'sort' => 10,
                    'heading' => 'Early callout',
                    'content' => 'Safe **Markdown**',
                    'style' => 'accent',
                ],
                [
                    'key' => 'rich',
                    'type' => 'rich_content',
                    'visible' => true,
                    'sort' => 20,
                    'rich_content' => step7RichContentDoc(),
                ],
            ],
            'team_profiles' => [
                [
                    'key' => 'second',
                    'visible' => true,
                    'sort' => 20,
                    'image_path' => 'team/second.jpg',
                    'name' => 'Second Person',
                    'title' => 'Second',
                ],
                [
                    'key' => 'first',
                    'visible' => true,
                    'sort' => 10,
                    'image_path' => 'team/first.webp',
                    'name' => 'First Person',
                    'description' => 'First bio',
                ],
            ],
        ],
    ]);

    $aboutPage = $result->group('about_page');

    expect($result->hasInvalidConfig())->toBeFalse()
        ->and($aboutPage['enabled'])->toBeTrue()
        ->and($aboutPage['title'])->toBe('מי אנחנו')
        ->and(collect($aboutPage['blocks'])->pluck('key')->all())->toBe(['early', 'rich', 'late'])
        ->and($aboutPage['blocks'][0])->toMatchArray([
            'type' => 'callout',
            'style' => 'accent',
            'body' => 'Safe **Markdown**',
        ])
        ->and(collect($aboutPage['team_profiles'])->pluck('key')->all())->toBe(['first', 'second'])
        ->and($aboutPage['team_profiles'][0]['image_path'])->toBe('team/first.webp');
});

it('reports invalid about blocks team profiles and unsafe semantic values', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'about_page' => [
            'enabled' => true,
            'title' => '<script>alert(1)</script>',
            'description' => 'resources/views/public/about.blade.php',
            'settings' => [
                'team_layout' => 'grid grid-cols-2',
            ],
            'blocks' => [
                [
                    'key' => 'bad_type',
                    'type' => 'raw_html',
                    'heading' => 'Raw',
                ],
                [
                    'key' => 'bad_image',
                    'type' => 'image',
                    'image_path' => '../secret.png',
                ],
                [
                    'key' => 'bad_form',
                    'type' => 'form_cta',
                    'form_key' => 'App\\Forms\\UnsafeForm::class',
                ],
                [
                    'key' => 'bad_style',
                    'type' => 'heading',
                    'style' => 'text-red-500',
                    'heading' => 'Still safe',
                ],
                [
                    'key' => 'bad_rich',
                    'type' => 'rich_content',
                    'rich_content' => [
                        'type' => 'doc',
                        'content' => [
                            [
                                'type' => 'iframe',
                                'attrs' => [
                                    'src' => 'javascript:alert(1)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'team_profiles' => [
                [
                    'key' => 'missing_name',
                    'visible' => true,
                    'sort' => 10,
                ],
                [
                    'key' => 'unsafe_description',
                    'visible' => true,
                    'sort' => 20,
                    'name' => 'Safe Name',
                    'description' => 'text-red-500',
                    'image_path' => 'avatars/safe.png',
                    'links' => [
                        'https://example.com',
                    ],
                ],
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())->map(fn ($invalidConfig): string => $invalidConfig->path);
    $aboutPage = $result->group('about_page');

    expect($paths)->toContain('about_page.title')
        ->and($paths)->toContain('about_page.description')
        ->and($paths)->toContain('about_page.settings.team_layout')
        ->and($paths)->toContain('about_page.blocks.0.type')
        ->and($paths)->toContain('about_page.blocks.1.image_path')
        ->and($paths)->toContain('about_page.blocks.2.form_key')
        ->and($paths)->toContain('about_page.blocks.3.style')
        ->and($paths)->toContain('about_page.blocks.4.rich_content.content.0.type')
        ->and($paths)->toContain('about_page.blocks.4.rich_content.content.0.attrs.src')
        ->and($paths)->toContain('about_page.team_profiles.0.name')
        ->and($paths)->toContain('about_page.team_profiles.1.links')
        ->and($paths)->toContain('about_page.team_profiles.1.description')
        ->and($paths)->toContain('about_page.team_profiles.1.image_path')
        ->and($aboutPage['blocks'])->toHaveCount(1)
        ->and($aboutPage['blocks'][0]['style'])->toBe('default')
        ->and($aboutPage['team_profiles'])->toHaveCount(1)
        ->and($aboutPage['team_profiles'][0])->not->toHaveKey('description')
        ->and($aboutPage['team_profiles'][0])->not->toHaveKey('image_path');
});

it('does not expose disabled about page content publicly', function (): void {
    saveStep7PublicFrontConfig([
        'about_page' => step7AboutConfig([
            'enabled' => false,
            'title' => 'Hidden About Title',
        ]),
    ]);

    $this->get('/about')
        ->assertNotFound()
        ->assertDontSee('Hidden About Title');
});

it('renders enabled about page content team profiles safe images and enabled form ctas', function (): void {
    saveStep7PublicFrontConfig([
        'public_forms' => step7PublicFormsConfig(),
        'about_page' => step7AboutConfig(),
    ]);

    $response = $this->get('/about');

    $response
        ->assertSuccessful()
        ->assertSee('data-test="about-page"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee('מי אנחנו')
        ->assertSee('על PodText')
        ->assertSee('תיאור בטוח.')
        ->assertSee('<strong>bold</strong>', false)
        ->assertSee('Rich safe text')
        ->assertSee('/storage/about/hero.webp')
        ->assertSee('/storage/team/bob.webp')
        ->assertSee('/storage/team/alice.png')
        ->assertSeeInOrder(['Bob Builder', 'Alice Admin'])
        ->assertDontSee('Eve Hidden')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert', false)
        ->assertSee('data-test="about-form-cta"', false)
        ->assertSee('data-form-key="request_transcription"', false)
        ->assertDontSee('Hidden disabled')
        ->assertDontSee('Hidden missing');
});

it('saves about content blocks and team profiles through the admin settings page', function (): void {
    config(['media.picker.driver' => 'file_upload']);
    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.about_page.enabled', true)
        ->set('data.about_page.title', 'Admin About')
        ->set('data.about_page.kicker', 'Admin Kicker')
        ->set('data.about_page.description', 'Admin description')
        ->set('data.about_page.settings.team_heading', 'Admin Team')
        ->set('data.about_page.settings.team_layout', 'list')
        ->set('data.about_page.blocks', [
            [
                'type' => 'markdown',
                'data' => [
                    'key' => 'admin_intro',
                    'visible' => true,
                    'sort' => 10,
                    'heading' => 'Intro',
                    'content' => 'Hello **there**',
                    'style' => 'default',
                ],
            ],
            [
                'type' => 'team_section',
                'data' => [
                    'key' => 'admin_team',
                    'visible' => true,
                    'sort' => 20,
                    'heading' => 'Team',
                    'style' => 'muted',
                ],
            ],
        ])
        ->set('data.about_page.team_profiles', [
            [
                'key' => 'admin_profile',
                'visible' => true,
                'sort' => 10,
                'image_path' => ['team/admin-profile.png'],
                'name' => 'Admin Profile',
                'title' => 'Maintainer',
                'description' => 'Maintains settings.',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep7PublicFrontSettingsCache();

    $aboutPage = app(PublicFrontConfigReader::class)
        ->read()
        ->group('about_page');

    expect($aboutPage['enabled'])->toBeTrue()
        ->and($aboutPage['title'])->toBe('Admin About')
        ->and($aboutPage['settings']['team_layout'])->toBe('list')
        ->and($aboutPage['blocks'])->toHaveCount(2)
        ->and($aboutPage['blocks'][0])->toMatchArray([
            'key' => 'admin_intro',
            'type' => 'markdown',
            'content' => 'Hello **there**',
        ])
        ->and($aboutPage['blocks'][1])->toMatchArray([
            'key' => 'admin_team',
            'type' => 'team_section',
            'style' => 'muted',
        ])
        ->and($aboutPage['team_profiles'][0])->toMatchArray([
            'key' => 'admin_profile',
            'image_path' => 'team/admin-profile.png',
            'name' => 'Admin Profile',
        ]);
});

it('configures about and team image uploads with safe public constraints', function (): void {
    config(['media.picker.driver' => 'file_upload']);
    $this->actingAs(User::factory()->create());

    $schema = Livewire::test(PublicContentSettingsPage::class)
        ->set('data.about_page.blocks', [
            [
                'type' => 'image',
                'data' => [
                    'key' => 'hero',
                    'visible' => true,
                    'sort' => 10,
                    'image_path' => null,
                ],
            ],
        ])
        ->set('data.about_page.team_profiles', [
            [
                'key' => 'profile',
                'visible' => true,
                'sort' => 10,
                'image_path' => null,
                'name' => 'Profile',
            ],
        ])
        ->instance()
        ->getSchema('form');

    $uploads = collect($schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->filter(fn (mixed $component): bool => $component instanceof FileUpload);

    $aboutImageUpload = $uploads->first(
        fn (FileUpload $component): bool => $component->getDirectory() === 'about',
    );
    $teamImageUpload = $uploads->first(
        fn (FileUpload $component): bool => $component->getDirectory() === 'team',
    );

    expect($aboutImageUpload)->toBeInstanceOf(FileUpload::class)
        ->and($aboutImageUpload->getDiskName())->toBe('public')
        ->and($aboutImageUpload->getDirectory())->toBe('about')
        ->and($aboutImageUpload->getVisibility())->toBe('public')
        ->and($aboutImageUpload->getAcceptedFileTypes())->toBe(PublicAboutPageRegistry::acceptedImageTypes())
        ->and($aboutImageUpload->getMaxSize())->toBe(PublicAboutPageRegistry::maxImageSize())
        ->and($teamImageUpload)->toBeInstanceOf(FileUpload::class)
        ->and($teamImageUpload->getDiskName())->toBe('public')
        ->and($teamImageUpload->getDirectory())->toBe('team')
        ->and($teamImageUpload->getVisibility())->toBe('public')
        ->and($teamImageUpload->getAcceptedFileTypes())->toBe(PublicAboutPageRegistry::acceptedImageTypes())
        ->and($teamImageUpload->getMaxSize())->toBe(PublicAboutPageRegistry::maxImageSize());
});

it('keeps about page and team content as settings rather than models', function (): void {
    expect(class_exists('App\\Models\\AboutPage'))->toBeFalse()
        ->and(class_exists('App\\Models\\AboutPageBlock'))->toBeFalse()
        ->and(class_exists('App\\Models\\TeamProfile'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('about_pages'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('about_page_blocks'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('team_profiles'))->toBeFalse();
});
