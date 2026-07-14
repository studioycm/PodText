<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\ContributorSettings;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\EpisodePageSettings;
use App\Filament\Pages\HomepageSettings;
use App\Filament\Pages\MaintenanceSettings;
use App\Filament\Pages\ManagePublicForms;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Pages\PodcastSettings;
use App\Filament\Pages\PublicContentSettings as LegacyPublicContentSettings;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\Settings\SettingsSp3bSubjectFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
    Http::preventStrayRequests();
    Mail::fake();
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @return array<class-string, string>
 */
function settingsSp3bEditablePages(): array
{
    return [
        HomepageSettings::class => SettingsSubjectOwnershipRegistry::HOMEPAGE,
        DisplaySettings::class => SettingsSubjectOwnershipRegistry::DISPLAY,
        EpisodePageSettings::class => SettingsSubjectOwnershipRegistry::EPISODE_PAGE,
        MenuHeaderSettings::class => SettingsSubjectOwnershipRegistry::MENU_HEADER,
        PodcastSettings::class => SettingsSubjectOwnershipRegistry::PODCASTS,
        ContributorSettings::class => SettingsSubjectOwnershipRegistry::CONTRIBUTORS,
        AboutSettings::class => SettingsSubjectOwnershipRegistry::ABOUT,
        MaintenanceSettings::class => SettingsSubjectOwnershipRegistry::MAINTENANCE,
        ManagePublicForms::class => SettingsSubjectOwnershipRegistry::PUBLIC_FORMS,
        CardTemplateSettings::class => SettingsSubjectOwnershipRegistry::CARD_TEMPLATES,
    ];
}

function clearSettingsSp3bState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function settingsSp3bCanonicalJson(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

it('classifies every public content setting property exactly once without using the registry as the source of truth', function (): void {
    $properties = collect((new ReflectionClass(PublicContentSettings::class))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->reject(fn (ReflectionProperty $property): bool => $property->isStatic())
        ->map(fn (ReflectionProperty $property): string => $property->getName())
        ->sort()
        ->values()
        ->all();

    $classified = collect(SettingsSubjectOwnershipRegistry::all())
        ->flatMap(fn (array $definition): array => $definition['properties'])
        ->sort()
        ->values()
        ->all();

    expect($classified)->toBe($properties)
        ->and($classified)->toHaveCount(count(array_unique($classified)));
});

it('fills each editable page with only its registry-owned roots', function (): void {
    $adminUx = app(AdminUxSettings::class);
    $adminUx->transcription_mode = TranscriptionMode::Multi->value;
    $adminUx->save();
    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
    $this->actingAs(User::factory()->superAdmin()->create());

    foreach (settingsSp3bEditablePages() as $page => $subject) {
        $component = Livewire::test($page);

        expect(collect(array_keys($component->instance()->form->getState(false)))->sort()->values()->all())
            ->toBe(collect(SettingsSubjectOwnershipRegistry::ownedProperties($subject))->sort()->values()->all());
    }
});

it('allows only administrators and super administrators to access every shared-contract page', function (): void {
    auth()->logout();

    foreach (array_keys(settingsSp3bEditablePages()) as $page) {
        expect($page::canAccess())->toBeFalse();
    }

    $this->actingAs(User::factory()->role(UserRole::User)->create());

    foreach (array_keys(settingsSp3bEditablePages()) as $page) {
        expect($page::canAccess())->toBeFalse();
    }

    $this->actingAs(User::factory()->admin()->create());

    foreach (array_keys(settingsSp3bEditablePages()) as $page) {
        expect($page::canAccess())->toBeTrue();
    }

    $this->actingAs(User::factory()->superAdmin()->create());

    foreach (array_keys(settingsSp3bEditablePages()) as $page) {
        expect($page::canAccess())->toBeTrue();
    }
});

it('enforces shared-contract page access through the admin routes for every role tier', function (): void {
    auth()->logout();

    foreach (array_keys(settingsSp3bEditablePages()) as $page) {
        $this->get($page::getUrl())->assertRedirect('/admin/login');
    }

    $this->actingAs(User::factory()->role(UserRole::User)->create());

    foreach (array_keys(settingsSp3bEditablePages()) as $page) {
        $this->get($page::getUrl())->assertForbidden();
    }

    foreach ([User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $user) {
        $this->actingAs($user);

        foreach (array_keys(settingsSp3bEditablePages()) as $page) {
            $this->get($page::getUrl())->assertOk();
        }
    }
});

it('preserves sequential stale saves from independent owned pages after settings and render caches are warm', function (): void {
    app(PublicContentSettings::class);
    app(PublicFrontConfigReader::class)->read();

    $staleHomepage = Livewire::test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 31)
        ->set('data.pinned_item_limit', 6)
        ->set('data.show_latest_section', false);

    Livewire::test(DisplaySettings::class)
        ->set('data.display_defaults.layout', 'rows')
        ->call('save')
        ->assertHasNoFormErrors();

    $staleHomepage
        ->call('save')
        ->assertHasNoFormErrors();

    clearSettingsSp3bState();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(31)
        ->and(app(PublicContentSettings::class)->display_defaults['layout'])->toBe('rows')
        ->and(app(PublicFrontConfigReader::class)->read()->group('display_defaults')['layout'])->toBe('rows');
});

it('ignores forged foreign roots while preserving their fresh stored value', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->default_public_sort = 'latest_transcription';
    $settings->save();

    Livewire::test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 22)
        ->set('data.default_public_sort', 'title_desc')
        ->call('save')
        ->assertHasNoFormErrors();

    clearSettingsSp3bState();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(22)
        ->and(app(PublicContentSettings::class)->default_public_sort)->toBe('latest_transcription');
});

it('changes only the owner roots and preserves each non-owned decoded and canonical JSON value', function (): void {
    $before = app(PublicContentSettings::class)->toArray();

    Livewire::test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 23)
        ->set('data.pinned_item_limit', 5)
        ->set('data.show_latest_section', false)
        ->call('save')
        ->assertHasNoFormErrors();

    clearSettingsSp3bState();

    $after = app(PublicContentSettings::class)->toArray();

    expect($after['homepage_item_limit'])->toBe(23)
        ->and($after['pinned_item_limit'])->toBe(5)
        ->and($after['show_latest_section'])->toBeFalse();

    foreach (array_diff(array_keys($before), SettingsSubjectOwnershipRegistry::ownedProperties(SettingsSubjectOwnershipRegistry::HOMEPAGE)) as $property) {
        expect($after[$property])->toBe($before[$property])
            ->and(settingsSp3bCanonicalJson($after[$property]))->toBe(settingsSp3bCanonicalJson($before[$property]));
    }
});

it('dispatches SettingsSaved exactly once for one ordinary subject-page save', function (): void {
    Event::fake([SettingsSaved::class]);

    Livewire::test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 24)
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertDispatchedTimes(SettingsSaved::class, 1);
});

it('redirects every legacy settings tab to its focused page', function (?string $tab, string $page): void {
    $parameters = $tab === null ? [] : ['public-content-tab' => $tab];

    $this->get(LegacyPublicContentSettings::getUrl($parameters))
        ->assertRedirect($page::getUrl());
})->with([
    'homepage' => ['homepage', HomepageSettings::class],
    'display' => ['display', DisplaySettings::class],
    'item page' => ['item-page', EpisodePageSettings::class],
    'menu header' => ['menu-header', MenuHeaderSettings::class],
    'podcasts' => ['podcasts', PodcastSettings::class],
    'contributors' => ['contributors', ContributorSettings::class],
    'about' => ['about', AboutSettings::class],
    'maintenance' => ['maintenance', MaintenanceSettings::class],
    'advanced' => ['advanced', CardTemplateSettings::class],
    'missing' => [null, HomepageSettings::class],
    'unknown' => ['unknown', HomepageSettings::class],
]);

it('forwards only approved legacy measurement query parameters and rejects unauthorized saves before persistence', function (): void {
    $this->get(LegacyPublicContentSettings::getUrl([
        'public-content-tab' => 'display',
        'sp3a_measure' => '1',
        'sp3a_profile' => '0',
        'sp3b_subject_fixture' => 'item-page',
        'unapproved' => 'discard-me',
    ]))
        ->assertRedirect(DisplaySettings::getUrl([
            'sp3a_measure' => '1',
            'sp3b_subject_fixture' => 'item-page',
        ]));

    $component = Livewire::test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 99);

    $this->actingAs(User::factory()->role(UserRole::User)->create());

    $component
        ->call('save')
        ->assertForbidden();

    clearSettingsSp3bState();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->not->toBe(99);
});

it('keeps the relocated public forms lock surfaces available at their focused component paths', function (): void {
    Livewire::test(ManagePublicForms::class)
        ->assertActionExists(TestAction::make('toggleImportLockGroup_public_front_forms')
            ->schemaComponent('public-settings-lock-section-public-front-forms', 'form'))
        ->assertActionExists(TestAction::make('toggleImportLockUnit_public_forms_require_email_verification')
            ->schemaComponent('public-settings-lock-section-public-front-forms.public_forms.require_email_verification', 'form'));
});

it('provides keyed canaries only for the four measured subject pages', function (): void {
    $fixture = app(SettingsSp3bSubjectFixture::class);

    expect($fixture->payload(SettingsSubjectOwnershipRegistry::EPISODE_PAGE, SettingsSubjectOwnershipRegistry::EPISODE_PAGE))
        ->toHaveKey('item_page')
        ->and($fixture->payload(SettingsSubjectOwnershipRegistry::MENU_HEADER, SettingsSubjectOwnershipRegistry::MENU_HEADER))
        ->toHaveKey('menu_config')
        ->and($fixture->payload(SettingsSubjectOwnershipRegistry::ABOUT, SettingsSubjectOwnershipRegistry::ABOUT))
        ->toHaveKey('about_page')
        ->and($fixture->payload(SettingsSubjectOwnershipRegistry::PUBLIC_FORMS, SettingsSubjectOwnershipRegistry::PUBLIC_FORMS))
        ->toHaveKey('public_forms')
        ->and($fixture->payload(SettingsSubjectOwnershipRegistry::DISPLAY, 'unknown'))->toBe([]);
});

it('refuses measured subject-page saves without changing stored settings', function (): void {
    $environment = app()->environment();
    $settings = app(PublicContentSettings::class);
    $settings->item_page_layout = 'standard';
    $settings->save();
    clearSettingsSp3bState();

    app()->detectEnvironment(fn (): string => 'local');

    try {
        foreach ([
            EpisodePageSettings::class => SettingsSubjectOwnershipRegistry::EPISODE_PAGE,
            MenuHeaderSettings::class => SettingsSubjectOwnershipRegistry::MENU_HEADER,
            AboutSettings::class => SettingsSubjectOwnershipRegistry::ABOUT,
            ManagePublicForms::class => SettingsSubjectOwnershipRegistry::PUBLIC_FORMS,
        ] as $page => $subject) {
            Livewire::withQueryParams([
                'sp3a_measure' => '1',
                'sp3b_subject_fixture' => $subject,
            ])
                ->test($page)
                ->assertOk();
        }

        Livewire::withQueryParams([
            'sp3a_measure' => '1',
            'sp3b_subject_fixture' => SettingsSubjectOwnershipRegistry::EPISODE_PAGE,
        ])
            ->test(EpisodePageSettings::class)
            ->set('data.item_page_layout', 'media_first')
            ->call('save')
            ->assertHasNoFormErrors();
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
    }

    clearSettingsSp3bState();

    expect(app(PublicContentSettings::class)->item_page_layout)->toBe('standard');
});
