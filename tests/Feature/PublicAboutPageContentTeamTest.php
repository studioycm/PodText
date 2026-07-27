<?php

use App\Enums\ImageUploadPurpose;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Models\Media;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\SettingsOwnerImagePresenter;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
    Http::preventStrayRequests();
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

it('rejects duplicate persisted block and profile domain keys without collapsing owner identity', function (): void {
    $about = step7AboutConfig();
    $about['blocks'] = [
        [
            'type' => 'heading',
            'heading' => 'Derived heading',
        ],
        [
            'key' => 'heading_1',
            'type' => 'heading',
            'heading' => 'Explicit collision',
        ],
        [
            'key' => 'duplicate',
            'type' => 'heading',
            'heading' => 'First explicit',
        ],
        [
            'key' => 'duplicate',
            'type' => 'heading',
            'heading' => 'Second explicit',
        ],
    ];
    $about['team_profiles'] = [
        [
            'key' => 'duplicate',
            'name' => 'First',
        ],
        [
            'key' => 'duplicate',
            'name' => 'Second',
        ],
        [
            'key' => '',
            'name' => 'Missing key',
        ],
    ];
    $result = app(PublicFrontConfigValidator::class)->validate([
        'about_page' => $about,
    ]);
    $invalid = collect($result->invalidConfig());

    expect($invalid->firstWhere('path', 'about_page.blocks.1.key')?->reason)
        ->toBe('duplicate_key')
        ->and($invalid->firstWhere('path', 'about_page.blocks.3.key')?->reason)
        ->toBe('duplicate_key')
        ->and($invalid->firstWhere('path', 'about_page.team_profiles.1.key')?->reason)
        ->toBe('duplicate_key')
        ->and($invalid->contains(fn ($finding): bool => $finding->path === 'about_page.team_profiles.2.key'))
        ->toBeTrue()
        ->and($result->group('about_page')['blocks'])->toHaveCount(2)
        ->and($result->group('about_page')['blocks'][0]['key'])->toBe('heading_1')
        ->and($result->group('about_page')['blocks'][1]['key'])->toBe('duplicate')
        ->and($result->group('about_page')['team_profiles'])->toHaveCount(1);
});

it('keeps a legacy keyless stored block visible through an unrelated about save', function (): void {
    $admin = User::factory()->admin()->create();
    $about = step7AboutConfig();
    $about['blocks'] = [
        [
            'type' => 'heading',
            'visible' => true,
            'sort' => 10,
            'heading' => 'Legacy keyless heading',
        ],
    ];
    $about['team_profiles'] = [];
    saveStep7PublicFrontConfig(['about_page' => $about]);
    $storedBefore = json_decode(
        (string) DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'about_page')
            ->value('payload'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $publicBefore = app(PublicFrontConfigReader::class)->readFresh()->group('about_page');

    expect($storedBefore['blocks'][0])->not->toHaveKey('key')
        ->and($publicBefore['blocks'])->toHaveCount(1)
        ->and($publicBefore['blocks'][0])->toMatchArray([
            'key' => 'heading_1',
            'type' => 'heading',
            'heading' => 'Legacy keyless heading',
        ]);

    $component = Livewire::actingAs($admin)->test(AboutSettings::class);
    $formBlocks = array_values($component->get('data.about_page.blocks'));
    expect($formBlocks)->toHaveCount(1)
        ->and($formBlocks[0]['type'])->toBe('heading')
        ->and($formBlocks[0]['data']['key'])->toBe('heading_1')
        ->and($formBlocks[0]['data']['heading'])->toBe('Legacy keyless heading');

    Event::fake([SettingsSaved::class]);
    $component
        ->set('data.about_page.description', 'Unrelated About change')
        ->call('save')
        ->assertHasNoFormErrors();
    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    clearStep7PublicFrontSettingsCache();
    $storedAfter = app(PublicContentSettings::class)->about_page;
    $publicAfter = app(PublicFrontConfigReader::class)->readFresh()->group('about_page');

    expect($storedAfter['description'])->toBe('Unrelated About change')
        ->and($storedAfter['blocks'])->toHaveCount(1)
        ->and($storedAfter['blocks'][0])->toMatchArray([
            'type' => 'heading',
            'heading' => 'Legacy keyless heading',
        ])
        ->and($storedAfter['blocks'][0])->not->toHaveKey('key')
        ->and($publicAfter['blocks'][0])->toMatchArray([
            'key' => 'heading_1',
            'type' => 'heading',
            'heading' => 'Legacy keyless heading',
        ]);
});

it('preserves an untouched invalid stored unit exactly through a disjoint valid about save', function (): void {
    $admin = User::factory()->admin()->create();
    $about = step7AboutConfig();
    $about['title'] = 'Opened title';
    $about['blocks'] = [
        [
            'key' => 'legacy-invalid-image',
            'type' => 'image',
            'visible' => true,
            'sort' => 10,
            'image_path' => '../outside-public-root.png',
            'image_alt' => 'Do not normalize or delete this unit',
        ],
    ];
    $about['team_profiles'] = [];
    saveStep7PublicFrontConfig(['about_page' => $about]);
    $storedBefore = app(PublicContentSettings::class)->about_page;
    $blockBytesBefore = json_encode(
        $storedBefore['blocks'],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );

    $component = Livewire::actingAs($admin)->test(AboutSettings::class);

    expect($component->get('data.about_page.blocks'))->toBe([]);

    Event::fake([SettingsSaved::class]);

    $component
        ->set('data.about_page.title', 'Saved disjoint title')
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    clearStep7PublicFrontSettingsCache();
    $storedAfter = app(PublicContentSettings::class)->about_page;

    expect($storedAfter['title'])->toBe('Saved disjoint title')
        ->and($storedAfter['blocks'])->toBe($storedBefore['blocks'])
        ->and(json_encode(
            $storedAfter['blocks'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ))->toBe($blockBytesBefore);
});

it('halts when a visible edit would replace a whole list containing hidden invalid data', function (): void {
    $admin = User::factory()->admin()->create();
    $about = step7AboutConfig();
    $about['blocks'] = [
        [
            'key' => 'legacy-invalid-image',
            'type' => 'image',
            'visible' => true,
            'sort' => 10,
            'image_path' => '../outside-public-root.png',
            'image_alt' => 'Hidden invalid unit must survive',
        ],
    ];
    $about['team_profiles'] = [];
    saveStep7PublicFrontConfig(['about_page' => $about]);
    $component = Livewire::actingAs($admin)->test(AboutSettings::class);
    $pendingBlocks = [
        [
            'type' => 'heading',
            'data' => [
                'key' => 'new-visible-heading',
                'heading' => 'Pending visible heading',
                'visible' => true,
                'sort' => 20,
            ],
        ],
    ];
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());
    $storedBytesBefore = json_encode(
        $storedBefore,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );

    expect($component->get('data.about_page.blocks'))->toBe([]);
    Event::fake([SettingsSaved::class]);

    $component
        ->set('data.about_page.blocks', $pendingBlocks)
        ->call('save')
        ->assertHasFormErrors(['about_page.blocks'])
        ->assertSet('data.about_page.blocks', $pendingBlocks);

    Event::assertNotDispatched(SettingsSaved::class);
    clearStep7PublicFrontSettingsCache();
    $storedAfter = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    expect($storedAfter)->toBe($storedBefore)
        ->and(json_encode(
            $storedAfter,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ))->toBe($storedBytesBefore);
});

it('presents a legacy keyless image under its derived key without rewriting the stored unit', function (): void {
    $admin = User::factory()->admin()->create();
    $saved = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/legacy-keyless-saved.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $about = step7AboutConfig();
    $about['blocks'] = [
        [
            'type' => 'image',
            'visible' => true,
            'sort' => 10,
            'image_path' => $saved->path,
            'image_media_reference_key' => $saved->reference_key,
            'image_alt' => 'Legacy saved image',
        ],
    ];
    $about['team_profiles'] = [];
    saveStep7PublicFrontConfig(['about_page' => $about]);

    $component = Livewire::actingAs($admin)->test(AboutSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->filter(fn (mixed $field): bool => $field instanceof PathCuratorPicker)
        ->first(fn (PathCuratorPicker $field): bool => $field->getOwnerChoicePresentation()->ownerKind
            === 'about_page.blocks.image_1.image_media_reference_key');
    assert($picker instanceof PathCuratorPicker);
    $presentation = $picker->getOwnerChoicePresentation();
    $formBlocks = array_values($component->get('data.about_page.blocks'));
    $blockItemKey = array_key_first($component->get('data.about_page.blocks'));

    expect($presentation->isProspective)->toBeFalse()
        ->and($presentation->savedMediaId)->toBe($saved->getKey())
        ->and($presentation->savedReferenceKey)->toBe($saved->reference_key)
        ->and($presentation->directState)->toBe('present')
        ->and($formBlocks[0]['data']['key'])->toBe('image_1')
        ->and($component->get(
            "data.about_page.blocks.{$blockItemKey}.data.image_media_reference_key",
        ))->toBe($saved->getKey());

    Event::fake([SettingsSaved::class]);
    $component
        ->set('data.about_page.description', 'Unrelated image-block change')
        ->call('save')
        ->assertHasNoFormErrors();
    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    clearStep7PublicFrontSettingsCache();
    $stored = app(PublicContentSettings::class)->about_page;

    expect($stored['description'])->toBe('Unrelated image-block change')
        ->and($stored['blocks'])->toHaveCount(1)
        ->and($stored['blocks'][0])->not->toHaveKey('key')
        ->and($stored['blocks'][0]['image_media_reference_key'])->toBe($saved->reference_key)
        ->and($stored['blocks'][0]['image_path'])->toBe($saved->path);
});

it('replaces a broken configured about image block through the real owner picker workflow', function (): void {
    Storage::fake('public');
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'about/missing-about-block.png';
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'name' => 'replacement-about-block',
        'path' => 'about/replacement-about-block.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($replacement->path, 'replacement');
    $about = step7AboutConfig();
    $about['blocks'] = [[
        'key' => 'broken_about_block',
        'type' => 'image',
        'visible' => true,
        'sort' => 10,
        'image_path' => $missingPath,
        'image_media_reference_key' => $missingReferenceKey,
        'image_alt' => 'Broken about image',
    ]];
    $about['team_profiles'] = [];
    saveStep7PublicFrontConfig(['about_page' => $about]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class);
    $blockItemKey = array_key_first($component->get('data.about_page.blocks'));

    $component
        ->assertSet(
            "data.about_page.blocks.{$blockItemKey}.data.image_media_reference_key",
            $missingReferenceKey,
        )
        ->set(
            "data.about_page.blocks.{$blockItemKey}.data.image_media_reference_key",
            $replacement->getKey(),
        )
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep7PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->about_page['blocks'][0])
        ->toMatchArray([
            'image_path' => $replacement->path,
            'image_media_reference_key' => $replacement->reference_key,
        ]);
});

it('clears a broken configured about image block through the real builder delete action', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'about/missing-about-block-to-clear.png';
    $about = step7AboutConfig();
    $about['blocks'] = [[
        'key' => 'broken_about_block',
        'type' => 'image',
        'visible' => true,
        'sort' => 10,
        'image_path' => $missingPath,
        'image_media_reference_key' => $missingReferenceKey,
        'image_alt' => 'Broken about image',
    ]];
    $about['team_profiles'] = [];
    saveStep7PublicFrontConfig(['about_page' => $about]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->filter(fn (mixed $field): bool => $field instanceof PathCuratorPicker)
        ->first(fn (PathCuratorPicker $field): bool => $field->getOwnerChoicePresentation()->ownerKind
            === 'about_page.blocks.broken_about_block.image_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    expect($picker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($picker->getOwnerChoicePresentation()?->canChooseAutomatic)->toBeFalse();
    $builder = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof Builder
        && $field->getStatePath(isAbsolute: false) === 'about_page.blocks');
    assert($builder instanceof Builder);
    $blockItemKey = array_key_first($component->get('data.about_page.blocks'));

    $component
        ->callAction(
            TestAction::make('delete')->schemaComponent(
                (string) str($builder->getKey())->after('.'),
            ),
            arguments: ['item' => $blockItemKey],
        )
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep7PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->about_page['blocks'])->toBe([]);
});

it('replaces a broken configured team profile image through the real owner picker workflow', function (): void {
    Storage::fake('public');
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'team/missing-team-profile.png';
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => ImageUploadPurpose::TeamImage->root(),
        'name' => 'replacement-team-profile',
        'path' => 'team/replacement-team-profile.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($replacement->path, 'replacement');
    $about = step7AboutConfig();
    $about['blocks'] = [];
    $about['team_profiles'] = [[
        'key' => 'broken_profile',
        'visible' => true,
        'sort' => 10,
        'image_path' => $missingPath,
        'image_media_reference_key' => $missingReferenceKey,
        'name' => 'Broken profile',
    ]];
    saveStep7PublicFrontConfig(['about_page' => $about]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class);
    $profileItemKey = array_key_first($component->get('data.about_page.team_profiles'));

    $component
        ->assertSet(
            "data.about_page.team_profiles.{$profileItemKey}.image_media_reference_key",
            $missingReferenceKey,
        )
        ->set(
            "data.about_page.team_profiles.{$profileItemKey}.image_media_reference_key",
            $replacement->getKey(),
        )
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep7PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->about_page['team_profiles'][0])
        ->toMatchArray([
            'image_path' => $replacement->path,
            'image_media_reference_key' => $replacement->reference_key,
        ]);
});

it('clears a broken configured team profile image through the explicit owner action', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'team/missing-team-profile-to-clear.png';
    $about = step7AboutConfig();
    $about['blocks'] = [];
    $about['team_profiles'] = [[
        'key' => 'broken_profile',
        'visible' => true,
        'sort' => 10,
        'image_path' => $missingPath,
        'image_media_reference_key' => $missingReferenceKey,
        'name' => 'Broken profile',
    ]];
    saveStep7PublicFrontConfig(['about_page' => $about]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->filter(fn (mixed $field): bool => $field instanceof PathCuratorPicker)
        ->first(fn (PathCuratorPicker $field): bool => $field->getOwnerChoicePresentation()->ownerKind
            === 'about_page.team_profiles.broken_profile.image_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    expect($picker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($picker->getOwnerChoicePresentation()?->canChooseAutomatic)->toBeTrue();

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep7PublicFrontSettingsCache();

    $profile = app(PublicContentSettings::class)->about_page['team_profiles'][0];

    expect(data_get($profile, 'image_path'))->toBeNull()
        ->and(data_get($profile, 'image_media_reference_key'))->toBeNull()
        ->and($profile)->not->toHaveKey('image_path')
        ->and($profile)->not->toHaveKey('image_media_reference_key');
});

it('merges an unchanged legacy image unit with a disjoint local about edit', function (): void {
    $admin = User::factory()->admin()->create();
    $openedWith = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/legacy-keyless-opened.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $changedElsewhere = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/legacy-keyless-changed.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $keylessAbout = function (Media $media, string $description): array {
        $about = step7AboutConfig();
        $about['description'] = $description;
        $about['blocks'] = [
            [
                'type' => 'image',
                'visible' => true,
                'sort' => 10,
                'image_path' => $media->path,
                'image_media_reference_key' => $media->reference_key,
                'image_alt' => 'Concurrent legacy image',
            ],
        ];
        $about['team_profiles'] = [];

        return $about;
    };
    saveStep7PublicFrontConfig([
        'about_page' => $keylessAbout($openedWith, 'Opened description'),
    ]);

    $component = Livewire::actingAs($admin)->test(AboutSettings::class);
    $blockItemKey = array_key_first($component->get('data.about_page.blocks'));
    $openedFingerprint = $component->get('settingsOwnerImageFingerprint');
    expect($component->get(
        "data.about_page.blocks.{$blockItemKey}.data.image_media_reference_key",
    ))->toBe($openedWith->getKey());

    saveStep7PublicFrontConfig([
        'about_page' => $keylessAbout($changedElsewhere, 'Changed elsewhere'),
    ]);
    $externalAbout = SettingsSubjectOwnershipRegistry::extractOwned(
        app(PublicContentSettings::class)->toArray(),
        SettingsSubjectOwnershipRegistry::ABOUT,
    );
    Event::fake([SettingsSaved::class]);

    $component
        ->set('data.about_page.title', 'Pending local title')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.about_page.title', 'Pending local title');
    $refilledBlockItemKey = array_key_first($component->get('data.about_page.blocks'));
    $component->assertSet(
        "data.about_page.blocks.{$refilledBlockItemKey}.data.image_media_reference_key",
        $changedElsewhere->getKey(),
    );
    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    expect($component->get('settingsOwnerImageFingerprint'))->not->toBe($openedFingerprint);
    clearStep7PublicFrontSettingsCache();
    $afterSave = SettingsSubjectOwnershipRegistry::extractOwned(
        app(PublicContentSettings::class)->toArray(),
        SettingsSubjectOwnershipRegistry::ABOUT,
    );

    expect($afterSave['about_page']['title'])->toBe('Pending local title')
        ->and($afterSave['about_page']['description'])->toBe('Changed elsewhere')
        ->and($afterSave['about_page']['blocks'])->toBe($externalAbout['about_page']['blocks'])
        ->and($afterSave['about_page']['blocks'][0])->not->toHaveKey('key')
        ->and($afterSave['about_page']['blocks'][0]['image_media_reference_key'])
        ->toBe($changedElsewhere->reference_key)
        ->and($afterSave['about_page']['blocks'][0]['image_path'])
        ->toBe($changedElsewhere->path);
});

it('requires unique domain keys for cloned or duplicated about form rows', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->set('data.about_page.blocks', [
            [
                'type' => 'heading',
                'data' => [
                    'key' => 'cloned_block',
                    'heading' => 'First',
                ],
            ],
            [
                'type' => 'heading',
                'data' => [
                    'key' => 'cloned_block',
                    'heading' => 'Clone',
                ],
            ],
        ])
        ->set('data.about_page.team_profiles', [
            [
                'key' => 'cloned_profile',
                'name' => 'First',
            ],
            [
                'key' => 'cloned_profile',
                'name' => 'Clone',
            ],
        ])
        ->call('save');
    $errorPaths = $component->instance()->getErrorBag()->keys();

    expect($errorPaths)
        ->toHaveCount(4)
        ->and(collect($errorPaths)->filter(
            fn (string $path): bool => str_starts_with($path, 'data.about_page.blocks.')
                && str_ends_with($path, '.data.key'),
        ))->toHaveCount(2)
        ->and(collect($errorPaths)->filter(
            fn (string $path): bool => str_starts_with($path, 'data.about_page.team_profiles.')
                && str_ends_with($path, '.key'),
        ))->toHaveCount(2);
});

it('clears copied domain keys when cloning about blocks and profiles', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->set('data.about_page.blocks', [
            [
                'type' => 'heading',
                'data' => [
                    'key' => 'original_block',
                    'heading' => 'Original',
                ],
            ],
        ])
        ->set('data.about_page.team_profiles', [
            [
                'key' => 'original_profile',
                'name' => 'Original',
            ],
        ]);
    $blockItem = array_key_first($component->get('data.about_page.blocks'));
    $profileItem = array_key_first($component->get('data.about_page.team_profiles'));
    $schemaComponents = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $builder = $schemaComponents->first(
        fn (mixed $field): bool => $field instanceof Builder
            && $field->getStatePath(isAbsolute: false) === 'about_page.blocks',
    );
    $repeater = $schemaComponents->first(
        fn (mixed $field): bool => $field instanceof Repeater
            && $field->getStatePath(isAbsolute: false) === 'about_page.team_profiles',
    );
    assert($builder instanceof Builder);
    assert($repeater instanceof Repeater);

    $component
        ->callAction(TestAction::make('clone')
            ->schemaComponent((string) str($builder->getKey())->after('.'))
            ->arguments(['item' => $blockItem]))
        ->callAction(TestAction::make('clone')
            ->schemaComponent((string) str($repeater->getKey())->after('.'))
            ->arguments(['item' => $profileItem]));
    $blocks = array_values($component->get('data.about_page.blocks'));
    $profiles = array_values($component->get('data.about_page.team_profiles'));

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['data']['key'])->toBe('original_block')
        ->and($blocks[1]['data']['key'])->toBeNull()
        ->and($blocks[1]['data']['heading'])->toBe('Original')
        ->and($profiles)->toHaveCount(2)
        ->and($profiles[0]['key'])->toBe('original_profile')
        ->and($profiles[1]['key'])->toBeNull()
        ->and($profiles[1]['name'])->toBe('Original');
});

it('treats ambiguous duplicate stored owner keys as prospective instead of collapsing rows', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $first = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/duplicate-first.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $second = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/duplicate-second.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $about = step7AboutConfig();
    $about['blocks'] = [
        [
            'key' => 'duplicate',
            'type' => 'image',
            'image_path' => $first->path,
            'image_media_reference_key' => $first->reference_key,
        ],
        [
            'key' => 'duplicate',
            'type' => 'image',
            'image_path' => $second->path,
            'image_media_reference_key' => $second->reference_key,
        ],
    ];
    $about['team_profiles'] = [];
    $snapshot = app(SettingsOwnerImagePresenter::class)->snapshot(
        ['about_page' => $about],
        SettingsSubjectOwnershipRegistry::ABOUT,
    );
    $choice = app(SettingsOwnerImagePresenter::class)->choice(
        $snapshot,
        'about_page.blocks.duplicate.image_media_reference_key',
        $first->getKey(),
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
    );

    expect($choice->isProspective)->toBeTrue()
        ->and($choice->savedMediaId)->toBeNull()
        ->and($snapshot->slots)->toHaveCount(2);
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
    $this->actingAs(User::factory()->create());
    $media = Media::factory()->create([
        'directory' => ImageUploadPurpose::TeamImage->root(),
        'name' => 'admin-profile',
        'path' => ImageUploadPurpose::TeamImage->root().'/admin-profile.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);

    Livewire::test(AboutSettings::class)
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
                'image_media_reference_key' => $media->getKey(),
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
            'image_media_reference_key' => $media->reference_key,
            'name' => 'Admin Profile',
        ]);
});

it('configures about and team image uploads with safe public constraints', function (): void {
    $this->actingAs(User::factory()->create());

    $schema = Livewire::test(AboutSettings::class)
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

    $pickers = collect($schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->filter(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    $aboutImagePicker = $pickers->first(
        fn (PathCuratorPicker $component): bool => $component->getUploadPurpose() === ImageUploadPurpose::AboutImage,
    );
    $teamImagePicker = $pickers->first(
        fn (PathCuratorPicker $component): bool => $component->getUploadPurpose() === ImageUploadPurpose::TeamImage,
    );

    expect($aboutImagePicker)->toBeInstanceOf(PathCuratorPicker::class)
        ->and($aboutImagePicker->getUploadPurpose())->toBe(ImageUploadPurpose::AboutImage)
        ->and($aboutImagePicker->isOwnerChoice())->toBeTrue()
        ->and($aboutImagePicker->getOwnerChoicePresentation()?->ownerLabel)->toBe('hero')
        ->and($aboutImagePicker->getOwnerChoicePresentation()?->isProspective)->toBeTrue()
        ->and($teamImagePicker)->toBeInstanceOf(PathCuratorPicker::class)
        ->and($teamImagePicker->getUploadPurpose())->toBe(ImageUploadPurpose::TeamImage)
        ->and($teamImagePicker->isOwnerChoice())->toBeTrue()
        ->and($teamImagePicker->getOwnerChoicePresentation()?->ownerLabel)->toBe('profile')
        ->and($teamImagePicker->getOwnerChoicePresentation()?->isProspective)->toBeTrue();
});

it('matches persisted about and team images by stable content key instead of row position', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $heroA = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/stable-hero-a.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $heroB = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'path' => 'about/stable-hero-b.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $profileA = Media::factory()->create([
        'directory' => ImageUploadPurpose::TeamImage->root(),
        'path' => 'team/stable-profile-a.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $profileB = Media::factory()->create([
        'directory' => ImageUploadPurpose::TeamImage->root(),
        'path' => 'team/stable-profile-b.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $about = step7AboutConfig();
    $about['blocks'] = [
        [
            'key' => 'stable_hero_a',
            'type' => 'image',
            'visible' => true,
            'sort' => 20,
            'image_path' => $heroA->path,
            'image_media_reference_key' => $heroA->reference_key,
        ],
        [
            'key' => 'stable_hero_b',
            'type' => 'image',
            'visible' => true,
            'sort' => 10,
            'image_path' => $heroB->path,
            'image_media_reference_key' => $heroB->reference_key,
        ],
    ];
    $about['team_profiles'] = [
        [
            'key' => 'stable_profile_a',
            'visible' => true,
            'sort' => 20,
            'image_path' => $profileA->path,
            'image_media_reference_key' => $profileA->reference_key,
            'name' => 'Profile A',
        ],
        [
            'key' => 'stable_profile_b',
            'visible' => true,
            'sort' => 10,
            'image_path' => $profileB->path,
            'image_media_reference_key' => $profileB->reference_key,
            'name' => 'Profile B',
        ],
    ];
    saveStep7PublicFrontConfig([
        'about_page' => $about,
    ]);

    $component = Livewire::test(AboutSettings::class)
        ->set('data.about_page.blocks', [
            [
                'type' => 'image',
                'data' => [
                    ...$about['blocks'][1],
                    'image_media_reference_key' => $heroB->getKey(),
                ],
            ],
            [
                'type' => 'image',
                'data' => [
                    ...$about['blocks'][0],
                    'image_media_reference_key' => $heroA->getKey(),
                ],
            ],
            [
                'type' => 'image',
                'data' => [
                    'key' => 'prospective_hero',
                    'visible' => true,
                    'sort' => 30,
                    'image_media_reference_key' => null,
                ],
            ],
        ])
        ->set('data.about_page.team_profiles', [
            [
                ...$about['team_profiles'][1],
                'image_media_reference_key' => $profileB->getKey(),
            ],
            [
                ...$about['team_profiles'][0],
                'image_media_reference_key' => $profileA->getKey(),
            ],
            [
                'key' => 'prospective_profile',
                'visible' => true,
                'sort' => 30,
                'image_media_reference_key' => null,
                'name' => 'Prospective Profile',
            ],
        ]);
    $schema = $component->instance()->getSchema('form');
    $pickers = collect($schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->filter(fn (mixed $field): bool => $field instanceof PathCuratorPicker)
        ->mapWithKeys(fn (PathCuratorPicker $field): array => [
            $field->getOwnerChoicePresentation()->ownerKind => $field->getOwnerChoicePresentation(),
        ]);

    expect($pickers['about_page.blocks.stable_hero_a.image_media_reference_key']->savedMediaId)
        ->toBe($heroA->getKey())
        ->and($pickers['about_page.blocks.stable_hero_b.image_media_reference_key']->savedMediaId)
        ->toBe($heroB->getKey())
        ->and($pickers['about_page.team_profiles.stable_profile_a.image_media_reference_key']->savedMediaId)
        ->toBe($profileA->getKey())
        ->and($pickers['about_page.team_profiles.stable_profile_b.image_media_reference_key']->savedMediaId)
        ->toBe($profileB->getKey())
        ->and($pickers['about_page.blocks.prospective_hero.image_media_reference_key']->isProspective)
        ->toBeTrue()
        ->and($pickers['about_page.team_profiles.prospective_profile.image_media_reference_key']->isProspective)
        ->toBeTrue();
});

it('keeps about owner image presentation queries bounded as configured rows grow', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $settingsFor = function (int $count, string $prefix): array {
        $blocks = [];
        $profiles = [];

        foreach (range(1, $count) as $index) {
            $aboutMedia = Media::factory()->create([
                'directory' => ImageUploadPurpose::AboutImage->root(),
                'path' => "about/{$prefix}-block-{$index}.png",
                'type' => 'image/png',
                'ext' => 'png',
            ]);
            $teamMedia = Media::factory()->create([
                'directory' => ImageUploadPurpose::TeamImage->root(),
                'path' => "team/{$prefix}-profile-{$index}.png",
                'type' => 'image/png',
                'ext' => 'png',
            ]);
            $blocks[] = [
                'key' => "{$prefix}_block_{$index}",
                'type' => 'image',
                'image_path' => $aboutMedia->path,
                'image_media_reference_key' => $aboutMedia->reference_key,
            ];
            $profiles[] = [
                'key' => "{$prefix}_profile_{$index}",
                'name' => "Profile {$index}",
                'image_path' => $teamMedia->path,
                'image_media_reference_key' => $teamMedia->reference_key,
            ];
        }

        return ['about_page' => [
            'blocks' => $blocks,
            'team_profiles' => $profiles,
        ]];
    };
    $measure = function (array $settings): int {
        $queries = [];
        DB::listen(function (mixed $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $presenter = app(SettingsOwnerImagePresenter::class);
        $snapshot = $presenter->snapshot($settings, SettingsSubjectOwnershipRegistry::ABOUT);

        foreach ($snapshot->slots as $slotKey => $slot) {
            $presenter->choice(
                $snapshot,
                $slotKey,
                $slot['configured_reference_key'],
                ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
            );
        }

        return count($queries);
    };
    $smallQueries = $measure($settingsFor(2, 'small'));
    $largeQueries = $measure($settingsFor(8, 'large'));

    expect($largeQueries)->toBeLessThanOrEqual($smallQueries + 2);
});

it('keeps about page and team content as settings rather than models', function (): void {
    expect(class_exists('App\\Models\\AboutPage'))->toBeFalse()
        ->and(class_exists('App\\Models\\AboutPageBlock'))->toBeFalse()
        ->and(class_exists('App\\Models\\TeamProfile'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('about_pages'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('about_page_blocks'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('team_profiles'))->toBeFalse();
});
