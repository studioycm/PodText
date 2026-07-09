<?php

use App\Enums\PublicationStatus;
use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContentItem;
use App\Livewire\Public\ContentGroupBrowser;
use App\Livewire\Public\ContentItemBrowser;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Episode;
use App\Models\Podcast;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function saveStep8PublicFrontConfig(array $config): void
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
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function createStep8PublicItem(
    ContentGroup $group,
    array $itemAttributes = [],
    array $transcriptionAttributes = [],
): ContentItem {
    $publishedAt = $itemAttributes['published_at'] ?? now()->subMinute();

    return ContentItem::factory()
        ->for($group)
        ->published($publishedAt)
        ->withTranscription([
            'published_at' => $transcriptionAttributes['published_at'] ?? $publishedAt,
            ...$transcriptionAttributes,
        ])
        ->create($itemAttributes);
}

it('allows guests to browse canonical podcasts without public table markup', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Public Podcast',
        'slug' => 'public-podcast',
        'description_markdown' => 'A public podcast description.',
    ]);
    createStep8PublicItem($group, ['title' => 'Public Episode']);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('public.pages.podcasts.title'))
        ->assertSee($group->title)
        ->assertSee('data-test="group-search"', false)
        ->assertSee('data-test="content-group-public-count"', false)
        ->assertDontSee('fi-ta-table', false);
});

it('lists only published groups with at least one public item and published transcription', function (): void {
    $visible = ContentGroup::factory()->published()->create(['title' => 'Visible Podcast']);
    createStep8PublicItem($visible, ['title' => 'Visible Episode']);

    $unpublishedGroup = ContentGroup::factory()->create(['title' => 'Draft Podcast']);
    createStep8PublicItem($unpublishedGroup, ['title' => 'Draft Parent Episode']);

    $withoutItems = ContentGroup::factory()->published()->create(['title' => 'Empty Podcast']);

    $withDraftItem = ContentGroup::factory()->published()->create(['title' => 'Draft Item Podcast']);
    ContentItem::factory()
        ->for($withDraftItem)
        ->withTranscription()
        ->create(['title' => 'Draft Only Episode']);

    $withoutTranscription = ContentGroup::factory()->published()->create(['title' => 'No Transcript Podcast']);
    ContentItem::factory()
        ->for($withoutTranscription)
        ->published()
        ->create(['title' => 'No Transcript Episode']);

    $withDraftTranscription = ContentGroup::factory()->published()->create(['title' => 'Draft Transcript Podcast']);
    ContentItem::factory()
        ->for($withDraftTranscription)
        ->published()
        ->withTranscription([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ])
        ->create(['title' => 'Draft Transcript Episode']);

    Livewire::test(ContentGroupBrowser::class)
        ->assertSee($visible->title)
        ->assertDontSee($unpublishedGroup->title)
        ->assertDontSee($withoutItems->title)
        ->assertDontSee($withDraftItem->title)
        ->assertDontSee($withoutTranscription->title)
        ->assertDontSee($withDraftTranscription->title);
});

it('counts only public episodes on podcast cards', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => 'Counted Podcast']);
    createStep8PublicItem($group, ['title' => 'First Public Episode']);
    createStep8PublicItem($group, ['title' => 'Second Public Episode']);
    ContentItem::factory()
        ->for($group)
        ->published()
        ->create(['title' => 'Hidden Missing Transcript']);
    ContentItem::factory()
        ->for($group)
        ->create(['title' => 'Hidden Draft Episode']);

    Livewire::test(ContentGroupBrowser::class)
        ->assertSee($group->title)
        ->assertSee('2 Episodes')
        ->assertDontSee('4 Episodes');
});

it('searches podcasts by title description visible category and public episode title', function (): void {
    $category = Category::factory()->create(['name' => 'Audio Strategy']);

    $titleMatch = ContentGroup::factory()->published()->create(['title' => 'Needle Podcast']);
    createStep8PublicItem($titleMatch);

    $descriptionMatch = ContentGroup::factory()->published()->create([
        'title' => 'Description Match',
        'description_markdown' => 'Deep topic about studio workflows.',
    ]);
    createStep8PublicItem($descriptionMatch);

    $categoryMatch = ContentGroup::factory()->published()->create(['title' => 'Category Match']);
    $categoryMatch->categories()->attach($category);
    createStep8PublicItem($categoryMatch);

    $episodeMatch = ContentGroup::factory()->published()->create(['title' => 'Episode Match']);
    createStep8PublicItem($episodeMatch, ['title' => 'Newsletter Topic Episode']);

    $other = ContentGroup::factory()->published()->create(['title' => 'Other Podcast']);
    createStep8PublicItem($other, ['title' => 'Unrelated Episode']);

    Livewire::test(ContentGroupBrowser::class)
        ->set('search', 'Needle')
        ->assertSee($titleMatch->title)
        ->assertDontSee($other->title)
        ->set('search', 'studio workflows')
        ->assertSee($descriptionMatch->title)
        ->assertDontSee($other->title)
        ->set('search', 'Audio Strategy')
        ->assertSee($categoryMatch->title)
        ->assertDontSee($other->title)
        ->set('search', 'Newsletter Topic')
        ->assertSee($episodeMatch->title)
        ->assertDontSee($other->title);
});

it('filters podcasts by visible category toggles including descendants and group categories', function (): void {
    $parent = Category::factory()->create(['name' => 'Parent Topic']);
    $child = Category::factory()->create([
        'name' => 'Child Topic',
        'parent_id' => $parent->id,
    ]);
    $otherCategory = Category::factory()->create(['name' => 'Other Topic']);

    $childGroup = ContentGroup::factory()->published()->create(['title' => 'Child Category Podcast']);
    $childGroup->categories()->attach($child);
    createStep8PublicItem($childGroup);

    $otherGroup = ContentGroup::factory()->published()->create(['title' => 'Other Category Podcast']);
    $otherGroup->categories()->attach($otherCategory);
    createStep8PublicItem($otherGroup);

    Livewire::withQueryParams(['categories' => (string) $parent->id])
        ->test(ContentGroupBrowser::class)
        ->assertSet('categoryIds', [$parent->id])
        ->assertSee($childGroup->title)
        ->assertDontSee($otherGroup->title)
        ->call('toggleCategoryFilter', $otherCategory->id)
        ->assertSet('categories', "{$parent->id},{$otherCategory->id}")
        ->assertSee($childGroup->title)
        ->assertSee($otherGroup->title);
});

it('renders cover fallback categories counts and configured public labels', function (): void {
    saveStep8PublicFrontConfig([
        'podcasts_page' => [
            'title' => 'Shows',
            'description' => 'Browse public shows.',
            'group_label_singular' => 'Show',
            'group_label_plural' => 'Shows',
        ],
    ]);

    $category = Category::factory()->create(['name' => 'Configured Category']);
    $withCover = ContentGroup::factory()->published()->create([
        'title' => 'Covered Show',
        'cover_path' => 'content-groups/covers/covered-show.jpg',
    ]);
    $withCover->categories()->attach($category);
    createStep8PublicItem($withCover);

    $withoutCover = ContentGroup::factory()->published()->create(['title' => 'Fallback Show']);
    createStep8PublicItem($withoutCover);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('Shows')
        ->assertSee('Browse public shows.')
        ->assertSee('Show')
        ->assertSee('Configured Category')
        ->assertSee(ShowContentGroup::getUrl(['contentGroupSlug' => $withCover->slug], panel: 'public'), false)
        ->assertSee('content-groups/covers/covered-show.jpg')
        ->assertSee('data-test="content-group-fallback"', false)
        ->assertSee('1 Episode');
});

it('shows public podcast detail pages with public episode descriptions only', function (): void {
    $category = Category::factory()->create(['name' => 'Detail Category']);
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Detail Podcast',
        'slug' => 'detail-podcast',
        'description_markdown' => 'Podcast **description**.',
    ]);
    $group->categories()->attach($category);

    $visibleItem = createStep8PublicItem($group, [
        'title' => 'Visible Episode',
        'description_markdown' => 'Visible episode description.',
    ]);
    $hiddenItem = ContentItem::factory()
        ->for($group)
        ->published()
        ->create([
            'title' => 'Hidden Episode',
            'description_markdown' => 'Hidden description.',
        ]);

    $this->get("/podcasts/{$group->slug}")
        ->assertSuccessful()
        ->assertSee($group->title)
        ->assertSee('<strong>description</strong>', false)
        ->assertSee($category->name)
        ->assertSee(BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public'), false)
        ->assertSee('1 Episode')
        ->assertSee($visibleItem->title)
        ->assertSee(ShowContentItem::getUrl([
            'contentGroupSlug' => $group->slug,
            'contentItemSlug' => $visibleItem->slug,
        ], panel: 'public'), false)
        ->assertSee('Visible episode description.')
        ->assertDontSee($hiddenItem->title)
        ->assertDontSee('Hidden description.');
});

it('returns not found for private or non-public podcast detail routes', function (): void {
    $draft = ContentGroup::factory()->create(['slug' => 'draft-podcast']);
    $empty = ContentGroup::factory()->published()->create(['slug' => 'empty-podcast']);

    $this->get("/podcasts/{$draft->slug}")->assertNotFound();
    $this->get("/podcasts/{$empty->slug}")->assertNotFound();
});

it('uses configured podcast and episode card template metadata', function (): void {
    saveStep8PublicFrontConfig([
        'card_templates' => [
            [
                'key' => 'step8_group_template',
                'label' => 'Step 8 Group Template',
                'family' => 'content_group',
                'layout' => 'rows',
                'density' => 'compact',
                'image_size' => 'small',
                'title_size' => 'lg',
                'parts' => [
                    [
                        'type' => 'title',
                        'source' => 'content_group',
                        'attribute' => 'title',
                    ],
                ],
            ],
            [
                'key' => 'step8_item_template',
                'label' => 'Step 8 Item Template',
                'family' => 'content_item',
                'layout' => 'rows',
                'density' => 'compact',
                'image_size' => 'small',
                'title_size' => 'lg',
                'parts' => [
                    [
                        'type' => 'description',
                        'source' => 'content_item',
                        'attribute' => 'description',
                    ],
                ],
            ],
        ],
        'podcasts_page' => [
            'template_key' => 'step8_group_template',
            'item_template_key' => 'step8_item_template',
        ],
    ]);

    $group = ContentGroup::factory()->published()->create([
        'title' => 'Templated Podcast',
        'slug' => 'templated-podcast',
    ]);
    createStep8PublicItem($group, [
        'title' => 'Templated Episode',
        'description_markdown' => 'Templated episode description.',
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('data-card-template-family="content_group"', false)
        ->assertSee('data-card-template-key="step8_group_template"', false);

    $this->get("/podcasts/{$group->slug}")
        ->assertSuccessful()
        ->assertSee('data-card-template-family="content_item"', false)
        ->assertSee('data-card-template-key="step8_item_template"', false)
        ->assertSee('Templated episode description.');
});

it('normalizes podcast detail episode grid settings safely', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'podcasts_page' => [
            'group_page' => [
                'items_layout' => 'grid grid-cols-4',
                'items_grid_columns' => 12,
                'items_grid_gap' => 'spacious',
                'items_per_page' => 5,
                'page_size_options' => [5, 12, 'bad'],
                'sort_options' => ['title_desc', 'unknown_sort'],
                'default_sort' => 'duration_shortest',
                'item_density' => 'compact',
                'item_image_size' => 'large',
                'item_image_fit' => 'contain',
                'item_image_radius' => 'round',
                'item_title_size' => 'lg',
            ],
        ],
    ]);

    $groupPage = $result->group('podcasts_page')['group_page'];
    $invalidPaths = collect($result->invalidConfigArray())->pluck('path')->all();

    expect($groupPage['items_layout'])->toBe('cards')
        ->and($groupPage['items_grid_columns'])->toBe(3)
        ->and($groupPage['items_grid_gap'])->toBe('spacious')
        ->and($groupPage['items_per_page'])->toBe(5)
        ->and($groupPage['page_size_options'])->toBe([5, 12])
        ->and($groupPage['sort_options'])->toBe(['title_desc'])
        ->and($groupPage['default_sort'])->toBe('title_desc')
        ->and($groupPage['item_density'])->toBe('compact')
        ->and($groupPage['item_image_size'])->toBe('large')
        ->and($groupPage['item_image_fit'])->toBe('contain')
        ->and($groupPage['item_image_radius'])->toBe('round')
        ->and($groupPage['item_title_size'])->toBe('lg')
        ->and($invalidPaths)->toContain(
            'podcasts_page.group_page.items_layout',
            'podcasts_page.group_page.items_grid_columns',
            'podcasts_page.group_page.page_size_options.2',
            'podcasts_page.group_page.sort_options.1',
            'podcasts_page.group_page.default_sort',
        );
});

it('renders configured podcast detail episode grid controls and card display settings', function (): void {
    saveStep8PublicFrontConfig([
        'podcasts_page' => [
            'group_page' => [
                'items_layout' => 'cards',
                'items_grid_columns' => 4,
                'items_grid_gap' => 'spacious',
                'items_per_page' => 6,
                'page_size_options' => [6, 12],
                'per_page_selector_enabled' => true,
                'search_enabled' => true,
                'sort_enabled' => true,
                'category_filter_enabled' => true,
                'default_sort' => 'title_desc',
                'sort_options' => ['title_desc', 'title_asc'],
                'item_density' => 'compact',
                'item_image_size' => 'small',
                'item_image_fit' => 'contain',
                'item_image_radius' => 'round',
                'item_title_size' => 'lg',
                'show_episode_descriptions' => true,
                'show_episode_authors' => false,
                'show_episode_tags' => false,
                'show_episode_duration' => false,
                'show_episode_effective_date' => false,
            ],
        ],
    ]);

    $category = Category::factory()->create(['name' => 'Episode Category']);
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Grid Podcast',
        'slug' => 'grid-podcast',
    ]);
    $item = createStep8PublicItem($group, [
        'title' => 'Grid Episode',
        'description_markdown' => 'Grid episode description.',
    ]);
    $item->categories()->attach($category);

    $this->get("/podcasts/{$group->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="group-item-controls"', false)
        ->assertSee('data-test="group-item-search"', false)
        ->assertSee('data-test="item-sort"', false)
        ->assertSee('value="title_desc"', false)
        ->assertSee('data-test="group-item-per-page"', false)
        ->assertSee('data-test="group-item-category-toggle"', false)
        ->assertSee('data-result-layout="cards"', false)
        ->assertSee('data-grid-columns="4"', false)
        ->assertSee('data-grid-gap="spacious"', false)
        ->assertSee('data-card-density="compact"', false)
        ->assertSee('data-card-image-size="small"', false)
        ->assertSee('data-card-image-fit="contain"', false)
        ->assertSee('data-card-image-radius="round"', false)
        ->assertSee('data-card-title-size="lg"', false)
        ->assertSee('Grid episode description.');
});

it('filters sorts and paginates podcast detail episodes through Livewire state', function (): void {
    saveStep8PublicFrontConfig([
        'podcasts_page' => [
            'group_page' => [
                'items_layout' => 'cards',
                'items_grid_columns' => 2,
                'items_per_page' => 6,
                'page_size_options' => [6, 12],
                'category_filter_enabled' => true,
                'default_sort' => 'title_asc',
                'sort_options' => ['title_asc', 'title_desc'],
            ],
        ],
    ]);

    $category = Category::factory()->create(['name' => 'Selected Category']);
    $otherCategory = Category::factory()->create(['name' => 'Other Category']);
    $group = ContentGroup::factory()->published()->create(['title' => 'Filter Podcast']);

    $alpha = createStep8PublicItem($group, ['title' => 'Alpha Episode']);
    $zulu = createStep8PublicItem($group, ['title' => 'Zulu Episode']);
    $other = createStep8PublicItem($group, ['title' => 'Other Episode']);

    $alpha->categories()->attach($category);
    $zulu->categories()->attach($category);
    $other->categories()->attach($otherCategory);

    Livewire::withQueryParams([
        'itemCategories' => (string) $category->id,
        'sort' => 'title_desc',
        'perPage' => '6',
    ])
        ->test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->assertSet('categoryIds', [$category->id])
        ->assertSet('sort', 'title_desc')
        ->assertSet('perPage', '6')
        ->assertSeeInOrder(['Zulu Episode', 'Alpha Episode'])
        ->assertDontSee('Other Episode')
        ->set('sort', 'title_asc')
        ->assertSeeInOrder(['Alpha Episode', 'Zulu Episode'])
        ->assertDontSee('Other Episode');
});

it('does not expose the old groups route or introduce podcast episode models', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'legacy-route-podcast']);
    createStep8PublicItem($group);

    $this->get("/groups/{$group->slug}")->assertNotFound();

    expect(class_exists(Podcast::class))->toBeFalse()
        ->and(class_exists(Episode::class))->toBeFalse();
});
