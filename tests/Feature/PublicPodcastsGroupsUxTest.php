<?php

use App\Enums\PublicationStatus;
use App\Livewire\Public\ContentGroupBrowser;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Episode;
use App\Models\Podcast;
use App\Settings\PublicContentSettings;
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
        ->assertSee('1 Episode')
        ->assertSee($visibleItem->title)
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

it('does not expose the old groups route or introduce podcast episode models', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'legacy-route-podcast']);
    createStep8PublicItem($group);

    $this->get("/groups/{$group->slug}")->assertNotFound();

    expect(class_exists(Podcast::class))->toBeFalse()
        ->and(class_exists(Episode::class))->toBeFalse();
});
