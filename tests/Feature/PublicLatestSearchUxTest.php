<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Livewire\Public\ContentItemSearch;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\PublicFront\Sections\PublicDisplaySectionConfigValidator;
use App\Support\PublicFront\Sections\PublicDisplaySectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function createStep5PublicItem(
    array $itemAttributes = [],
    array $transcriptionAttributes = [],
    ?ContentGroup $group = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();
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

function createStep5LatestSection(array $overrides = []): HomepageSection
{
    return HomepageSection::factory()->create([
        'name' => 'Latest Step 5',
        'type' => HomepageSectionType::Latest,
        'limit' => 4,
        'sort_order' => 1,
        'source_config' => [
            'source_type' => 'latest_content_items',
            'sort' => 'latest_transcription',
            'total_limit' => 50,
        ],
        'pagination_config' => [
            'mode' => 'next_previous',
            'per_page' => 4,
            'total_limit' => 50,
        ],
        ...$overrides,
    ]);
}

function saveStep5PublicFrontConfig(array $config): void
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

it('renders latest as a resolved looper section with lightweight search and next previous controls', function (): void {
    $section = createStep5LatestSection();
    $sectionKey = "section-{$section->id}";

    $items = collect(range(1, 8))->map(fn (int $index): ContentItem => createStep5PublicItem([
        'title' => sprintf('Latest Step5 %02d', $index),
    ], [
        'published_at' => now()->subMinutes($index),
    ]));

    Livewire::test(ContentItemSearch::class)
        ->assertSee('data-test="latest-controls"', false)
        ->assertSee('data-test="latest-search"', false)
        ->assertSee('data-test="latest-next-previous"', false)
        ->assertSee($items[0]->title)
        ->assertSee($items[3]->title)
        ->assertDontSee($items[4]->title)
        ->call('nextLatestPage', $sectionKey, 2)
        ->assertSee($items[4]->title)
        ->assertSee($items[7]->title)
        ->assertDontSee($items[0]->title)
        ->call('previousLatestPage', $sectionKey)
        ->assertSee($items[0]->title)
        ->set('latestSearch', [$sectionKey => '08'])
        ->assertSee($items[7]->title)
        ->assertDontSee($items[0]->title)
        ->assertDontSee('data-test="filter-category-toggle"', false);
});

it('supports latest load more mode from normalized pagination config', function (): void {
    $section = createStep5LatestSection([
        'pagination_config' => [
            'mode' => 'load_more',
            'per_page' => 4,
            'total_limit' => 50,
        ],
    ]);
    $sectionKey = "section-{$section->id}";

    $items = collect(range(1, 9))->map(fn (int $index): ContentItem => createStep5PublicItem([
        'title' => sprintf('Load More Step5 %02d', $index),
    ], [
        'published_at' => now()->subMinutes($index),
    ]));

    Livewire::test(ContentItemSearch::class)
        ->assertSee('data-test="latest-load-more"', false)
        ->assertSee($items[0]->title)
        ->assertSee($items[3]->title)
        ->assertDontSee($items[4]->title)
        ->call('loadMoreLatest', $sectionKey, 3)
        ->assertSee($items[4]->title)
        ->assertSee($items[7]->title)
        ->assertDontSee($items[8]->title);
});

it('normalizes latest page size and total query window for at least fifty results', function (): void {
    collect(range(1, 55))->each(fn (int $index): ContentItem => createStep5PublicItem([
        'title' => sprintf('Window Step5 %02d', $index),
    ], [
        'published_at' => now()->subMinutes($index),
    ]));

    $section = createStep5LatestSection([
        'limit' => 2,
        'source_config' => [
            'source_type' => 'latest_content_items',
            'total_limit' => 10,
        ],
        'pagination_config' => [
            'mode' => 'none',
            'per_page' => 2,
            'total_limit' => 10,
        ],
    ]);

    $config = app(PublicDisplaySectionConfigValidator::class)->validate($section);
    $resolved = app(PublicDisplaySectionResolver::class)->resolve($section);

    expect($config->paginationConfig['per_page'])->toBe(4)
        ->and($config->paginationConfig['total_limit'])->toBe(50)
        ->and($resolved?->items)->toHaveCount(50);
});

it('renders the search filter drawer with category toggles tag chips and active counts', function (): void {
    $firstCategory = Category::factory()->create(['name' => 'Drawer Category A']);
    $secondCategory = Category::factory()->create(['name' => 'Drawer Category B']);
    $thirdCategory = Category::factory()->create(['name' => 'Drawer Category C']);

    $firstItem = createStep5PublicItem(['title' => 'Drawer Category Item A']);
    $firstItem->categories()->attach($firstCategory);
    $secondItem = createStep5PublicItem(['title' => 'Drawer Category Item B']);
    $secondItem->categories()->attach($secondCategory);
    $thirdItem = createStep5PublicItem(['title' => 'Drawer Category Item C']);
    $thirdItem->categories()->attach($thirdCategory);

    $enabledTag = ContentTag::findOrCreateFromString('Drawer Enabled Tag', 'content')->enable();
    $disabledTag = ContentTag::findOrCreateFromString('Hidden Disabled Topic', 'content');
    $taggedItem = createStep5PublicItem(['title' => 'Drawer Tagged Item']);
    $taggedItem->attachTag($enabledTag);
    $disabledTaggedItem = createStep5PublicItem(['title' => 'Hidden Topic Item']);
    $disabledTaggedItem->attachTag($disabledTag);

    Livewire::test(ContentItemSearch::class, ['context' => 'search'])
        ->assertSee('data-test="open-filter-drawer"', false)
        ->assertSee('data-test="filter-drawer"', false)
        ->assertSee('x-on:open-public-filter-drawer.window', false)
        ->assertSee('data-test="filter-category-toggle"', false)
        ->assertSee('data-test="filter-tag-toggle"', false)
        ->assertSee($enabledTag->name)
        ->assertDontSee($disabledTag->name)
        ->call('toggleCategoryFilter', $firstCategory->id)
        ->call('toggleCategoryFilter', $secondCategory->id)
        ->assertSet('filterCategories', "{$firstCategory->id},{$secondCategory->id}")
        ->assertSee($firstItem->title)
        ->assertSee($secondItem->title)
        ->assertDontSee($thirdItem->title)
        ->assertSee(trans_choice('public.filters.active_count', 2, ['count' => 2]))
        ->call('clearFilters')
        ->assertSet('filterCategories', '')
        ->assertSet('filterTags', '')
        ->assertSee($thirdItem->title)
        ->call('toggleTagFilter', $enabledTag->id)
        ->assertSet('filterTags', (string) $enabledTag->id)
        ->assertSee($taggedItem->title)
        ->assertDontSee($disabledTaggedItem->title);
});

it('hydrates url backed search sort category and tag state', function (): void {
    $category = Category::factory()->create(['name' => 'URL Category']);
    $tag = ContentTag::findOrCreateFromString('URL Tag', 'content')->enable();
    $matching = createStep5PublicItem(['title' => 'URL Needle Episode']);
    $matching->categories()->attach($category);
    $matching->attachTag($tag);
    createStep5PublicItem(['title' => 'URL Other Episode']);

    Livewire::withQueryParams([
        'q' => 'Needle',
        'sort' => 'title_asc',
        'categories' => (string) $category->id,
        'tags' => (string) $tag->id,
    ])
        ->test(ContentItemSearch::class)
        ->assertSet('search', 'Needle')
        ->assertSet('sort', 'title_asc')
        ->assertSet('filterCategories', (string) $category->id)
        ->assertSet('filterTags', (string) $tag->id)
        ->assertSee($matching->title)
        ->assertDontSee('URL Other Episode');
});

it('uses deterministic card layout classes and stacks large image templates safely', function (): void {
    $item = createStep5PublicItem([
        'title' => 'Large Image Layout Episode',
        'description_markdown' => 'Large card description',
        'external_thumbnail_url' => 'https://example.com/thumb.jpg',
    ]);

    createStep5LatestSection([
        'selection_config' => [
            'include_ids' => [$item->id],
        ],
        'display_config' => [
            'template_family' => 'content_item',
            'template_overrides' => [
                'layout' => 'rows',
                'density' => 'compact',
                'image_size' => 'large',
                'title_size' => 'lg',
            ],
        ],
        'pagination_config' => [
            'mode' => 'none',
            'per_page' => 4,
            'total_limit' => 50,
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee($item->title)
        ->assertSee('data-card-image-size="large"', false)
        ->assertSee('data-result-layout="cards"', false)
        ->assertSee('data-card-renderer-parts="image,group_identity,title,description,date_read_time,metadata_row"', false)
        ->assertSee('data-card-title-clamp="2"', false)
        ->assertSee('data-card-description-clamp="3"', false)
        ->assertSee('aspect-square', false)
        ->assertSee('object-cover', false)
        ->assertSee('overflow-hidden', false)
        ->assertSee('min-w-0', false)
        ->assertDontSee('fi-ta-table', false);
});

it('does not break public rendering when configured card template parts are invalid', function (): void {
    $item = createStep5PublicItem(['title' => 'Invalid Renderer Parts Episode']);

    saveStep5PublicFrontConfig([
        'card_templates' => [
            [
                'key' => 'default_content_item',
                'label' => 'Invalid renderer parts',
                'family' => 'content_item',
                'layout' => 'cards',
                'density' => 'comfortable',
                'image_size' => 'medium',
                'title_size' => 'base',
                'parts' => [
                    [
                        'type' => 'raw_html',
                        'source' => 'content_item',
                        'attribute' => 'title',
                    ],
                    [
                        'type' => 'title',
                        'source' => 'content_item',
                        'attribute' => 'App\\Unsafe\\Card::class',
                    ],
                ],
            ],
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee($item->title)
        ->assertSee('data-test="content-item-card"', false)
        ->assertSee('data-card-template-parts=""', false)
        ->assertDontSee('App\\Unsafe\\Card::class');
});

it('keeps public visibility constraints for latest sections', function (): void {
    $visible = createStep5PublicItem(['title' => 'Step5 Visible Public Item']);
    createStep5PublicItem(['title' => 'Step5 Draft Group Item'], [], ContentGroup::factory()->create());
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->withTranscription()
        ->create(['title' => 'Step5 Draft Item']);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->create(['title' => 'Step5 No Transcription Item']);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ])
        ->create(['title' => 'Step5 Draft Transcription Item']);

    createStep5LatestSection();

    Livewire::test(ContentItemSearch::class)
        ->assertSee($visible->title)
        ->assertDontSee('Step5 Draft Group Item')
        ->assertDontSee('Step5 Draft Item')
        ->assertDontSee('Step5 No Transcription Item')
        ->assertDontSee('Step5 Draft Transcription Item');
});
