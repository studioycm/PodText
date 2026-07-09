<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
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
        'item_page' => [
            'dates' => [
                'display' => 'site',
            ],
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
        'transcription_display' => 'effective_only',
    ])->and($menuConfig['enabled'])->toBeTrue()
        ->and($result->group('item_page')['dates'])->toMatchArray([
            'display' => 'site',
            'site_published' => [
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'calendar',
                'icon_position' => 'inline_before',
            ],
            'original_published' => [
                'label_mode' => 'short',
                'label_override' => null,
                'icon' => 'calendar',
                'icon_position' => 'inline_before',
            ],
        ])
        ->and($menuConfig['items'])->not->toBeEmpty()
        ->and($menuConfig['theme_selector'])->toMatchArray([
            'enabled' => true,
            'mode' => 'light_dark_system',
        ]);
});

it('creates item page settings defaults through the settings migration', function (): void {
    $settings = app(PublicContentSettings::class);

    expect(DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'item_page')
        ->exists())->toBeTrue()
        ->and($settings->item_page)->toMatchArray(PublicFrontConfigRegistry::defaults()['item_page']);
});

it('backfills item page header settings through the settings migration', function (): void {
    $legacyItemPage = [
        'dates' => [
            'display' => 'site',
        ],
        'badges' => [
            'info' => [
                'size' => 'md',
                'color' => 'primary',
            ],
        ],
    ];

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode($legacyItemPage),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000001_add_public_item_page_header_settings.php');
    $migration->up();

    $itemPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'item_page')
            ->value('payload'),
        true,
    );

    expect($itemPage)->toMatchArray([
        'show_breadcrumbs' => true,
        'podcast_identity' => [
            'mode' => 'badge',
            'color' => 'primary',
            'icon' => 'podcast',
            'icon_position' => 'inline_before',
            'position' => 'above_title',
            'size' => 'sm',
        ],
        'badges' => [
            'info' => [
                'size' => 'md',
                'color' => 'primary',
            ],
        ],
    ])->and($itemPage['dates']['display'])->toBe('site')
        ->and($itemPage['dates']['site_published'])->toMatchArray(
            PublicFrontConfigRegistry::defaults()['item_page']['dates']['site_published'],
        )
        ->and($itemPage['info_fields'])->toBe(PublicItemPageRegistry::defaultInfoFields());
});

it('backfills item page podcast identity presentation settings through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'podcast_identity' => [
                    'mode' => 'text',
                    'color' => 'success',
                    'icon' => 'podcast',
                    'icon_position' => 'inline_after',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000002_extend_public_item_page_podcast_identity_settings.php');
    $migration->up();

    $itemPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'item_page')
            ->value('payload'),
        true,
    );

    expect($itemPage['podcast_identity'])->toMatchArray([
        'mode' => 'text',
        'color' => 'success',
        'icon' => 'podcast',
        'icon_position' => 'inline_after',
        'position' => 'above_title',
        'size' => 'sm',
    ]);
});

it('backfills item page transcript actions setting through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'show_breadcrumbs' => false,
                'show_transcript_actions_menu' => 'yes',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000003_add_public_item_page_transcript_actions_setting.php');
    $migration->up();

    $itemPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'item_page')
            ->value('payload'),
        true,
    );

    expect($itemPage)->toMatchArray([
        'show_breadcrumbs' => false,
        'show_transcript_actions_menu' => false,
    ]);
});

it('aligns transcription display defaults through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'display_defaults',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'layout' => 'cards',
                'transcription_display' => 'effective_plus_count',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'podcasts_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'group_page' => [
                    'transcription_display' => 'effective_plus_count',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'contributors_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'directory' => [
                    'transcription_display' => 'effective_plus_count',
                ],
                'top_transcribers' => [
                    'transcription_display' => 'effective_plus_count',
                ],
                'page' => [
                    'transcription_display' => 'effective_plus_count',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000004_align_public_transcription_display_defaults.php');
    $migration->up();

    $displayDefaults = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'display_defaults')
            ->value('payload'),
        true,
    );
    $podcastsPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'podcasts_page')
            ->value('payload'),
        true,
    );
    $contributorsPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'contributors_page')
            ->value('payload'),
        true,
    );

    expect($displayDefaults['transcription_display'])->toBe('effective_only')
        ->and($podcastsPage['group_page']['transcription_display'])->toBe('effective_only')
        ->and($contributorsPage['directory']['transcription_display'])->toBe('effective_only')
        ->and($contributorsPage['top_transcribers']['transcription_display'])->toBe('effective_only')
        ->and($contributorsPage['page']['transcription_display'])->toBe('effective_only');
});

it('normalizes item page date and badge settings safely', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'item_page' => [
            'show_breadcrumbs' => 'yes',
            'show_transcript_actions_menu' => '<script>alert(1)</script>',
            'podcast_identity' => [
                'mode' => 'pill',
                'color' => 'bg-red-500',
                'icon' => 'App\\Icons\\Unsafe',
                'icon_position' => 'after',
                'position' => 'inside-title',
                'size' => 'huge',
                'class' => 'rounded-full',
            ],
            'info_fields' => [
                [
                    'field' => 'site_published_date',
                    'label_mode' => 'long',
                    'label_override' => 'Published locally',
                    'icon' => 'calendar',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'primary',
                ],
                [
                    'field' => 'raw_html',
                    'label_mode' => 'verbose',
                    'label_override' => '<b>Bad</b>',
                    'icon' => 'heroicon-o-calendar',
                    'icon_position' => 'beside',
                    'size' => 'xl',
                    'color' => '#fff',
                    'class' => 'text-red-500',
                ],
            ],
            'dates' => [
                'display' => 'p-4 text-red-500',
                'raw_classes' => 'gap-4',
                'site_published' => [
                    'label_mode' => 'verbose',
                    'label_override' => '<script>alert(1)</script>',
                    'icon' => 'heroicon-o-calendar',
                    'icon_position' => 'before',
                    'raw_classes' => 'text-red-500',
                ],
                'original_published' => 'bad-value',
                'transcription_date' => [
                    'enabled' => 'yes',
                    'label_mode' => 'hidden',
                    'icon' => 'document',
                    'icon_position' => 'after',
                ],
            ],
            'badges' => [
                'info' => [
                    'size' => 'xl',
                    'color' => '#fff',
                    'class' => 'bg-red-500',
                ],
            ],
        ],
    ]);

    $itemPage = $result->group('item_page');
    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($itemPage['dates']['display'])->toBe('both')
        ->and($itemPage['show_breadcrumbs'])->toBeTrue()
        ->and($itemPage['show_transcript_actions_menu'])->toBeFalse()
        ->and($itemPage['podcast_identity'])->toMatchArray([
            'mode' => 'badge',
            'color' => 'primary',
            'icon' => 'podcast',
            'icon_position' => 'inline_after',
            'position' => 'above_title',
            'size' => 'sm',
        ])
        ->and($itemPage['info_fields'])->toHaveCount(2)
        ->and($itemPage['info_fields'][0])->toMatchArray([
            'field' => 'site_published_date',
            'label_mode' => 'long',
            'label_override' => 'Published locally',
            'icon' => 'calendar',
            'icon_position' => 'inline_before',
            'size' => 'sm',
            'color' => 'primary',
        ])
        ->and($itemPage['info_fields'][1])->toMatchArray([
            'field' => 'duration',
            'label_mode' => 'hidden',
            'label_override' => null,
            'icon' => 'document',
            'icon_position' => 'inline_before',
            'size' => 'sm',
            'color' => 'gray',
        ])
        ->and($itemPage['dates']['site_published'])->toMatchArray([
            'label_mode' => 'long',
            'label_override' => null,
            'icon' => 'calendar',
            'icon_position' => 'inline_before',
        ])
        ->and($itemPage['dates']['original_published'])->toMatchArray(
            PublicFrontConfigRegistry::defaults()['item_page']['dates']['original_published'],
        )
        ->and($itemPage['dates']['transcription_date'])->toMatchArray([
            'enabled' => true,
            'label_mode' => 'hidden',
            'icon' => 'document',
            'icon_position' => 'inline_after',
        ])
        ->and($itemPage['badges']['info'])->toMatchArray([
            'size' => 'sm',
            'color' => 'gray',
        ])
        ->and($paths)->toContain('item_page.show_breadcrumbs')
        ->and($paths)->toContain('item_page.show_transcript_actions_menu')
        ->and($paths)->toContain('item_page.podcast_identity.mode')
        ->and($paths)->toContain('item_page.podcast_identity.color')
        ->and($paths)->toContain('item_page.podcast_identity.icon')
        ->and($paths)->toContain('item_page.podcast_identity.position')
        ->and($paths)->toContain('item_page.podcast_identity.size')
        ->and($paths)->toContain('item_page.podcast_identity.class')
        ->and($paths)->toContain('item_page.info_fields.1.field')
        ->and($paths)->toContain('item_page.info_fields.1.label_mode')
        ->and($paths)->toContain('item_page.info_fields.1.label_override')
        ->and($paths)->toContain('item_page.info_fields.1.icon')
        ->and($paths)->toContain('item_page.info_fields.1.icon_position')
        ->and($paths)->toContain('item_page.info_fields.1.size')
        ->and($paths)->toContain('item_page.info_fields.1.color')
        ->and($paths)->toContain('item_page.info_fields.1.class')
        ->and($paths)->toContain('item_page.dates.display')
        ->and($paths)->toContain('item_page.dates.raw_classes')
        ->and($paths)->toContain('item_page.dates.site_published.label_mode')
        ->and($paths)->toContain('item_page.dates.site_published.label_override')
        ->and($paths)->toContain('item_page.dates.site_published.icon')
        ->and($paths)->toContain('item_page.dates.site_published.raw_classes')
        ->and($paths)->toContain('item_page.dates.original_published')
        ->and($paths)->toContain('item_page.dates.transcription_date.enabled')
        ->and($paths)->toContain('item_page.badges.info.size')
        ->and($paths)->toContain('item_page.badges.info.color')
        ->and($paths)->toContain('item_page.badges.info.class');
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
        ->set('data.item_page.show_breadcrumbs', false)
        ->set('data.item_page.show_transcript_actions_menu', true)
        ->set('data.item_page.podcast_identity.mode', 'text')
        ->set('data.item_page.podcast_identity.color', 'image_2')
        ->set('data.item_page.podcast_identity.size', 'lg')
        ->set('data.item_page.podcast_identity.position', 'title_row_after')
        ->set('data.item_page.podcast_identity.icon', 'podcast')
        ->set('data.item_page.podcast_identity.icon_position', 'inline_after')
        ->set('data.item_page.info_fields', [
            [
                'field' => 'categories',
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'folder',
                'icon_position' => 'inline_before',
                'size' => 'sm',
                'color' => 'info',
            ],
            [
                'field' => 'site_published_date',
                'label_mode' => 'short',
                'label_override' => 'Site',
                'icon' => 'calendar',
                'icon_position' => 'inline_after',
                'size' => 'md',
                'color' => 'primary',
            ],
        ])
        ->set('data.item_page.dates.display', 'both')
        ->set('data.item_page.dates.site_published.label_mode', 'long')
        ->set('data.item_page.dates.site_published.label_override', 'Published here')
        ->set('data.item_page.dates.site_published.icon', 'calendar')
        ->set('data.item_page.dates.site_published.icon_position', 'inline_after')
        ->set('data.item_page.dates.original_published.label_mode', 'short')
        ->set('data.item_page.dates.original_published.label_override', null)
        ->set('data.item_page.dates.original_published.icon', 'calendar')
        ->set('data.item_page.dates.original_published.icon_position', 'inline_before')
        ->set('data.item_page.dates.transcription_date.enabled', true)
        ->set('data.item_page.dates.transcription_date.label_mode', 'hidden')
        ->set('data.item_page.dates.transcription_date.label_override', null)
        ->set('data.item_page.dates.transcription_date.icon', 'document')
        ->set('data.item_page.dates.transcription_date.icon_position', 'hidden')
        ->set('data.item_page.badges.info.size', 'md')
        ->set('data.item_page.badges.info.color', 'primary')
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
        'transcription_display' => 'effective_only',
    ])->and($config->group('item_page'))->toMatchArray([
        'show_breadcrumbs' => false,
        'show_transcript_actions_menu' => true,
        'podcast_identity' => [
            'mode' => 'text',
            'color' => 'image_2',
            'icon' => 'podcast',
            'icon_position' => 'inline_after',
            'position' => 'title_row_after',
            'size' => 'lg',
        ],
        'info_fields' => [
            [
                'field' => 'categories',
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'folder',
                'icon_position' => 'inline_before',
                'size' => 'sm',
                'color' => 'info',
            ],
            [
                'field' => 'site_published_date',
                'label_mode' => 'short',
                'label_override' => 'Site',
                'icon' => 'calendar',
                'icon_position' => 'inline_after',
                'size' => 'md',
                'color' => 'primary',
            ],
        ],
        'dates' => [
            'display' => 'both',
            'site_published' => [
                'label_mode' => 'long',
                'label_override' => 'Published here',
                'icon' => 'calendar',
                'icon_position' => 'inline_after',
            ],
            'original_published' => [
                'label_mode' => 'short',
                'label_override' => null,
                'icon' => 'calendar',
                'icon_position' => 'inline_before',
            ],
            'transcription_date' => [
                'enabled' => true,
                'label_mode' => 'hidden',
                'label_override' => null,
                'icon' => 'document',
                'icon_position' => 'hidden',
            ],
        ],
        'badges' => [
            'info' => [
                'size' => 'md',
                'color' => 'primary',
            ],
        ],
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
        ->and($cardOptions->transcriptionDisplay)->toBe('effective_only')
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

it('has translated item page settings labels in both locales', function (): void {
    foreach (['en', 'he'] as $locale) {
        app()->setLocale($locale);

        foreach ([
            'site_published_long',
            'site_published_short',
            'original_published_long',
            'original_published_short',
            'transcription_date_long',
            'transcription_date_short',
        ] as $key) {
            expect(__("public.dates.{$key}"))->not->toBe("public.dates.{$key}");
        }

        foreach ([
            'categories_long',
            'duration_short',
            'reading_time_long',
            'tags_short',
            'transcribers_long',
            'transcription_count_short',
            'word_count_long',
        ] as $key) {
            expect(__("public.item_page.info_fields.{$key}"))->not->toBe("public.item_page.info_fields.{$key}");
        }

        foreach ([
            'admin.sections.public_front_item_page_header',
            'admin.sections.public_front_item_page_info_fields',
            'admin.sections.public_front_item_page_transcript_controls',
            'admin.descriptions.public_front_item_page_transcript_controls',
            'admin.fields.item_page_show_breadcrumbs',
            'admin.fields.item_page_show_transcript_actions_menu',
            'admin.fields.item_page_podcast_identity_mode',
            'admin.fields.item_page_podcast_identity_position',
            'admin.fields.item_page_podcast_identity_size',
            'admin.fields.item_page_info_fields',
            'admin.helpers.item_page_show_transcript_actions_menu',
            'admin.helpers.item_page_info_field_key',
            'admin.item_page_info_fields.site_published_date',
            'admin.item_page_podcast_identity_modes.badge',
            'admin.item_page_podcast_identity_colors.image_2',
            'admin.item_page_podcast_identity_positions.title_row_after',
            'admin.item_page_podcast_identity_sizes.lg',
            'public.viewer.actions',
            'public.viewer.decrease_font',
            'public.viewer.fullscreen',
            'public.viewer.hide_player',
            'public.viewer.increase_font',
            'public.viewer.reset_font',
            'public.viewer.show_player',
        ] as $translationKey) {
            expect(__($translationKey))->not->toBe($translationKey);
        }
    }

    app()->setLocale(config('app.locale'));
});
