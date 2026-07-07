<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function clearPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

it('loads public front defaults when settings rows are missing', function (): void {
    DB::table('settings')->where('group', PublicContentSettings::group())->delete();

    clearPublicFrontSettingsCache();

    $result = app(PublicFrontConfigReader::class)->read();
    $defaults = app(PublicFrontConfigValidator::class)
        ->validate(PublicFrontConfigRegistry::defaults())
        ->config();

    expect($result->config())->toBe($defaults);
});

it('merges nested stored config arrays with defaults', function (): void {
    $result = app(PublicFrontConfigReader::class)->fromArray([
        'display_defaults' => [
            'layout' => 'rows',
        ],
        'menu_config' => [
            'enabled' => true,
        ],
    ]);

    $menuConfig = $result->group('menu_config');

    expect($result->group('display_defaults'))->toMatchArray([
        'layout' => 'rows',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'image_fit' => 'cover',
        'image_radius' => 'mid_rounded',
        'title_size' => 'base',
        'page_size' => 12,
    ])->and($menuConfig['enabled'])->toBeTrue()
        ->and($menuConfig['items'])->not->toBeEmpty()
        ->and($menuConfig['theme_selector'])->toMatchArray([
            'enabled' => true,
            'mode' => 'light_dark_system',
        ]);
});

it('reports unknown top level and nested keys safely', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'unexpected_group' => [],
        'display_defaults' => [
            'layout' => 'cards',
            'raw_classes' => 'p-4',
        ],
        'menu_config' => [
            'enabled' => true,
            'debug' => true,
        ],
    ]);

    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($paths)->toContain('unexpected_group')
        ->and($paths)->toContain('display_defaults.raw_classes')
        ->and($paths)->toContain('menu_config.debug');
});

it('rejects unsafe class css sql blade html and javascript values', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'display_defaults' => [
            'layout' => 'p-4 text-red-500',
        ],
        'menu_config' => [
            'items' => [
                [
                    'route_key' => 'home',
                    'label' => '<iframe src="https://example.com"></iframe>',
                ],
                [
                    'external_url' => 'javascript:alert(1)',
                    'label' => 'Bad URL',
                ],
                [
                    'route_key' => 'search',
                    'label' => 'Search',
                    'view' => 'resources/views/public/card.blade.php',
                ],
            ],
        ],
        'about_page' => [
            'blocks' => [
                [
                    'key' => 'intro',
                    'label' => 'App\\Filament\\Pages\\PublicContentSettings::class',
                ],
            ],
        ],
        'public_forms' => [
            [
                'key' => 'contact',
                'label' => 'select * from users where id = 1',
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($result->group('display_defaults')['layout'])->toBe('cards')
        ->and(collect($result->group('menu_config')['items'])->pluck('route_key')->filter()->values()->all())->toBe(['home', 'search'])
        ->and($paths)->toContain('display_defaults.layout')
        ->and($paths)->toContain('menu_config.items.0.label')
        ->and($paths)->toContain('menu_config.items.1.external_url')
        ->and($paths)->toContain('menu_config.items.2.view')
        ->and($paths)->toContain('about_page.blocks.0.label')
        ->and($paths)->toContain('public_forms.definitions.0.name');
});

it('saves sanitized public front config through the settings page while preserving card settings', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.homepage_item_limit', 9)
        ->set('data.pinned_item_limit', 4)
        ->set('data.default_public_sort', 'title_desc')
        ->set('data.default_result_layout', 'rows')
        ->set('data.show_latest_section', false)
        ->set('data.item_page_layout', 'media_first')
        ->set('data.homepage_card_image_size', 'large')
        ->set('data.homepage_card_density', 'compact')
        ->set('data.homepage_card_title_size', 'lg')
        ->set('data.homepage_show_group_badge', false)
        ->set('data.homepage_show_authors', false)
        ->set('data.homepage_show_categories', false)
        ->set('data.homepage_show_tags', false)
        ->set('data.homepage_show_duration', false)
        ->set('data.homepage_show_effective_date', false)
        ->set('data.homepage_show_description', false)
        ->set('data.homepage_description_lines', 1)
        ->set('data.homepage_cards_per_page', 7)
        ->set('data.menu_config.enabled', true)
        ->set('data.display_defaults.layout', 'rows')
        ->set('data.display_defaults.density', 'compact')
        ->set('data.display_defaults.image_size', 'large')
        ->set('data.display_defaults.title_size', 'lg')
        ->set('data.display_defaults.page_size', 16)
        ->set('data.route_labels', [
            [
                'route_key' => 'podcasts',
                'label' => 'Podcasts',
            ],
            [
                'route_key' => 'search',
                'label' => '<script>alert(1)</script>',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    clearPublicFrontSettingsCache();

    $settings = app(PublicContentSettings::class);
    $config = app(PublicFrontConfigReader::class)->read($settings);
    $cardOptions = PublicContentCardOptions::fromSettings($settings);

    expect($settings->display_defaults)->toBe([
        'layout' => 'rows',
        'density' => 'compact',
        'image_size' => 'large',
        'image_fit' => 'cover',
        'image_radius' => 'mid_rounded',
        'title_size' => 'lg',
        'page_size' => 16,
    ])->and($config->group('route_labels'))->toBe([
        [
            'route_key' => 'podcasts',
            'label' => 'Podcasts',
        ],
    ])->and($config->group('menu_config')['enabled'])->toBeTrue()
        ->and($config->group('menu_config')['items'])->not->toBeEmpty()
        ->and($cardOptions->imageSize)->toBe('large')
        ->and($cardOptions->density)->toBe('compact')
        ->and($cardOptions->titleSize)->toBe('lg')
        ->and($cardOptions->cardsPerPage)->toBe(7);
});

it('does not introduce settings only models', function (): void {
    expect(class_exists('App\\Models\\CardTemplate'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicMenu'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicMenuItem'))->toBeFalse()
        ->and(class_exists('App\\Models\\AboutPage'))->toBeFalse()
        ->and(class_exists('App\\Models\\AboutPageBlock'))->toBeFalse()
        ->and(class_exists('App\\Models\\TeamProfile'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicFormDefinition'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicDisplaySection'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicLooper'))->toBeFalse();
});
