<?php

use App\Livewire\Public\ContributorDirectory;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Menu\PublicMenuConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function clearStep9RPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep9RPublicFrontSettings(array $settings): void
{
    foreach ($settings as $key => $value) {
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

    clearStep9RPublicFrontSettingsCache();
}

function step9RPublicFormsConfig(): array
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

function createStep9RPublicItem(
    string $title = 'Public Step 9R Item',
    ?ContentGroup $group = null,
    ?Author $author = null,
    array $itemAttributes = [],
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();
    $author ??= Author::factory()->create();

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

it('records focused MCP research discipline and future footer scope split guidance', function (): void {
    $research = file_get_contents(base_path('docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md'));
    $verificationPlan = file_get_contents(base_path('docs/phase-02/public-front-v2-step9r-verification-and-fixes-plan.md'));
    $futurePlan = file_get_contents(base_path('docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md'));
    $toolingGuidance = file_get_contents(base_path('.ai/guidelines/tooling-quality.md'));

    expect($research)->toContain('Query Batches')
        ->and($research)->toContain('Refined second pass')
        ->and($research)->toContain('Pattern to copy')
        ->and($verificationPlan)->toContain('Step 10 Overlap Decision')
        ->and($futurePlan)->toContain('Step 10')
        ->and($futurePlan)->toContain('Step 11')
        ->and($toolingGuidance)->toContain('decompose the feature into short search topics');
});

it('normalizes and renders extended public header logo search alignment and theme settings', function (): void {
    $rawMenuConfig = [
        'enabled' => true,
        'items_alignment' => 'end',
        'logo' => [
            'light_path' => 'header/custom-logo.svg',
            'dark_path' => 'header/custom-logo-dark.webp',
            'alt_text' => 'PodText Custom',
            'display_mode' => 'image_text',
            'size' => 'small',
        ],
        'search' => [
            'enabled' => true,
            'placeholder' => 'Search archive',
            'route_key' => 'search',
            'query_param' => 'term',
        ],
        'items' => [
            [
                'key' => 'home',
                'type' => 'route',
                'route_key' => 'home',
                'label' => 'Home',
                'visible' => true,
                'sort' => 10,
            ],
            [
                'key' => 'request',
                'type' => 'public_form',
                'form_key' => 'request_transcription',
                'label' => 'Request',
                'display_mode' => 'slide_over',
                'visible' => true,
                'sort' => 20,
            ],
            [
                'key' => 'disabled',
                'type' => 'public_form',
                'form_key' => 'disabled_form',
                'label' => 'Disabled',
                'visible' => true,
                'sort' => 30,
            ],
            [
                'key' => 'theme',
                'type' => 'theme_selector',
                'visible' => true,
                'sort' => 40,
            ],
        ],
        'theme_selector' => [
            'enabled' => true,
            'mode' => 'light_dark',
            'display_mode' => 'trigger_icon_menu',
        ],
    ];

    $validation = app(PublicFrontConfigValidator::class)->validate([
        'menu_config' => $rawMenuConfig,
    ]);
    $menuConfig = $validation->group('menu_config');

    expect($validation->hasInvalidConfig())->toBeFalse()
        ->and($menuConfig['items_alignment'])->toBe('end')
        ->and($menuConfig['logo']['light_path'])->toBe('header/custom-logo.svg')
        ->and($menuConfig['search']['query_param'])->toBe('term')
        ->and($menuConfig['theme_selector']['display_mode'])->toBe('trigger_icon_menu');

    saveStep9RPublicFrontSettings([
        'public_forms' => step9RPublicFormsConfig(),
        'menu_config' => $rawMenuConfig,
    ]);

    $menu = app(PublicMenuConfigReader::class)->read();

    expect($menu['items_alignment'])->toBe('end')
        ->and($menu['logo']['light_url'])->toContain('/storage/header/custom-logo.svg')
        ->and($menu['search']['query_param'])->toBe('term')
        ->and(collect($menu['items'])->pluck('key')->all())->toBe(['home', 'request', 'theme']);

    $this->get('/?term=needle')
        ->assertSuccessful()
        ->assertSee('data-test="public-menu-layout"', false)
        ->assertSee('data-menu-alignment="end"', false)
        ->assertSee('data-logo-display-mode="image_text"', false)
        ->assertSee('data-logo-size="small"', false)
        ->assertSee('/storage/header/custom-logo.svg', false)
        ->assertSee('PodText Custom')
        ->assertSee('data-test="public-header-search"', false)
        ->assertSee('name="term"', false)
        ->assertSee('value="needle"', false)
        ->assertSee('Search archive')
        ->assertSee('data-theme-display-mode="trigger_icon_menu"', false)
        ->assertSee('data-test="public-theme-menu"', false)
        ->assertSee('data-test="public-menu-form-action"', false)
        ->assertSee('data-form-key="request_transcription"', false)
        ->assertDontSee('data-menu-key="disabled"', false);
});

it('rejects unsafe extended public header config values', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'menu_config' => [
            'items_alignment' => 'justify-between',
            'logo' => [
                'light_path' => '../logo.svg',
                'dark_path' => 'header/logo.php',
                'display_mode' => 'raw_html',
                'size' => 'huge',
            ],
            'search' => [
                'route_key' => 'admin',
                'query_param' => '<script>',
            ],
            'theme_selector' => [
                'display_mode' => 'raw',
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())->pluck('path')->all();

    expect($paths)->toContain('menu_config.items_alignment')
        ->and($paths)->toContain('menu_config.logo.light_path')
        ->and($paths)->toContain('menu_config.logo.dark_path')
        ->and($paths)->toContain('menu_config.logo.display_mode')
        ->and($paths)->toContain('menu_config.logo.size')
        ->and($paths)->toContain('menu_config.search.route_key')
        ->and($paths)->toContain('menu_config.search.query_param')
        ->and($paths)->toContain('menu_config.theme_selector.display_mode');
});

it('suppresses homepage discovery chrome when root query parameters are present and keeps search chrome', function (): void {
    createStep9RPublicItem('Query Chrome Item');

    $this->get('/?sort=latest_transcription&q=query')
        ->assertSuccessful()
        ->assertSee('Query Chrome Item')
        ->assertSee('data-test="homepage-section"', false)
        ->assertDontSee('data-test="discovery-chrome"', false)
        ->assertDontSee('data-test="item-search"', false);

    $this->get('/search?sort=latest_transcription&q=query')
        ->assertSuccessful()
        ->assertSee('data-test="discovery-chrome"', false)
        ->assertSee('data-test="item-search"', false);
});

it('uses group image fallback and finite badge title image styling settings on item cards', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Fallback Group',
        'cover_path' => 'groups/fallback-cover.jpg',
    ]);

    createStep9RPublicItem('Fallback Episode', $group);

    saveStep9RPublicFrontSettings([
        'homepage_card_image_fit' => 'contain',
        'homepage_card_image_radius' => 'high_rounded',
        'homepage_group_badge_mode' => 'thumbnail_name',
        'homepage_group_badge_duplicate_thumbnail' => false,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-card-image-fit="contain"', false)
        ->assertSee('data-card-image-radius="high_rounded"', false)
        ->assertSee('data-card-image-source="group"', false)
        ->assertSee('/storage/groups/fallback-cover.jpg', false)
        ->assertSee('data-group-badge-mode="thumbnail_name"', false)
        ->assertSee('data-group-badge-thumbnail="false"', false);

    saveStep9RPublicFrontSettings([
        'homepage_group_badge_mode' => 'combined_title',
        'homepage_group_title_separator' => ' | ',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Fallback Group | Fallback Episode')
        ->assertDontSee('data-test="group-badge"', false);
});

it('renders about heading hierarchy and image styling settings safely', function (): void {
    saveStep9RPublicFrontSettings([
        'about_page' => [
            'enabled' => true,
            'title' => 'About Step 9R',
            'kicker' => 'About',
            'description' => 'Safe description',
            'settings' => [
                'team_heading' => 'Team',
                'team_layout' => 'grid',
                'team_card' => [
                    'show_image' => true,
                    'image_size' => 'medium',
                    'image_fit' => 'contain',
                    'image_radius' => 'circle',
                    'layout' => 'grid',
                    'density' => 'comfortable',
                    'show_title' => true,
                    'show_description' => true,
                    'description_lines' => 2,
                ],
            ],
            'blocks' => [
                [
                    'key' => 'headings',
                    'type' => 'markdown',
                    'visible' => true,
                    'sort' => 10,
                    'content' => "# H1\n\n## H2\n\n### H3\n\n#### H4\n\n##### H5\n\n###### H6",
                ],
                [
                    'key' => 'hero',
                    'type' => 'image',
                    'visible' => true,
                    'sort' => 20,
                    'image_path' => 'about/hero.jpg',
                    'image_alt' => 'Hero',
                    'image_fit' => 'contain',
                    'image_radius' => 'round',
                ],
            ],
            'team_profiles' => [
                [
                    'key' => 'profile',
                    'visible' => true,
                    'sort' => 10,
                    'image_path' => 'team/profile.png',
                    'name' => 'Profile Person',
                    'title' => 'Title',
                    'description' => 'Description',
                ],
            ],
        ],
    ]);

    $this->get('/about')
        ->assertSuccessful()
        ->assertSee('[&amp;_h1]:text-3xl', false)
        ->assertSee('[&amp;_h2]:text-2xl', false)
        ->assertSee('[&amp;_h3]:text-xl', false)
        ->assertSee('[&amp;_h4]:text-lg', false)
        ->assertSee('[&amp;_h5]:text-base', false)
        ->assertSee('[&amp;_h6]:text-sm', false)
        ->assertSee('data-test="about-image"', false)
        ->assertSee('data-image-fit="contain"', false)
        ->assertSee('data-image-radius="round"', false)
        ->assertSee('data-test="about-team-profile-image"', false)
        ->assertSee('data-team-card-image-fit="contain"', false)
        ->assertSee('data-team-card-image-radius="circle"', false);
});

it('keeps contributor directory preview grid behavior as a step 9 follow-up without full step 10 redesign', function (): void {
    $author = Author::factory()->create(['name' => 'Grid Contributor', 'slug' => 'grid-contributor']);
    $other = Author::factory()->create(['name' => 'Other Contributor', 'slug' => 'other-grid-contributor']);

    createStep9RPublicItem('Grid Preview Needle', author: $author);
    createStep9RPublicItem('Grid Preview Haystack', author: $author);
    createStep9RPublicItem('Other Preview Item', author: $other);

    Livewire::test(ContributorDirectory::class)
        ->call('selectContributor', $author->id)
        ->assertSee('data-test="contributor-preview-items-grid"', false)
        ->assertSee('data-result-layout="cards"', false)
        ->assertSee('data-test="selected-contributor-link"', false)
        ->set('previewSearch', 'Needle')
        ->assertSee('Grid Preview Needle')
        ->assertDontSee('Grid Preview Haystack')
        ->assertDontSee('Other Preview Item');
});

it('does not introduce prohibited public menu footer cms or podcast model layers', function (): void {
    expect(class_exists('App\\Models\\PublicMenu'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicMenuItem'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicFormDefinition'))->toBeFalse()
        ->and(class_exists('App\\Models\\FooterSection'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicFooter'))->toBeFalse()
        ->and(class_exists('App\\Models\\Podcast'))->toBeFalse()
        ->and(class_exists('App\\Models\\Episode'))->toBeFalse()
        ->and(class_exists('App\\Models\\Page'))->toBeFalse();
});
