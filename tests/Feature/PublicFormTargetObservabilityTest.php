<?php

use App\Enums\DashboardLens;
use App\Enums\HomepageSectionType;
use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Resources\HomepageSections\Pages\EditHomepageSection;
use App\Filament\Widgets\PublicFormTargetWarningsWidget;
use App\Models\HomepageSection;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Menu\PublicMenuItemTargetHealth;
use App\Support\PublicFront\PublicFormTargetStatus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function saveFormTargetConfig(array $config): void
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

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFormTargetStatus::class);
    app(SettingsContainer::class)->clearCache();
}

function formTargetPublicFormsConfig(): array
{
    return [
        'definitions' => [
            [
                'key' => 'request_transcription',
                'name' => 'Request transcription',
                'enabled' => true,
                'display_mode_default' => 'modal',
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                ],
                'settings' => ['rate_limit_attempts' => 5, 'rate_limit_decay_seconds' => 600],
            ],
            [
                'key' => 'disabled_form',
                'name' => 'Disabled form',
                'enabled' => false,
                'display_mode_default' => 'modal',
                'fields' => [],
                'settings' => ['rate_limit_attempts' => 5, 'rate_limit_decay_seconds' => 600],
            ],
        ],
    ];
}

it('marks disabled and not-yet-defined form targets in select options', function (): void {
    saveFormTargetConfig(['public_forms' => formTargetPublicFormsConfig()]);

    $options = app(PublicFormTargetStatus::class)->selectOptions();

    expect($options['request_transcription'])->toBe('Request transcription')
        ->and($options['disabled_form'])
        ->toBe('Disabled form '.__('admin.labels.public_form_target_disabled_suffix'))
        ->and($options['volunteer_transcriber'])
        ->toEndWith(' '.__('admin.labels.public_form_target_missing_suffix'));
});

it('reports warnings for missing and disabled form targets and stays quiet for healthy ones', function (): void {
    saveFormTargetConfig(['public_forms' => formTargetPublicFormsConfig()]);

    $status = app(PublicFormTargetStatus::class);

    expect($status->hasEnabledDefinition('request_transcription'))->toBeTrue()
        ->and($status->hasEnabledDefinition('disabled_form'))->toBeFalse()
        ->and($status->hasEnabledDefinition('missing_form'))->toBeFalse()
        ->and($status->warningFor('request_transcription'))->toBeNull()
        ->and($status->warningFor('disabled_form'))
        ->toBe(__('admin.labels.public_form_target_warning_disabled'))
        ->and($status->warningFor('missing_form'))
        ->toBe(__('admin.labels.public_form_target_warning_missing'))
        ->and($status->warningFor(null))->toBeNull()
        ->and($status->warningFor(''))->toBeNull();
});

it('counts visible menu items, about blocks, and homepage content-block buttons with broken form targets', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'home', 'type' => 'route', 'route_key' => 'home', 'visible' => true, 'sort' => 10],
                ['key' => 'ok', 'type' => 'public_form', 'form_key' => 'request_transcription', 'visible' => true, 'sort' => 20],
                ['key' => 'broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 30],
                ['key' => 'disabled', 'type' => 'public_form', 'form_key' => 'disabled_form', 'visible' => true, 'sort' => 40],
                ['key' => 'hidden_broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => false, 'sort' => 50],
            ],
        ],
        'about_page' => [
            'enabled' => true,
            'blocks' => [
                ['key' => 'intro', 'type' => 'markdown', 'content' => 'Intro', 'visible' => true],
                ['key' => 'cta_ok', 'type' => 'form_cta', 'form_key' => 'request_transcription', 'visible' => true],
                ['key' => 'cta_broken', 'type' => 'form_cta', 'form_key' => 'missing_form', 'visible' => true],
                ['key' => 'cta_hidden', 'type' => 'form_cta', 'form_key' => 'missing_form', 'visible' => false],
            ],
        ],
    ]);

    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['button_label' => 'Broken', 'button_form_key' => 'missing_form'],
    ]);
    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['button_label' => 'Healthy', 'button_form_key' => 'request_transcription'],
    ]);
    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'is_visible' => false,
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['button_label' => 'Hidden broken', 'button_form_key' => 'missing_form'],
    ]);

    $counts = app(PublicFormTargetStatus::class)->misconfiguredCounts();

    expect($counts)->toBe([
        'menu_items' => 2,
        'about_blocks' => 1,
        'homepage_buttons' => 1,
        'total' => 4,
    ]);
});

it('warns on the menu-header settings page when a menu item form target is missing or disabled', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 10],
                ['key' => 'disabled', 'type' => 'public_form', 'form_key' => 'disabled_form', 'visible' => true, 'sort' => 20],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSee(__('admin.labels.public_form_target_warning_missing'))
        ->assertSee(__('admin.labels.public_form_target_warning_disabled'));
});

it('stays quiet on the menu-header settings page when the menu item form target is enabled', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'ok', 'type' => 'public_form', 'form_key' => 'request_transcription', 'visible' => true, 'sort' => 10],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertDontSee(__('admin.labels.public_form_target_warning_missing'))
        ->assertDontSee(__('admin.labels.public_form_target_warning_disabled'));
});

it('warns on the about settings page when a form CTA block targets a missing form', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'about_page' => [
            'enabled' => true,
            'blocks' => [
                ['key' => 'cta_broken', 'type' => 'form_cta', 'heading' => 'CTA', 'form_key' => 'missing_form', 'visible' => true],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->assertSee(__('admin.labels.public_form_target_warning_missing'));
});

it('stays quiet on the about settings page when the form CTA block target is enabled', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'about_page' => [
            'enabled' => true,
            'blocks' => [
                ['key' => 'cta_ok', 'type' => 'form_cta', 'heading' => 'CTA', 'form_key' => 'request_transcription', 'visible' => true],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->assertDontSee(__('admin.labels.public_form_target_warning_missing'))
        ->assertDontSee(__('admin.labels.public_form_target_warning_disabled'));
});

it('warns on the homepage section form when the content-block button targets a missing form', function (): void {
    saveFormTargetConfig(['public_forms' => formTargetPublicFormsConfig()]);

    $section = HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['button_label' => 'Broken', 'button_form_key' => 'missing_form'],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditHomepageSection::class, ['record' => $section->id])
        ->assertSee(__('admin.labels.public_form_target_warning_missing'));
});

it('stays quiet on the homepage section form when the content-block button target is enabled', function (): void {
    saveFormTargetConfig(['public_forms' => formTargetPublicFormsConfig()]);

    $section = HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['button_label' => 'Healthy', 'button_form_key' => 'request_transcription'],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditHomepageSection::class, ['record' => $section->id])
        ->assertDontSee(__('admin.labels.public_form_target_warning_missing'))
        ->assertDontSee(__('admin.labels.public_form_target_warning_disabled'));
});

it('mirrors the public menu skip rules when judging admin menu item target health', function (): void {
    saveFormTargetConfig(['public_forms' => formTargetPublicFormsConfig()]);

    $health = app(PublicMenuItemTargetHealth::class);

    expect($health->hasUsableTarget(['type' => 'route', 'route_key' => 'home']))->toBeTrue()
        ->and($health->hasUsableTarget(['type' => 'route', 'route_key' => 'unknown_route']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'route']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'external_url', 'external_url' => 'https://example.test']))->toBeTrue()
        ->and($health->hasUsableTarget(['type' => 'external_url', 'external_url' => 'http://example.test']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'external_url']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'public_form', 'form_key' => 'request_transcription']))->toBeTrue()
        ->and($health->hasUsableTarget(['type' => 'public_form', 'form_key' => 'disabled_form']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'public_form', 'form_key' => 'missing_form']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'public_form']))->toBeFalse()
        ->and($health->hasUsableTarget(['type' => 'theme_selector']))->toBeTrue()
        ->and($health->hasUsableTarget([]))->toBeFalse();
});

it('marks menu items the public side would skip as inactive in their repeater headers', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'ok', 'type' => 'public_form', 'label' => 'Healthy menu CTA', 'form_key' => 'request_transcription', 'visible' => true, 'sort' => 10],
                ['key' => 'broken', 'type' => 'public_form', 'label' => 'Broken menu CTA', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 20],
                ['key' => 'off', 'type' => 'public_form', 'label' => 'Disabled-form CTA', 'form_key' => 'disabled_form', 'visible' => true, 'sort' => 30],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(MenuHeaderSettings::class)
        ->assertSee(__('admin.labels.public_target_inactive_label', ['label' => 'Broken menu CTA']))
        ->assertSee(__('admin.labels.public_target_inactive_label', ['label' => 'Disabled-form CTA']))
        ->assertSee('Healthy menu CTA')
        ->assertDontSee(__('admin.labels.public_target_inactive_label', ['label' => 'Healthy menu CTA']));
});

it('marks about form CTA blocks the public side would skip as inactive in their block headers', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'about_page' => [
            'enabled' => true,
            'blocks' => [
                ['key' => 'cta_broken', 'type' => 'form_cta', 'heading' => 'CTA', 'form_key' => 'missing_form', 'visible' => true],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->assertSee(__('admin.labels.public_target_inactive_label', [
            'label' => __('admin.about_block_types.form_cta'),
        ]));
});

it('keeps the plain block header for about form CTA blocks whose target is enabled', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'about_page' => [
            'enabled' => true,
            'blocks' => [
                ['key' => 'cta_ok', 'type' => 'form_cta', 'heading' => 'CTA', 'form_key' => 'request_transcription', 'visible' => true],
            ],
        ],
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->assertDontSee(__('admin.labels.public_target_inactive_label', [
            'label' => __('admin.about_block_types.form_cta'),
        ]));
});

it('shows the form target warnings widget with counts and settings links when visible CTAs are broken', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 10],
            ],
        ],
        'about_page' => [
            'enabled' => true,
            'blocks' => [
                ['key' => 'cta_broken', 'type' => 'form_cta', 'heading' => 'CTA', 'form_key' => 'missing_form', 'visible' => true],
            ],
        ],
    ]);

    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['button_label' => 'Broken', 'button_form_key' => 'missing_form'],
    ]);

    $this->actingAs(User::factory()->admin()->create());

    expect(PublicFormTargetWarningsWidget::canView())->toBeTrue()
        ->and(Dashboard::getWidgetsForLens(DashboardLens::Overview))->toContain(PublicFormTargetWarningsWidget::class);

    Livewire::test(PublicFormTargetWarningsWidget::class)
        ->assertSee(__('admin.dashboard.form_targets.heading'))
        ->assertSee(__('admin.dashboard.form_targets.menu_items'))
        ->assertSee(__('admin.dashboard.form_targets.about_blocks'))
        ->assertSee(__('admin.dashboard.form_targets.homepage_buttons'))
        ->assertSee(MenuHeaderSettings::getUrl(), false)
        ->assertSee(AboutSettings::getUrl(), false)
        ->assertSee(HomepageSectionResource::getUrl('index'), false);
});

it('hides the form target warnings widget when every visible CTA has an enabled definition', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'ok', 'type' => 'public_form', 'form_key' => 'request_transcription', 'visible' => true, 'sort' => 10],
            ],
        ],
    ]);

    $this->actingAs(User::factory()->admin()->create());

    expect(PublicFormTargetWarningsWidget::canView())->toBeFalse();
});

it('hides the form target warnings widget from guests even when CTAs are broken', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 10],
            ],
        ],
    ]);

    expect(PublicFormTargetWarningsWidget::canView())->toBeFalse();
});

it('tags the form target warnings widget as a stock widget', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 10],
            ],
        ],
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(PublicFormTargetWarningsWidget::class)
        ->assertSeeHtml('data-testid="widget-tag-stock"');
});

it('memoizes form target status per request so a widget render counts once', function (): void {
    saveFormTargetConfig([
        'public_forms' => formTargetPublicFormsConfig(),
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'broken', 'type' => 'public_form', 'form_key' => 'missing_form', 'visible' => true, 'sort' => 10],
            ],
        ],
    ]);

    expect(app(PublicFormTargetStatus::class))->toBe(app(PublicFormTargetStatus::class));

    $first = app(PublicFormTargetStatus::class)->misconfiguredCounts();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $second = app(PublicFormTargetStatus::class)->misconfiguredCounts();
    $queriesDuringSecondCall = DB::getQueryLog();
    DB::disableQueryLog();

    expect($second)->toBe($first)
        ->and($queriesDuringSecondCall)->toBe([]);
});
