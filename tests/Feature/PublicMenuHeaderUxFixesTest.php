<?php

use App\Enums\HomepageSectionType;
use App\Filament\Pages\MenuHeaderSettings;
use App\Livewire\Public\ContributorDirectory;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Menu\PublicMenuConfigReader;
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
