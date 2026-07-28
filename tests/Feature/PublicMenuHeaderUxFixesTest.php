<?php

use App\Enums\HomepageSectionType;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Livewire\Admin\MediaPickerPanel;
use App\Livewire\Public\ContributorDirectory;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\SettingsOwnerImagePresenter;
use App\Support\PublicFront\Menu\PublicMenuConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Livewire as LivewireSchemaComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function clearStep9PublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep9PublicFrontConfig(array $config): void
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

    clearStep9PublicFrontSettingsCache();
}

function step9PublicFormsConfig(): array
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
                'key' => 'volunteer_transcriber',
                'name' => 'Volunteer transcriber',
                'heading' => 'Register as a transcriber',
                'submit_label' => 'Register',
                'success_message' => 'Registration received.',
                'enabled' => true,
                'display_mode_default' => 'slide_over',
                'fields' => [
                    [
                        'key' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
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

function createStep9PublicItem(
    Author $author,
    string $title,
    array $itemAttributes = [],
    ?ContentGroup $group = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();

    $contentItem = ContentItem::factory()
        ->for($group)
        ->published($itemAttributes['published_at'] ?? now()->subMinute())
        ->create([
            'title' => $title,
            ...$itemAttributes,
        ]);

    $transcription = Transcription::factory()
        ->for($contentItem)
        ->forAuthor($author)
        ->published(now()->subMinute())
        ->create(['title' => $title]);

    $contentItem->update(['featured_transcription_id' => $transcription->id]);

    return $contentItem->refresh();
}

it('renders the focused menu and header settings page', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(MenuHeaderSettings::class)
        ->assertSee(__('admin.sections.public_front_menu_header'))
        ->assertSee('data.menu_config.items', false);
});

it('clears only the cloned route-label identity', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->set('data.route_labels', [
            [
                'route_key' => 'home',
                'label' => 'Homepage label',
            ],
        ]);
    $routeLabelItem = array_key_first($component->get('data.route_labels'));
    $schemaComponents = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $routeLabelsRepeater = $schemaComponents->first(
        fn (mixed $field): bool => $field instanceof Repeater
            && $field->getStatePath(isAbsolute: false) === 'route_labels',
    );
    assert($routeLabelsRepeater instanceof Repeater);

    $component->callAction(TestAction::make('clone')
        ->schemaComponent((string) str($routeLabelsRepeater->getKey())->after('.'))
        ->arguments(['item' => $routeLabelItem]));
    $routeLabels = array_values($component->get('data.route_labels'));

    expect($routeLabels)->toHaveCount(2)
        ->and($routeLabels[0]['route_key'])->toBe('home')
        ->and($routeLabels[1]['route_key'])->toBeNull()
        ->and($routeLabels[1]['label'])->toBe('Homepage label');
});

it('requires distinct route-label selections before settings writes', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->set('data.route_labels', [
            [
                'route_key' => 'home',
                'label' => 'First home label',
            ],
            [
                'route_key' => 'home',
                'label' => 'Second home label',
            ],
        ]);
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component->call('save');
    $errorPaths = $component->instance()->getErrorBag()->keys();

    expect(collect($errorPaths)->filter(
        fn (string $path): bool => str_starts_with($path, 'data.route_labels.')
            && str_ends_with($path, '.route_key'),
    ))->toHaveCount(2);
    Event::assertNotDispatched(SettingsSaved::class);
    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('rejects a locally invalid route-label unit without changing settings', function (string $locale): void {
    app()->setLocale($locale);
    saveStep9PublicFrontConfig([
        'route_labels' => [
            [
                'route_key' => 'home',
                'label' => 'Home',
            ],
            [
                'route_key' => 'search',
                'label' => 'Search',
            ],
        ],
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->set('data.route_labels', [
            [
                'route_key' => 'home',
                'label' => 'Home',
            ],
            [
                'route_key' => 'search',
                'label' => '<script>alert(1)</script>',
            ],
        ]);
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->call('save')
        ->assertHasFormErrors(['route_labels.search'])
        ->assertNotified(__('admin.validation.settings_unit_invalid', [
            'unit' => app(SettingsLifecycleSchema::class)->labelFor('route_labels.search'),
        ]));

    Event::assertNotDispatched(SettingsSaved::class);
    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
})->with(['en', 'he']);

it('rejects a known route edit when an unregistered stored row makes the whole section unsafe', function (): void {
    saveStep9PublicFrontConfig([
        'route_labels' => [
            [
                'route_key' => 'home',
                'label' => 'Home',
            ],
            [
                'label' => 'Missing route identity',
            ],
        ],
    ]);
    $pendingLabels = [
        [
            'route_key' => 'home',
            'label' => 'Pending home label',
        ],
    ];
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->set('data.route_labels', $pendingLabels);
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->call('save')
        ->assertHasFormErrors(['route_labels'])
        ->assertSet('data.route_labels', $pendingLabels)
        ->assertNotified(__('admin.validation.settings_unit_invalid', [
            'unit' => app(SettingsLifecycleSchema::class)->labelFor('route_labels'),
        ]));

    Event::assertNotDispatched(SettingsSaved::class);
    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('preserves a separate known invalid route unit while saving a valid known route edit', function (): void {
    saveStep9PublicFrontConfig([
        'route_labels' => [
            [
                'route_key' => 'home',
                'label' => 'Home',
            ],
            [
                'route_key' => 'search',
                'label' => '<script>legacy unsafe search</script>',
            ],
        ],
    ]);
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->set('data.route_labels', [
            [
                'route_key' => 'home',
                'label' => 'Updated home',
            ],
        ]);

    Event::fake([SettingsSaved::class]);

    $component
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    clearStep9PublicFrontSettingsCache();
    $labels = collect(app(PublicContentSettings::class)->route_labels)
        ->keyBy('route_key');

    expect($labels->get('home')['label'])->toBe('Updated home')
        ->and($labels->get('search')['label'])->toBe('<script>legacy unsafe search</script>');
});

it('shows light and dark logo current pending and committed owner-choice state', function (): void {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $current = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'current-logo',
        'path' => 'header/current-logo.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'replacement-logo',
        'path' => 'header/replacement-logo.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($current->path, 'current');
    Storage::disk('public')->put($replacement->path, 'replacement');
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $current->path,
                'light_media_reference_key' => $current->reference_key,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component = Livewire::actingAs($admin)->test(MenuHeaderSettings::class);
    $pickers = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->filter(fn (mixed $field): bool => $field instanceof PathCuratorPicker)
        ->keyBy(fn (PathCuratorPicker $field): string => $field->getStatePath(isAbsolute: false));
    $light = $pickers->get('menu_config.logo.light_media_reference_key');
    $dark = $pickers->get('menu_config.logo.dark_media_reference_key');

    expect($light->getOwnerChoicePresentation()->savedMediaId)->toBe($current->getKey())
        ->and($light->getOwnerChoicePresentation()->pendingKind)->toBe('unchanged')
        ->and($dark->getOwnerChoicePresentation()->directState)->toBe('absent')
        ->and($dark->getOwnerChoicePresentation()->shownNowSource)->toBe('none');

    $component
        ->set('data.menu_config.logo.light_media_reference_key', $replacement->getKey())
        ->set('data.menu_config.logo.alt_text', 'Unsaved logo label');
    $pendingLight = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');

    expect($pendingLight->getOwnerChoicePresentation()->pendingMedia['id'] ?? null)
        ->toBe($replacement->getKey());

    $component->call('save')->assertHasNoFormErrors();
    clearStep9PublicFrontSettingsCache();
    $logo = app(PublicContentSettings::class)->menu_config['logo'];

    expect($logo['light_media_reference_key'])->toBe($replacement->reference_key)
        ->and($logo['light_path'])->toBe($replacement->path)
        ->and($logo['alt_text'])->toBe('Unsaved logo label');
});

it('replaces a broken configured menu logo through the real owner picker workflow', function (): void {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'header/missing-logo.png';
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'replacement-logo',
        'path' => 'header/replacement-for-missing-logo.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($replacement->path, 'replacement');
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $missingPath,
                'light_media_reference_key' => $missingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    Livewire::actingAs($admin)
        ->test(MenuHeaderSettings::class)
        ->set('data.menu_config.logo.light_media_reference_key', $replacement->getKey())
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo'])
        ->toMatchArray([
            'light_path' => $replacement->path,
            'light_media_reference_key' => $replacement->reference_key,
        ]);
});

it('clears a broken configured menu logo only through the explicit owner action', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'header/missing-logo-to-clear.png';
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $missingPath,
                'light_media_reference_key' => $missingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class);
    $component->assertSet(
        'data.menu_config.logo.light_media_reference_key',
        $missingReferenceKey,
    );
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    expect($picker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($picker->getOwnerChoicePresentation()?->canChooseAutomatic)->toBeTrue();

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo'])
        ->toMatchArray([
            'light_path' => null,
            'light_media_reference_key' => null,
        ]);
});

it('preserves an untouched unresolved legacy menu logo path exactly', function (): void {
    $missingPath = 'header/legacy-logo-that-has-no-media-row.png';
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $missingPath,
                'light_media_reference_key' => null,
                'dark_path' => null,
                'dark_media_reference_key' => null,
                'alt_text' => 'Before unrelated edit',
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $missingPath,
        )
        ->set('data.menu_config.logo.alt_text', 'After unrelated edit')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo'])
        ->toMatchArray([
            'light_path' => $missingPath,
            'light_media_reference_key' => null,
            'alt_text' => 'After unrelated edit',
        ]);
});

it('presents a broken configured menu logo as safe evidence instead of absence', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'header/missing-logo-evidence.png';
    $presenter = app(SettingsOwnerImagePresenter::class);
    $broken = $presenter->snapshot([
        'menu_config' => [
            'logo' => [
                'light_path' => $missingPath,
                'light_media_reference_key' => $missingReferenceKey,
            ],
        ],
    ], SettingsSubjectOwnershipRegistry::MENU_HEADER);
    $absent = $presenter->snapshot([
        'menu_config' => [
            'logo' => [
                'light_path' => null,
                'light_media_reference_key' => null,
            ],
        ],
    ], SettingsSubjectOwnershipRegistry::MENU_HEADER);
    $choice = $presenter->choice(
        $broken,
        'menu_config.logo.light_media_reference_key',
        null,
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
    );

    expect($choice->directState)->toBe('broken')
        ->and($choice->savedReferenceKey)->toBe($missingReferenceKey)
        ->and($choice->expectedLegacyPath)->toBe($missingPath)
        ->and($choice->directMedia)->toBeNull()
        ->and($choice->shownNowMedia)->toBeNull()
        ->and($choice->shownNowPreviewUrl)->toBeNull()
        ->and($broken->fingerprint)->not->toBe($absent->fingerprint);
});

it('renders broken configured settings media as labeled safe evidence', function (string $locale): void {
    app()->setLocale($locale);
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'header/missing-logo-<unsafe>.png';
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $missingPath,
                'light_media_reference_key' => $missingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSee('data-testid="owner-image-broken-state"', false)
        ->assertSee(__('admin.owner_image.broken_configured_heading'))
        ->assertSee(__('admin.owner_image.broken_configured_body'))
        ->assertSee(__('admin.owner_image.broken_configured_reference'))
        ->assertSee(__('admin.owner_image.broken_configured_path'))
        ->assertSee($missingReferenceKey)
        ->assertSee('header/missing-logo-&lt;unsafe&gt;.png', false)
        ->assertDontSee($missingPath, false)
        ->assertDontSee(__('admin.owner_image.actions.open_details'));
})->with(['he', 'en']);

it('keeps pending replacement capabilities out of a broken owner card', function (string $locale): void {
    app()->setLocale($locale);
    Storage::fake('public');
    $missingReferenceKey = (string) Str::ulid();
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'pending-broken-card-replacement',
        'path' => 'header/pending-broken-card-replacement.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($replacement->path, 'replacement');
    $replacementProjection = app(MediaRecordProjector::class)->project(
        $replacement,
        withOwnerDetails: true,
    );
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => 'header/missing-pending-owner.png',
                'light_media_reference_key' => $missingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $replacement->getKey(),
            'mediaIds' => [$replacement->getKey()],
        ]])
        ->call('$refresh')
        ->assertSee('data-testid="owner-image-broken-state"', false)
        ->assertDontSee($replacement->reference_key)
        ->assertDontSee(__('admin.owner_image.actions.open_details'));
    if (is_string($replacementProjection['details_url'] ?? null)) {
        $component->assertDontSee($replacementProjection['details_url'], false);
    }
    if (is_string($replacementProjection['preview_url'] ?? null)) {
        $component->assertDontSee($replacementProjection['preview_url'], false);
    }
    $refreshedPicker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($refreshedPicker instanceof PathCuratorPicker);

    $presentation = $refreshedPicker->getOwnerChoicePresentation();
    expect($presentation?->directState)->toBe('broken')
        ->and($presentation?->pendingMedia['reference_key'] ?? null)->toBe($replacement->reference_key)
        ->and($presentation?->pendingMedia['label'] ?? null)->not->toBe('')
        ->and($presentation?->pendingMedia['preview_url'] ?? null)->toBeNull()
        ->and($presentation?->pendingMedia['details_url'] ?? null)->toBeNull();
})->with(['he', 'en']);

it('fingerprints a malformed configured menu logo as broken without exposing its raw value', function (): void {
    $presenter = app(SettingsOwnerImagePresenter::class);
    $malformed = $presenter->snapshot([
        'menu_config' => [
            'logo' => [
                'light_path' => null,
                'light_media_reference_key' => ['unexpected' => 42],
            ],
        ],
    ], SettingsSubjectOwnershipRegistry::MENU_HEADER);
    $absent = $presenter->snapshot([
        'menu_config' => [
            'logo' => [
                'light_path' => null,
                'light_media_reference_key' => null,
            ],
        ],
    ], SettingsSubjectOwnershipRegistry::MENU_HEADER);
    $choice = $presenter->choice(
        $malformed,
        'menu_config.logo.light_media_reference_key',
        null,
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
    );

    expect($choice->directState)->toBe('broken')
        ->and($choice->savedReferenceKey)->toBeNull()
        ->and($choice->expectedLegacyPath)->toBeNull()
        ->and($choice->canChooseAutomatic)->toBeFalse()
        ->and($choice->directMedia)->toBeNull()
        ->and($choice->shownNowMedia)->toBeNull()
        ->and($choice->shownNowPreviewUrl)->toBeNull()
        ->and($malformed->fingerprint)->not->toBe($absent->fingerprint);
});

it('preserves a malformed stored media identity through a disjoint menu save', function (): void {
    $malformedIdentity = ['unexpected' => 42];
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'enabled' => true,
            'logo' => [
                'light_path' => null,
                'light_media_reference_key' => $malformedIdentity,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSet('data.menu_config.logo.light_media_reference_key', null)
        ->assertSee('data-testid="owner-image-broken-state"', false)
        ->assertSee(__('admin.owner_image.broken_configured_evidence_hidden'))
        ->assertDontSee('unexpected');
    $openedEvidence = $component->get('settingsOpenedOwnerImageEvidence');
    $openedLightEvidence = $openedEvidence['menu_config.logo.light_media_reference_key'] ?? null;
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    expect($picker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($picker->getOwnerChoicePresentation()?->savedReferenceKey)->toBeNull()
        ->and($picker->getOwnerChoicePresentation()?->canChooseAutomatic)->toBeFalse();
    expect($openedLightEvidence)->toBeArray()
        ->and($openedLightEvidence['direct_state'] ?? null)->toBe('broken')
        ->and($openedLightEvidence['reference_key'] ?? null)->toBeNull()
        ->and($openedLightEvidence['legacy_path'] ?? null)->toBeNull()
        ->and($openedLightEvidence['evidence_hash'] ?? null)->toBeString()
        ->and(json_encode($openedLightEvidence))->not->toContain('unexpected');

    $component
        ->set('data.menu_config.enabled', false)
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['enabled'])->toBeFalse()
        ->and(app(PublicContentSettings::class)->menu_config['logo']['light_media_reference_key'])
        ->toBe($malformedIdentity);
});

it('refills opened broken owner evidence from the persisted concurrent merge before the next save', function (): void {
    $openedMissingReferenceKey = (string) Str::ulid();
    $openedMissingPath = 'header/opened-missing-logo.png';
    $freshMissingReferenceKey = (string) Str::ulid();
    $freshMissingPath = 'header/fresh-missing-logo.png';
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'enabled' => true,
            'logo' => [
                'light_path' => $openedMissingPath,
                'light_media_reference_key' => $openedMissingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
                'alt_text' => 'Opened label',
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $openedMissingReferenceKey,
        );

    saveStep9PublicFrontConfig([
        'menu_config' => [
            'enabled' => true,
            'logo' => [
                'light_path' => $freshMissingPath,
                'light_media_reference_key' => $freshMissingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
                'alt_text' => 'Fresh concurrent label',
            ],
        ],
    ]);

    $component
        ->set('data.menu_config.enabled', false)
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $openedMissingReferenceKey,
        )
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $freshMissingReferenceKey,
        );

    $openedEvidence = $component->get('settingsOpenedOwnerImageEvidence');
    $openedLightEvidence = $openedEvidence['menu_config.logo.light_media_reference_key'] ?? null;

    expect($openedLightEvidence)->toMatchArray([
        'direct_state' => 'broken',
        'reference_key' => $freshMissingReferenceKey,
        'legacy_path' => $freshMissingPath,
    ]);

    clearStep9PublicFrontSettingsCache();
    $savedMenuConfig = app(PublicContentSettings::class)->menu_config;
    expect($savedMenuConfig['enabled'])->toBeFalse()
        ->and(data_get($savedMenuConfig, 'logo.light_path'))->toBe($freshMissingPath)
        ->and(data_get($savedMenuConfig, 'logo.light_media_reference_key'))
        ->toBe($freshMissingReferenceKey)
        ->and(data_get($savedMenuConfig, 'logo.alt_text'))->toBe('Fresh concurrent label');

    $component
        ->set('data.menu_config.enabled', true)
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $freshMissingReferenceKey,
        );

    clearStep9PublicFrontSettingsCache();
    $resavedMenuConfig = app(PublicContentSettings::class)->menu_config;
    expect($resavedMenuConfig['enabled'])->toBeTrue()
        ->and(data_get($resavedMenuConfig, 'logo.light_path'))->toBe($freshMissingPath)
        ->and(data_get($resavedMenuConfig, 'logo.light_media_reference_key'))
        ->toBe($freshMissingReferenceKey)
        ->and(data_get($resavedMenuConfig, 'logo.alt_text'))->toBe('Fresh concurrent label');
});

it('keeps valid or absent opened owner evidence authoritative until successful refill', function (string $openedState): void {
    Storage::fake('public');
    $makeMedia = function (string $name): Media {
        $media = Media::factory()->create([
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'visibility' => 'public',
            'directory' => 'header',
            'name' => $name,
            'path' => "header/{$name}.png",
            'type' => 'image/png',
            'ext' => 'png',
            'size' => 20,
            'width' => 100,
            'height' => 100,
        ]);
        Storage::disk('public')->put($media->path, $name);

        return $media;
    };
    $openedMedia = $openedState === 'valid' ? $makeMedia('opened-owner-a') : null;
    $pendingMedia = $makeMedia("pending-owner-c-{$openedState}");
    $freshMedia = $makeMedia("fresh-owner-b-{$openedState}");
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'enabled' => true,
            'logo' => [
                'light_path' => $openedMedia?->path,
                'light_media_reference_key' => $openedMedia?->reference_key,
                'dark_path' => null,
                'dark_media_reference_key' => null,
                'alt_text' => 'Opened label',
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class);
    $findLightPicker = fn (): mixed => collect(
        $component->instance()->getSchema('form')->getFlatComponents(
            withActions: false,
            withHidden: true,
            withAbsoluteKeys: true,
        ),
    )->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    $picker = $findLightPicker();
    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $pendingMedia->getKey(),
            'mediaIds' => [$pendingMedia->getKey()],
        ]])
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $pendingMedia->getKey(),
        );

    saveStep9PublicFrontConfig([
        'menu_config' => [
            'enabled' => true,
            'logo' => [
                'light_path' => $freshMedia->path,
                'light_media_reference_key' => $freshMedia->reference_key,
                'dark_path' => null,
                'dark_media_reference_key' => null,
                'alt_text' => 'Fresh concurrent label',
            ],
        ],
    ]);

    $component->call('$refresh');
    $refreshedPicker = $findLightPicker();
    assert($refreshedPicker instanceof PathCuratorPicker);
    $openedEvidence = $component->get('settingsOpenedOwnerImageEvidence');
    $openedLightEvidence = $openedEvidence['menu_config.logo.light_media_reference_key'] ?? null;
    $presentation = $refreshedPicker->getOwnerChoicePresentation();

    expect($openedLightEvidence)->toMatchArray([
        'direct_state' => $openedState === 'valid' ? 'present' : 'absent',
        'reference_key' => $openedMedia?->reference_key,
        'legacy_path' => $openedMedia?->path,
        'resolved_media_id' => $openedMedia?->getKey(),
    ])
        ->and($presentation?->directState)->toBe($openedState === 'valid' ? 'present' : 'absent')
        ->and($presentation?->savedMediaId)->toBe($openedMedia?->getKey())
        ->and($presentation?->savedReferenceKey)->toBe($openedMedia?->reference_key)
        ->and($presentation?->expectedLegacyPath)->toBe($openedMedia?->path)
        ->and($presentation?->directMedia['id'] ?? null)->toBe($openedMedia?->getKey())
        ->and($presentation?->shownNowMedia['id'] ?? null)->toBe($freshMedia->getKey())
        ->and($presentation?->pendingMedia['id'] ?? null)->toBe($pendingMedia->getKey());

    $component
        ->call(
            'callSchemaComponentMethod',
            $refreshedPicker->getKey(),
            'restoreSavedOwnerSelection',
            [[]],
        )
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $openedMedia?->getKey(),
        )
        ->set('data.menu_config.enabled', false)
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $freshMedia->getKey(),
        );

    clearStep9PublicFrontSettingsCache();
    $savedMenuConfig = app(PublicContentSettings::class)->menu_config;
    expect(data_get($savedMenuConfig, 'enabled'))->toBeFalse()
        ->and(data_get($savedMenuConfig, 'logo.light_path'))->toBe($freshMedia->path)
        ->and(data_get($savedMenuConfig, 'logo.light_media_reference_key'))
        ->toBe($freshMedia->reference_key)
        ->and(data_get($savedMenuConfig, 'logo.alt_text'))->toBe('Fresh concurrent label');

    $refilledEvidence = $component->get('settingsOpenedOwnerImageEvidence');
    $refilledLightEvidence = $refilledEvidence['menu_config.logo.light_media_reference_key'] ?? null;
    $refilledPicker = $findLightPicker();
    assert($refilledPicker instanceof PathCuratorPicker);
    expect($refilledLightEvidence)->toMatchArray([
        'direct_state' => 'present',
        'reference_key' => $freshMedia->reference_key,
        'legacy_path' => $freshMedia->path,
        'resolved_media_id' => $freshMedia->getKey(),
    ])
        ->and($refilledPicker->getOwnerChoicePresentation()?->savedMediaId)
        ->toBe($freshMedia->getKey())
        ->and($refilledPicker->getOwnerChoicePresentation()?->savedReferenceKey)
        ->toBe($freshMedia->reference_key)
        ->and($refilledPicker->getOwnerChoicePresentation()?->directMedia['id'] ?? null)
        ->toBe($freshMedia->getKey())
        ->and($refilledPicker->getOwnerChoicePresentation()?->shownNowMedia['id'] ?? null)
        ->toBe($freshMedia->getKey());
})->with(['valid', 'absent']);

it('rejects a forged broken identity that differs from the saved owner evidence', function (): void {
    $savedMissingReferenceKey = (string) Str::ulid();
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => 'header/saved-missing-owner-evidence.png',
                'light_media_reference_key' => $savedMissingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $savedMissingReferenceKey,
        )
        ->set(
            'data.menu_config.logo.light_media_reference_key',
            (string) Str::ulid(),
        )
        ->assertStatus(404);
});

it('restores exact broken saved owner evidence after a real pending replacement', function (): void {
    Storage::fake('public');
    $savedMissingReferenceKey = (string) Str::ulid();
    $savedMissingPath = 'header/saved-missing-owner-evidence-to-restore.png';
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'pending-replacement-before-restore',
        'path' => 'header/pending-replacement-before-restore.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($replacement->path, 'replacement');
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $savedMissingPath,
                'light_media_reference_key' => $savedMissingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $replacement->getKey(),
            'mediaIds' => [$replacement->getKey()],
        ]])
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $replacement->getKey(),
        )
        ->call('$refresh')
        ->assertSee('data-testid="owner-image-restore-saved"', false);
    $refreshedPicker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($refreshedPicker instanceof PathCuratorPicker);

    expect($refreshedPicker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($refreshedPicker->getOwnerChoicePresentation()?->savedReferenceKey)
        ->toBe($savedMissingReferenceKey);

    $component
        ->call('callSchemaComponentMethod', $refreshedPicker->getKey(), 'restoreSavedOwnerSelection', [[]])
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $savedMissingReferenceKey,
        )
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep9PublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo'])
        ->toMatchArray([
            'light_path' => $savedMissingPath,
            'light_media_reference_key' => $savedMissingReferenceKey,
        ]);
});

it('restores the exact opened legacy path instead of fresh concurrent owner evidence', function (): void {
    Storage::fake('public');
    $openedMissingPath = 'header/opened-missing-legacy-logo.png';
    $freshMissingReferenceKey = (string) Str::ulid();
    $freshMissingPath = 'header/fresh-concurrent-missing-logo.png';
    $replacement = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'pending-replacement-before-concurrent-restore',
        'path' => 'header/pending-replacement-before-concurrent-restore.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($replacement->path, 'replacement');
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $openedMissingPath,
                'light_media_reference_key' => null,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $replacement->getKey(),
            'mediaIds' => [$replacement->getKey()],
        ]])
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $replacement->getKey(),
        );

    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $freshMissingPath,
                'light_media_reference_key' => $freshMissingReferenceKey,
                'dark_path' => null,
                'dark_media_reference_key' => null,
            ],
        ],
    ]);

    $component->call('$refresh');
    $refreshedPicker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($refreshedPicker instanceof PathCuratorPicker);

    expect($refreshedPicker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($refreshedPicker->getOwnerChoicePresentation()?->savedReferenceKey)->toBeNull()
        ->and($refreshedPicker->getOwnerChoicePresentation()?->expectedLegacyPath)
        ->toBe($openedMissingPath);

    $component
        ->call('callSchemaComponentMethod', $refreshedPicker->getKey(), 'restoreSavedOwnerSelection', [[]])
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $openedMissingPath,
        )
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet(
            'data.menu_config.logo.light_media_reference_key',
            $freshMissingReferenceKey,
        );

    clearStep9PublicFrontSettingsCache();
    expect(app(PublicContentSettings::class)->menu_config['logo'])
        ->toMatchArray([
            'light_path' => $freshMissingPath,
            'light_media_reference_key' => $freshMissingReferenceKey,
        ]);
});

it('keeps non-owner pickers on normal trusted identity resolution', function (): void {
    $group = ContentGroup::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->set('data.cover_media_reference_key', (string) Str::ulid())
        ->assertStatus(404);
});

it('keeps unsaved settings through nested picker cancel and discards them on reload without deleting admitted media', function (): void {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $saved = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'saved-logo',
        'path' => 'header/saved-logo.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($saved->path, 'saved');
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $saved->path,
                'light_media_reference_key' => $saved->reference_key,
                'alt_text' => 'Saved label',
            ],
        ],
    ]);

    $component = Livewire::actingAs($admin)->test(MenuHeaderSettings::class);
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    $component
        ->set('data.menu_config.logo.alt_text', 'Unsaved label')
        ->mountAction(TestAction::make('launchPanel')->schemaComponent(
            (string) str($picker->getKey())->after('.'),
        ))
        ->assertActionMounted();
    $nestedPicker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(
        withHidden: true,
    ))->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof LivewireSchemaComponent);
    assert($nestedPicker instanceof LivewireSchemaComponent);
    expect($nestedPicker->getComponent())->toBe(MediaPickerPanel::class);
    $encoded = file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'));
    $upload = UploadedFile::fake()->createWithContent(
        'settings-logo.jpg',
        base64_decode((string) $encoded, true),
    );

    Livewire::actingAs($admin)
        ->test(MediaPickerPanel::class, $nestedPicker->getData())
        ->call('activateSource', 'upload')
        ->fillForm(['uploads' => [$upload]])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertDispatched('insert-media');

    $admitted = Media::query()->whereKeyNot($saved->getKey())->sole();
    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $admitted->getKey(),
            'mediaIds' => [$admitted->getKey()],
        ]])
        ->call('callSchemaComponentMethod', $picker->getKey(), 'closePicker')
        ->assertSet('mountedActions', [])
        ->assertSet('data.menu_config.logo.alt_text', 'Unsaved label')
        ->assertSet('data.menu_config.logo.light_media_reference_key', $admitted->getKey());

    clearStep9PublicFrontSettingsCache();
    expect(app(PublicContentSettings::class)->menu_config['logo'])
        ->toMatchArray([
            'alt_text' => 'Saved label',
            'light_media_reference_key' => $saved->reference_key,
        ]);

    Livewire::actingAs($admin)
        ->test(MenuHeaderSettings::class)
        ->assertSet('data.menu_config.logo.alt_text', 'Saved label')
        ->assertSet('data.menu_config.logo.light_media_reference_key', $saved->getKey());

    expect(Media::query()->whereKey($admitted)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($admitted->path);
});

it('renders settings image state with localized labels and semantic content direction', function (string $locale): void {
    app()->setLocale($locale);
    Storage::fake('public');
    $media = Media::factory()->create([
        'title' => 'לוגו Logo <unsafe>',
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'visibility' => 'public',
        'directory' => 'header',
        'name' => 'semantic-logo',
        'path' => 'header/semantic-logo.png',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 20,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($media->path, 'semantic');
    saveStep9PublicFrontConfig([
        'menu_config' => [
            'logo' => [
                'light_path' => $media->path,
                'light_media_reference_key' => $media->reference_key,
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSee(__('admin.sections.public_menu_logo'))
        ->assertSee('data-testid="owner-image-choice-state"', false)
        ->assertSee('לוגו Logo &lt;unsafe&gt;', false)
        ->assertDontSee('לוגו Logo <unsafe>', false);
})->with(['he', 'en']);

it('normalizes menu config and skips disabled, missing, and non-https menu targets', function (): void {
    saveStep9PublicFrontConfig([
        'public_forms' => step9PublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                [
                    'key' => 'home',
                    'type' => 'route',
                    'label' => 'Home',
                    'route_key' => 'home',
                    'visible' => true,
                    'sort' => 10,
                ],
                [
                    'key' => 'request',
                    'type' => 'public_form',
                    'label' => 'Request',
                    'form_key' => 'request_transcription',
                    'display_mode' => 'slide_over',
                    'visible' => true,
                    'sort' => 20,
                ],
                [
                    'key' => 'disabled',
                    'type' => 'public_form',
                    'label' => 'Disabled',
                    'form_key' => 'disabled_form',
                    'visible' => true,
                    'sort' => 30,
                ],
                [
                    'key' => 'missing',
                    'type' => 'public_form',
                    'label' => 'Missing',
                    'form_key' => 'missing_form',
                    'visible' => true,
                    'sort' => 40,
                ],
                [
                    'key' => 'bad_external',
                    'type' => 'external_url',
                    'label' => 'Bad external',
                    'external_url' => 'http://example.test',
                    'visible' => true,
                    'sort' => 50,
                ],
                [
                    'key' => 'theme',
                    'type' => 'theme_selector',
                    'visible' => true,
                    'sort' => 60,
                ],
            ],
            'theme_selector' => [
                'enabled' => true,
                'mode' => 'light_dark_system',
            ],
        ],
    ]);

    $menu = app(PublicMenuConfigReader::class)->read();

    expect(collect($menu['items'])->pluck('key')->all())->toBe(['home', 'request', 'theme'])
        ->and($menu['form_mounts'])->toBe([
            [
                'form_key' => 'request_transcription',
                'display_mode' => 'slide_over',
            ],
        ]);

    $result = app(PublicFrontConfigValidator::class)->validate([
        'menu_config' => [
            'enabled' => true,
            'items' => [
                [
                    'type' => 'external_url',
                    'external_url' => 'http://example.test',
                ],
            ],
        ],
    ]);

    expect(collect($result->invalidConfig())->pluck('path')->all())->toContain('menu_config.items.0.external_url');
});

it('renders the public header menu, form actions, logo, and theme selector', function (): void {
    saveStep9PublicFrontConfig([
        'public_forms' => step9PublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'home', 'type' => 'route', 'route_key' => 'home', 'label' => 'Home', 'visible' => true, 'sort' => 10],
                ['key' => 'podcasts', 'type' => 'route', 'route_key' => 'podcasts', 'label' => 'Podcasts', 'visible' => true, 'sort' => 20],
                ['key' => 'about', 'type' => 'route', 'route_key' => 'about', 'label' => 'About', 'visible' => true, 'sort' => 30],
                ['key' => 'request', 'type' => 'public_form', 'form_key' => 'request_transcription', 'label' => 'Request transcription', 'display_mode' => 'modal', 'visible' => true, 'sort' => 40],
                ['key' => 'volunteer', 'type' => 'public_form', 'form_key' => 'volunteer_transcriber', 'label' => 'Register as transcriber', 'display_mode' => 'slide_over', 'visible' => true, 'sort' => 50],
                ['key' => 'theme', 'type' => 'theme_selector', 'visible' => true, 'sort' => 60],
            ],
            'theme_selector' => ['enabled' => true, 'mode' => 'light_dark_system'],
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-test="public-header"', false)
        ->assertSee('data-test="public-header-logo"', false)
        ->assertSee('images/podtext-logo.jpg', false)
        ->assertSee('Home')
        ->assertSee('Podcasts')
        ->assertSee('About')
        ->assertSee('Request transcription')
        ->assertSee('Register as transcriber')
        ->assertSee('open-public-form', false)
        ->assertSee('data-form-key="request_transcription"', false)
        ->assertSee('data-test="public-theme-selector"', false);
});

it('renders about team images, card settings, and explicit heading typography classes', function (): void {
    saveStep9PublicFrontConfig([
        'about_page' => [
            'enabled' => true,
            'title' => 'About Step 9',
            'kicker' => 'About',
            'description' => 'Safe description',
            'settings' => [
                'team_heading' => 'Team',
                'team_description' => 'Team intro',
                'team_layout' => 'grid',
                'team_card' => [
                    'show_image' => true,
                    'image_size' => 'large',
                    'layout' => 'list',
                    'density' => 'compact',
                    'show_title' => false,
                    'show_description' => false,
                    'description_lines' => 2,
                ],
            ],
            'blocks' => [
                [
                    'key' => 'headings',
                    'type' => 'markdown',
                    'visible' => true,
                    'sort' => 10,
                    'content' => "# H1 from markdown\n\n## H2 from markdown\n\n### H3 from markdown",
                ],
            ],
            'team_profiles' => [
                [
                    'key' => 'alice',
                    'visible' => true,
                    'sort' => 10,
                    'image_path' => 'team/alice.png',
                    'name' => 'Alice Step9',
                    'title' => 'Hidden title',
                    'description' => 'Hidden description',
                ],
            ],
        ],
    ]);

    $this->get('/about')
        ->assertSuccessful()
        ->assertSee('data-test="about-team-profile-image"', false)
        ->assertSee('/storage/team/alice.png', false)
        ->assertSee('data-team-card-layout="list"', false)
        ->assertSee('data-team-card-density="compact"', false)
        ->assertSee('data-team-card-image-size="large"', false)
        ->assertDontSee('>Hidden title<', false)
        ->assertDontSee('>Hidden description<', false)
        ->assertSee('[&amp;_h1]:text-3xl', false)
        ->assertSee('[&amp;_h2]:text-2xl', false)
        ->assertSee('[&amp;_h3]:text-xl', false);
});

it('shows compact contributor cards and a separate searchable preview row', function (): void {
    $alpha = Author::factory()->create(['name' => 'Alpha Contributor', 'slug' => 'alpha-contributor']);
    $zulu = Author::factory()->create(['name' => 'Zulu Contributor', 'slug' => 'zulu-contributor']);

    $needle = createStep9PublicItem($alpha, 'Needle Preview Item');
    createStep9PublicItem($alpha, 'Filtered Away Preview Item');
    createStep9PublicItem($zulu, 'Zulu Preview Item');

    Livewire::test(ContributorDirectory::class)
        ->assertSee('data-test="contributor-grid"', false)
        ->assertSee('data-test="contributor-preview"', false)
        ->assertSee('data-test="contributor-page-size"', false)
        ->assertSee('data-sort="name_asc"', false)
        ->assertSee('data-sort="name_desc"', false)
        ->assertSee('data-sort="count_desc"', false)
        ->assertSee('data-sort="count_asc"', false)
        ->assertSee('data-test="public-transcriptions-count"', false)
        ->assertDontSee('data-test="contributor-link"', false)
        ->set('perPage', 15)
        ->assertSet('perPage', 15)
        ->set('sort', 'name_asc')
        ->assertSet('sort', 'name_asc')
        ->call('selectContributor', $alpha->id)
        ->assertSet('selectedContributorId', $alpha->id)
        ->assertSee('data-test="selected-contributor-link"', false)
        ->assertSee("contributors/{$alpha->slug}", false)
        ->assertSee('data-test="contributor-preview-search"', false)
        ->set('previewSearch', 'Needle')
        ->assertSee($needle->title)
        ->assertDontSee('Filtered Away Preview Item');
});

it('suppresses homepage discovery chrome while keeping section header actions and content blocks', function (): void {
    $author = Author::factory()->create();
    createStep9PublicItem($author, 'Homepage Latest Step9');

    HomepageSection::factory()->create([
        'name' => 'Latest Step9',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 10,
        'source_config' => [
            'source_type' => 'latest_content_items',
            'total_limit' => 50,
        ],
        'display_config' => [
            'heading' => 'Latest Step9',
            'show_view_all_link' => true,
        ],
        'pagination_config' => [
            'mode' => 'next_previous',
            'per_page' => 4,
            'total_limit' => 50,
        ],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Content Block Step9',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 20,
        'source_config' => [
            'source_type' => 'content_block',
        ],
        'display_config' => [
            'heading' => 'Content Block Step9',
            'body' => '# Safe block heading',
            'content_style' => 'callout',
            'button_label' => 'About PodText',
            'button_route_key' => 'about',
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('data-test="discovery-chrome"', false)
        ->assertDontSee('data-test="item-search"', false)
        ->assertSee('data-test="homepage-section-header"', false)
        ->assertSee('data-test="latest-search"', false)
        ->assertSee('data-test="homepage-section-view-more"', false)
        ->assertSee('data-test="latest-next-previous"', false)
        ->assertSee('data-test="homepage-content-block"', false)
        ->assertSee('Safe block heading')
        ->assertSee('About PodText');
});

it('does not introduce menu models or a cms/page model layer', function (): void {
    expect(class_exists('App\\Models\\PublicMenu'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicMenuItem'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicFormDefinition'))->toBeFalse()
        ->and(class_exists('App\\Models\\Podcast'))->toBeFalse()
        ->and(class_exists('App\\Models\\Episode'))->toBeFalse()
        ->and(class_exists('App\\Models\\TeamProfile'))->toBeFalse()
        ->and(class_exists('App\\Models\\Page'))->toBeFalse();
});
