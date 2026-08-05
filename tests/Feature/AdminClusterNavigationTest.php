<?php

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Clusters\SystemCluster;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\MaintenanceSettings;
use App\Filament\Pages\ManagePublicForms;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    $this->actingAs(User::factory()->admin()->create());
});

it('serves both clusters in the tools group after the labeled groups', function (): void {
    $navigation = collect(Filament::getNavigation());

    // R1 EQ-1 (2026-08-05): episodes left this block for the ungrouped front
    // door, so taxonomy leads and the content group trails it. The clusters
    // sit in the tools group rather than a second ungrouped run — native
    // Filament sorts ungrouped items first and offers no override, so a block
    // that must come last has to carry a name.
    expect($navigation->map(fn ($group): ?string => $group->getLabel())->values()->all())->toBe([
        null,
        __('admin.navigation.groups.taxonomy_management'),
        __('admin.navigation.groups.content_management'),
        __('admin.navigation.groups.tools_and_system'),
    ]);

    $leadingLabels = collect($navigation->first()->getItems())
        ->map(fn ($item): string => (string) $item->getLabel())
        ->values();
    $trailingLabels = collect($navigation->last()->getItems())
        ->map(fn ($item): string => (string) $item->getLabel())
        ->values();

    expect($leadingLabels->last())->toBe(__('admin.curator.plural_label'))
        ->and($trailingLabels->all())->toBe([
            __('admin.tools.pages.tools.navigation'),
            __('admin.spotify_fetcher.pages.navigation'),
            SettingsCluster::getNavigationLabel(),
            SystemCluster::getNavigationLabel(),
            __('admin.navigation.public_homepage'),
        ]);
});

it('routes cluster members under the cluster prefixes', function (): void {
    expect(SettingsCluster::getUrl())->toEndWith('/admin/settings')
        ->and(SystemCluster::getUrl())->toEndWith('/admin/system')
        ->and(DisplaySettings::getUrl())->toEndWith('/admin/settings/display')
        ->and(ManagePublicForms::getUrl())->toEndWith('/admin/settings/manage-public-forms')
        ->and(MaintenanceSettings::getUrl())->toEndWith('/admin/system/maintenance')
        ->and(UserResource::getUrl())->toEndWith('/admin/system/users');
});

it('renders the settings pages with the ten-tab top sub-navigation', function (): void {
    $response = $this->get(DisplaySettings::getUrl())->assertSuccessful();

    $response->assertSee('fi-page-sub-navigation-tabs', false);

    foreach ([
        __('admin.pages.homepage_settings.navigation'),
        __('admin.resources.homepage_section.navigation'),
        __('admin.pages.podcast_settings.navigation'),
        __('admin.pages.episode_page_settings.navigation'),
        __('admin.pages.contributor_settings.navigation'),
        __('admin.pages.about_settings.navigation'),
        __('admin.pages.display_settings.navigation'),
        __('admin.pages.menu_header_settings.navigation'),
        __('admin.pages.manage_public_forms.navigation'),
        __('admin.pages.card_template_settings.navigation'),
    ] as $label) {
        $response->assertSee($label);
    }
});

it('renders the system pages with their top sub-navigation honoring the users gate', function (): void {
    $response = $this->get(MaintenanceSettings::getUrl())->assertSuccessful();

    $response->assertSee('fi-page-sub-navigation-tabs', false);

    foreach ([
        __('admin.pages.maintenance_settings.navigation'),
        __('admin.importer.pages.settings.navigation'),
        __('admin.resources.settings_backup.navigation'),
        __('admin.pages.admin_ux_settings.navigation'),
    ] as $label) {
        $response->assertSee($label);
    }

    $response->assertDontSee(__('admin.resources.user.navigation'));

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(MaintenanceSettings::getUrl())
        ->assertSuccessful()
        ->assertSee(__('admin.resources.user.navigation'));
});

it('resolves the admin panel url from another panel context while authenticated', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('public'));

    expect(Filament::getPanel('admin')->getUrl())->toContain('/admin')
        ->and(Filament::getCurrentPanel()?->getId())->toBe('public');
});
