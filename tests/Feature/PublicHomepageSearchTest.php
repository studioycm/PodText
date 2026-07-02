<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Livewire\Public\ContentItemSearch;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function createPrompt11PublicItem(array $itemAttributes = [], array $transcriptionAttributes = [], ?ContentGroup $group = null): ContentItem
{
    $group ??= ContentGroup::factory()->published()->create();

    return ContentItem::factory()
        ->for($group)
        ->published($itemAttributes['published_at'] ?? now()->subMinute())
        ->withTranscription([
            'published_at' => $transcriptionAttributes['published_at'] ?? now()->subMinute(),
            ...$transcriptionAttributes,
        ])
        ->create($itemAttributes);
}

function savePrompt11PublicSettings(array $overrides): void
{
    $settings = app(PublicContentSettings::class);

    $defaults = [
        'homepage_item_limit' => 12,
        'pinned_item_limit' => 6,
        'default_public_sort' => 'latest_transcription',
        'default_result_layout' => 'cards',
        'show_latest_section' => true,
        'item_page_layout' => 'standard',
        'homepage_card_image_size' => 'medium',
        'homepage_card_density' => 'comfortable',
        'homepage_card_title_size' => 'base',
        'homepage_show_group_badge' => true,
        'homepage_show_authors' => true,
        'homepage_show_categories' => true,
        'homepage_show_tags' => true,
        'homepage_show_duration' => true,
        'homepage_show_effective_date' => true,
        'homepage_show_description' => true,
        'homepage_description_lines' => 3,
        'homepage_cards_per_page' => 12,
    ];

    foreach ([...$defaults, ...$overrides] as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();

    foreach ([...$defaults, ...$overrides] as $key => $value) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => 'public_content',
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
    app(SettingsContainer::class)->clearCache();
}

it('allows guests to access the homepage and renders content item cards with rtl markers', function (): void {
    $item = createPrompt11PublicItem(['title' => 'Visible Episode']);

    $response = $this->get('/');

    file_put_contents('/tmp/podtext-public-homepage.html', $response->getContent());

    $response
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('public.pages.browse.title'))
        ->assertSee('data-test="content-item-card"', false)
        ->assertSee($item->title)
        ->assertDontSee('data-test="group-search"', false);
});

it('shows group badges when enabled and hides them when disabled', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => 'Badge Podcast']);
    $item = createPrompt11PublicItem(['title' => 'Badge Episode'], [], $group);

    savePrompt11PublicSettings(['homepage_show_group_badge' => true]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($item->title)
        ->assertSee('data-test="group-badge"', false);

    savePrompt11PublicSettings(['homepage_show_group_badge' => false]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($item->title)
        ->assertDontSee('data-test="group-badge"', false);
});

it('consumes card field visibility and semantic display settings', function (): void {
    $author = Author::factory()->create(['name' => 'Card Author']);
    $category = Category::factory()->create(['name' => 'Card Category']);
    $tag = ContentTag::findOrCreate('Card Tag', 'content')->enable();

    $item = createPrompt11PublicItem([
        'title' => 'Settings Episode',
        'description_markdown' => 'Visible description text',
        'duration_seconds' => 125,
        'external_thumbnail_url' => 'https://example.com/thumb.jpg',
    ], [
        'published_at' => now()->subDay(),
    ]);
    $item->authors()->attach($author);
    $item->categories()->attach($category);
    $item->attachTag($tag);

    savePrompt11PublicSettings([
        'homepage_card_image_size' => 'large',
        'homepage_card_density' => 'compact',
        'homepage_card_title_size' => 'lg',
        'homepage_description_lines' => 2,
        'homepage_show_authors' => true,
        'homepage_show_categories' => true,
        'homepage_show_tags' => true,
        'homepage_show_duration' => true,
        'homepage_show_effective_date' => true,
        'homepage_show_description' => true,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-card-image-size="large"', false)
        ->assertSee('data-card-density="compact"', false)
        ->assertSee('data-card-title-size="lg"', false)
        ->assertSee($author->name)
        ->assertSee($category->name)
        ->assertSee($tag->name)
        ->assertSee('Visible description text')
        ->assertSee(now()->subDay()->format('d/m/Y'));

    savePrompt11PublicSettings([
        'homepage_show_authors' => false,
        'homepage_show_categories' => false,
        'homepage_show_tags' => false,
        'homepage_show_duration' => false,
        'homepage_show_effective_date' => false,
        'homepage_show_description' => false,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee($item->title)
        ->assertDontSee('data-test="item-author"', false)
        ->assertDontSee('data-test="item-categories"', false)
        ->assertDontSee('data-test="item-tags"', false)
        ->assertDontSee('data-test="duration"', false)
        ->assertDontSee('data-test="effective-date"', false)
        ->assertDontSee('data-test="item-description"', false);
});

it('hides draft groups draft items and items without published effective transcriptions', function (): void {
    $visible = createPrompt11PublicItem(['title' => 'Published Episode']);
    $draftGroup = ContentGroup::factory()->create();
    createPrompt11PublicItem(['title' => 'Draft Group Episode'], [], $draftGroup);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->withTranscription()
        ->create(['title' => 'Draft Item Episode']);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->create(['title' => 'No Transcript Episode']);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ])
        ->create(['title' => 'Draft Transcript Episode']);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($visible->title)
        ->assertDontSee('Draft Group Episode')
        ->assertDontSee('Draft Item Episode')
        ->assertDontSee('No Transcript Episode')
        ->assertDontSee('Draft Transcript Episode');
});

it('orders by latest effective transcription and keeps valid pinned items first on homepage default', function (): void {
    $newer = createPrompt11PublicItem(['title' => 'Newer Transcript'], ['published_at' => now()->subDay()]);
    $olderPinned = createPrompt11PublicItem([
        'title' => 'Pinned Older Transcript',
        'is_pinned' => true,
        'pinned_at' => now()->subMinute(),
        'pin_order' => 1,
    ], [
        'published_at' => now()->subDays(5),
    ]);
    $oldest = createPrompt11PublicItem(['title' => 'Oldest Transcript'], ['published_at' => now()->subDays(10)]);

    Livewire::test(ContentItemSearch::class)
        ->assertSeeInOrder([$olderPinned->title, $newer->title, $oldest->title]);

    Livewire::test(ContentItemSearch::class)
        ->set('sort', 'latest_transcription')
        ->assertSeeInOrder([$newer->title, $olderPinned->title, $oldest->title]);
});

it('searches item title group title category and enabled content tag names only', function (): void {
    $groupMatch = ContentGroup::factory()->published()->create(['title' => 'Signal Podcast']);
    $groupItem = createPrompt11PublicItem(['title' => 'Group Match Episode'], [], $groupMatch);
    $titleItem = createPrompt11PublicItem(['title' => 'Needle Episode']);
    $category = Category::factory()->create(['name' => 'Research Category']);
    $categoryItem = createPrompt11PublicItem(['title' => 'Category Episode']);
    $categoryItem->categories()->attach($category);
    $enabledTag = ContentTag::findOrCreate('Visible Topic', 'content')->enable();
    $tagItem = createPrompt11PublicItem(['title' => 'Tag Episode']);
    $tagItem->attachTag($enabledTag);
    $disabledTag = ContentTag::findOrCreate('Hidden Topic', 'content');
    $disabledTagItem = createPrompt11PublicItem(['title' => 'Disabled Tag Episode']);
    $disabledTagItem->attachTag($disabledTag);

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Needle')
        ->assertSee($titleItem->title)
        ->assertDontSee($groupItem->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Signal')
        ->assertSee($groupItem->title)
        ->assertDontSee($titleItem->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Research')
        ->assertSee($categoryItem->title)
        ->assertDontSee($titleItem->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Visible Topic')
        ->assertSee($tagItem->title)
        ->assertDontSee($disabledTagItem->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Hidden Topic')
        ->assertDontSee($disabledTagItem->title);
});

it('filters by descendant categories inherited group categories enabled tags groups authors and providers', function (): void {
    $parent = Category::factory()->create(['name' => 'Parent']);
    $child = Category::factory()->for($parent, 'parent')->create(['name' => 'Child']);
    $group = ContentGroup::factory()->published()->create(['title' => 'Filtered Group']);
    $group->categories()->attach($child);
    $inherited = createPrompt11PublicItem(['title' => 'Inherited Category Episode'], [], $group);

    $tag = ContentTag::findOrCreate('Filter Tag', 'content')->enable();
    $tagged = createPrompt11PublicItem(['title' => 'Tagged Episode']);
    $tagged->attachTag($tag);

    $author = Author::factory()->create(['name' => 'Filter Author']);
    $authored = createPrompt11PublicItem(['title' => 'Authored Episode']);
    $authored->authors()->attach($author);

    $provider = createPrompt11PublicItem([
        'title' => 'Provider Episode',
        'embed_provider' => 'youtube',
    ]);

    Livewire::test(ContentItemSearch::class)
        ->set('tableFilters.category.value', $parent->id)
        ->assertSee($inherited->title)
        ->assertDontSee($tagged->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableFilters.tag.value', $tag->id)
        ->assertSee($tagged->title)
        ->assertDontSee($inherited->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableFilters.content_group_id.value', $group->id)
        ->assertSee($inherited->title)
        ->assertDontSee($tagged->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableFilters.author.value', $author->id)
        ->assertSee($authored->title)
        ->assertDontSee($tagged->title);

    Livewire::test(ContentItemSearch::class)
        ->set('tableFilters.embed_provider.value', 'youtube')
        ->assertSee($provider->title)
        ->assertDontSee($tagged->title);
});

it('renders category and tag landing pages with matching public content items', function (): void {
    $category = Category::factory()->create(['name' => 'Landing Category', 'slug' => 'landing-category']);
    $categoryItem = createPrompt11PublicItem(['title' => 'Category Landing Episode']);
    $categoryItem->categories()->attach($category);

    $tag = ContentTag::findOrCreate('Landing Tag', 'content')->enable();
    $tagItem = createPrompt11PublicItem(['title' => 'Tag Landing Episode']);
    $tagItem->attachTag($tag);

    $this->get("/categories/{$category->slug}")
        ->assertSuccessful()
        ->assertSee($category->name)
        ->assertSee($categoryItem->title)
        ->assertDontSee($tagItem->title);

    $this->get("/tags/{$tag->slug}")
        ->assertSuccessful()
        ->assertSee($tag->name)
        ->assertSee($tagItem->title)
        ->assertDontSee($categoryItem->title);
});

it('shows result count supports url backed state clear filters and empty state', function (): void {
    $matching = createPrompt11PublicItem(['title' => 'Url Episode']);
    createPrompt11PublicItem(['title' => 'Other Episode']);

    Livewire::withQueryParams(['q' => 'Url', 'sort' => 'title_asc'])
        ->test(ContentItemSearch::class)
        ->assertSet('tableSearch', 'Url')
        ->assertSet('sort', 'title_asc')
        ->assertSee($matching->title)
        ->assertSee(trans_choice('public.results.count', 1, ['count' => 1]))
        ->call('clearFilters')
        ->assertSet('tableSearch', '')
        ->assertSet('sort', 'latest_transcription')
        ->assertSee(trans_choice('public.results.count', 2, ['count' => 2]));

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Nothing Matches')
        ->assertSee(__('public.empty.items'));
});

it('uses visible ordered homepage sections for latest category tag and content group slices', function (): void {
    $category = Category::factory()->create();
    $tag = ContentTag::findOrCreate('Section Tag', 'content')->enable();
    $group = ContentGroup::factory()->published()->create();

    $categoryItem = createPrompt11PublicItem(['title' => 'Section Category Episode']);
    $categoryItem->categories()->attach($category);
    $tagItem = createPrompt11PublicItem(['title' => 'Section Tag Episode']);
    $tagItem->attachTag($tag);
    $groupItem = createPrompt11PublicItem(['title' => 'Section Group Episode'], [], $group);
    $outside = createPrompt11PublicItem(['title' => 'Outside Episode']);

    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Category,
        'category_id' => $category->id,
        'sort_order' => 1,
    ]);
    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Tag,
        'tag_id' => $tag->id,
        'sort_order' => 2,
    ]);
    HomepageSection::factory()->create([
        'type' => HomepageSectionType::ContentGroup,
        'content_group_id' => $group->id,
        'sort_order' => 3,
    ]);
    HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'is_visible' => false,
        'sort_order' => 0,
    ]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($categoryItem->title)
        ->assertSee($tagItem->title)
        ->assertSee($groupItem->title)
        ->assertDontSee($outside->title);
});

it('uses safe defaults when old settings rows are missing', function (): void {
    DB::table('settings')
        ->where('group', 'public_content')
        ->whereIn('name', ['homepage_card_image_size', 'homepage_cards_per_page'])
        ->delete();

    app()->forgetInstance(PublicContentSettings::class);

    $options = PublicContentCardOptions::fromSettings();

    expect($options->imageSize)->toBe('medium')
        ->and($options->cardsPerPage)->toBe(12);
});
