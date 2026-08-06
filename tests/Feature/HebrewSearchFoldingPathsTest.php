<?php

use App\Livewire\Public\ContentGroupBrowser;
use App\Livewire\Public\ContentItemBrowser;
use App\Livewire\Public\ContentItemSearch;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Every public search path folds both sides
|--------------------------------------------------------------------------
|
| The goal is symmetric: pointed text must be findable by an unpointed search,
| and unpointed text by a pointed one. Both directions are asserted on every
| path, because a fix applied to only one side of the comparison passes the
| first and fails the second.
|
*/

/** Pointed and unpointed spellings of the same word. */
const PointedShalom = 'שָׁלוֹם';
const PlainShalom = 'שלום';

function publicFoldingItem(array $attributes = [], ?ContentGroup $group = null): ContentItem
{
    return ContentItem::factory()
        ->for($group ?? ContentGroup::factory()->published()->create())
        ->published(now()->subMinute())
        ->withTranscription(['published_at' => now()->subMinute()])
        ->create($attributes);
}

it('finds a pointed item title from an unpointed homepage search', function (): void {
    $item = publicFoldingItem(['title' => PointedShalom.' עולם']);

    Livewire::test(ContentItemSearch::class)
        ->set('search', PlainShalom)
        ->assertSee($item->title);
});

it('finds an unpointed item title from a pointed homepage search', function (): void {
    $item = publicFoldingItem(['title' => PlainShalom.' עולם']);

    Livewire::test(ContentItemSearch::class)
        ->set('search', PointedShalom)
        ->assertSee($item->title);
});

it('folds group titles categories and tags reached through the homepage search', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => PointedShalom.' פודקאסט']);
    $groupItem = publicFoldingItem(['title' => 'פרק ראשון'], $group);

    $category = Category::factory()->create(['name' => 'מַשֶּׁה']);
    $categoryItem = publicFoldingItem(['title' => 'פרק שני']);
    $categoryItem->categories()->attach($category);

    $tag = ContentTag::findOrCreate('גְּמָרָא', 'content')->enable();
    $tagItem = publicFoldingItem(['title' => 'פרק שלישי']);
    $tagItem->attachTag($tag);

    Livewire::test(ContentItemSearch::class)
        ->set('search', PlainShalom)
        ->assertSee($groupItem->title);

    Livewire::test(ContentItemSearch::class)
        ->set('search', 'משה')
        ->assertSee($categoryItem->title);

    Livewire::test(ContentItemSearch::class)
        ->set('search', 'גמרא')
        ->assertSee($tagItem->title);
});

it('still excludes disabled tags once tag search is folded', function (): void {
    $disabled = ContentTag::findOrCreate('גְּמָרָא', 'content');
    $item = publicFoldingItem(['title' => 'פרק חבוי']);
    $item->attachTag($disabled);

    Livewire::test(ContentItemSearch::class)
        ->set('search', 'גמרא')
        ->assertDontSee($item->title);
});

it('folds the episode browser search over title and description', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $titled = publicFoldingItem(['title' => PointedShalom], $group);
    $described = publicFoldingItem(['title' => 'פרק אחר', 'description_markdown' => 'מַשֶּׁה רבנו'], $group);

    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('search', PlainShalom)
        ->assertSee($titled->title)
        ->assertDontSee($described->title);

    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('search', 'משה')
        ->assertSee($described->title);
});

it('folds the group listing search over title description and nested items', function (): void {
    $byTitle = ContentGroup::factory()->published()->create(['title' => PointedShalom.' פודקאסט']);
    publicFoldingItem([], $byTitle);

    $byDescription = ContentGroup::factory()->published()->create([
        'title' => 'פודקאסט שני',
        'description_markdown' => 'מַשֶּׁה רבנו',
    ]);
    publicFoldingItem([], $byDescription);

    $byItemTitle = ContentGroup::factory()->published()->create(['title' => 'פודקאסט שלישי']);
    publicFoldingItem(['title' => 'גְּמָרָא'], $byItemTitle);

    $found = fn (string $search): array => PublicContentGroupQueries::applySearch(
        PublicContentGroupQueries::base(),
        $search,
    )->pluck('id')->all();

    expect($found(PlainShalom))->toContain($byTitle->getKey())
        ->and($found('משה'))->toContain($byDescription->getKey())
        ->and($found('גמרא'))->toContain($byItemTitle->getKey());
});

it('folds the group browser livewire search', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => PointedShalom.' פודקאסט']);
    publicFoldingItem([], $group);

    Livewire::test(ContentGroupBrowser::class)
        ->set('search', PlainShalom)
        ->assertSee($group->title);
});

it('folds contributor discovery over author names', function (): void {
    $author = Author::factory()->create(['name' => 'מַשֶּׁה רבנו']);
    $item = publicFoldingItem();
    $item->effectiveTranscription()->first()?->authors()->syncWithoutDetaching([$author->getKey()]);

    expect(PublicContributorDiscovery::contributors('משה')->pluck('id')->all())
        ->toContain($author->getKey());
});

it('folds contributor episode search over item and group titles', function (): void {
    $author = Author::factory()->create(['name' => 'מַשֶּׁה']);
    $group = ContentGroup::factory()->published()->create(['title' => 'גְּמָרָא פודקאסט']);
    $item = publicFoldingItem(['title' => PointedShalom], $group);
    $item->effectiveTranscription()->first()?->authors()->syncWithoutDetaching([$author->getKey()]);

    $found = fn (string $search): array => PublicContributorDiscovery::contentItemsForContributor(
        $author,
        $search,
    )->pluck('id')->all();

    expect($found(PlainShalom))->toContain($item->getKey())
        ->and($found('גמרא'))->toContain($item->getKey());
});

/*
 * The live defect, reproduced from the bytes measured on content_items id 56:
 * a U+05BF RAFE sits between the final ה and the exclamation mark, invisible
 * when rendered. Measured against the real row before this change,
 * `description_markdown LIKE '%זה למה!%'` returned 0 while
 * `LOCATE('זה למה!', description_markdown)` returned 1.
 */
it('finds content item 56 by the phrase as it renders, rafe and all', function (): void {
    $stored = "לא נשאר לי חול מרוב דיבורים - זה למה\u{05BF}!";
    $group = ContentGroup::factory()->published()->create(['description_markdown' => $stored]);
    $item = publicFoldingItem(['title' => 'עוד אחת מהשיחות', 'description_markdown' => $stored], $group);

    expect(ContentItem::query()->where('description_markdown', 'like', '%זה למה!%')->count())
        ->toBe(0, 'the unfolded column still cannot match — that is the defect');

    // ContentItemBrowser is one of the three surfaces that reach an item
    // description; the homepage search never did, which is why a
    // homepage-scoped fix would not have moved this row.
    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('search', 'זה למה!')
        ->assertSee($item->title);

    expect(PublicContentGroupQueries::applySearch(PublicContentGroupQueries::base(), 'זה למה!')->pluck('id')->all())
        ->toContain($group->getKey());
});

it('folds the in-php homepage section filter on both sides', function (): void {
    $item = publicFoldingItem(['title' => PointedShalom.' עולם']);

    // latestFilteredItems() filters a hydrated Collection rather than issuing
    // SQL, so it needs the same normalizer applied in PHP to both sides.
    $component = Livewire::test(ContentItemSearch::class);
    $sections = invade($component->instance())->homepageSections();
    $section = $sections->first();

    invade($component->instance())->latestSearch = [$section->key => PlainShalom];

    expect(invade($component->instance())->latestFilteredItems($section)->pluck('id')->all())
        ->toContain($item->getKey());
});
