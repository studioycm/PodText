<?php

use App\Livewire\Public\ContentItemBrowser;
use App\Livewire\Public\ContentItemSearch;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows guests to browse the public panel root with rtl layout markers', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'שיחות עם ניקוד',
        'slug' => 'published-hebrew-group',
    ]);
    $item = ContentItem::factory()->for($group)->published()->withTranscription()->create([
        'title' => 'פרק ציבורי',
        'slug' => 'public-item',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('public.pages.browse.title'))
        ->assertSee($item->title)
        ->assertSee('data-test="content-item-card"', false);
});

it('shows only public content items on the browse page', function (): void {
    $published = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription()
        ->create(['title' => 'Published Item']);
    $draft = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->withTranscription()
        ->create(['title' => 'Draft Item']);
    $future = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published(now()->addDay())
        ->withTranscription()
        ->create(['title' => 'Future Item']);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($published->title)
        ->assertDontSee($draft->title)
        ->assertDontSee($future->title);
});

it('searches public items by title and stores search in url state', function (): void {
    $matching = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription()
        ->create(['title' => 'Alpha Episode']);
    $other = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription()
        ->create(['title' => 'Beta Episode']);

    Livewire::test(ContentItemSearch::class)
        ->set('tableSearch', 'Alpha')
        ->assertSee($matching->title)
        ->assertDontSee($other->title);

    Livewire::withQueryParams(['q' => 'Alpha'])
        ->test(ContentItemSearch::class)
        ->assertSet('tableSearch', 'Alpha')
        ->assertSee($matching->title)
        ->assertDontSee($other->title);
});

it('sorts public items by latest transcription and title', function (): void {
    $alpha = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription(['published_at' => now()->subDays(2)])
        ->create(['title' => 'Alpha Item']);
    $beta = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription(['published_at' => now()->subDay()])
        ->create(['title' => 'Beta Item']);

    Livewire::test(ContentItemSearch::class)
        ->set('sort', 'latest_transcription')
        ->assertSeeInOrder([$beta->title, $alpha->title]);

    Livewire::test(ContentItemSearch::class)
        ->set('sort', 'title_asc')
        ->assertSeeInOrder([$alpha->title, $beta->title]);

    Livewire::withQueryParams(['sort' => 'title_asc'])
        ->test(ContentItemSearch::class)
        ->assertSet('sort', 'title_asc')
        ->assertSeeInOrder([$alpha->title, $beta->title]);
});

it('paginates public content items', function (): void {
    $items = collect(range(1, 13))
        ->map(fn (int $index): ContentItem => ContentItem::factory()
            ->for(ContentGroup::factory()->published())
            ->published()
            ->withTranscription(['published_at' => now()->subMinutes($index)])
            ->create([
                'title' => "Paged Item {$index}",
                'slug' => "paged-item-{$index}",
            ]));

    Livewire::test(ContentItemSearch::class)
        ->assertSee($items[0]->title)
        ->assertDontSee($items[12]->title)
        ->call('setPage', 2)
        ->assertSee($items[12]->title);
});

it('renders a published group page with sanitized description and published items only', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Published Group',
        'slug' => 'published-group',
        'description_markdown' => "Safe **description**\n\n<script>alert('x')</script>",
    ]);
    $author = Author::factory()->create(['name' => 'Visible Author']);
    $publishedItem = ContentItem::factory()->for($group)->published()->withTranscription()->create([
        'title' => 'Published Item',
        'slug' => 'published-item',
    ]);
    $draftItem = ContentItem::factory()->for($group)->withTranscription()->create([
        'title' => 'Draft Item',
        'slug' => 'draft-item',
    ]);
    $publishedItem->authors()->attach($author);
    $draftItem->authors()->attach($author);

    $this->get("/groups/{$group->slug}")
        ->assertSuccessful()
        ->assertSee($group->title)
        ->assertSee('<strong>description</strong>', false)
        ->assertDontSee("alert('x')")
        ->assertSee($publishedItem->title)
        ->assertSee($author->name)
        ->assertDontSee($draftItem->title);
});

it('returns not found for draft and future group pages', function (): void {
    $draft = ContentGroup::factory()->create(['slug' => 'draft-group']);
    $future = ContentGroup::factory()->published(now()->addDay())->create(['slug' => 'future-group']);

    $this->get("/groups/{$draft->slug}")->assertNotFound();
    $this->get("/groups/{$future->slug}")->assertNotFound();
});

it('sorts items on a group page', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $alpha = ContentItem::factory()->for($group)->published(now()->subDays(2))->withTranscription()->create(['title' => 'Alpha Item']);
    $beta = ContentItem::factory()->for($group)->published(now()->subDay())->withTranscription()->create(['title' => 'Beta Item']);

    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('sort', 'newest')
        ->assertSeeInOrder([$beta->title, $alpha->title]);

    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('sort', 'title')
        ->assertSeeInOrder([$alpha->title, $beta->title]);
});

it('renders a published item page with authors sanitized markdown and approved embed', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Parent Group',
        'slug' => 'parent-group',
    ]);
    $authors = Author::factory()->count(2)->create();
    $item = ContentItem::factory()->for($group)->published()->withTranscription([
        'transcript_markdown' => "## Transcript\n\nשלום <script>alert('x')</script>",
    ])->create([
        'title' => 'Published Item',
        'slug' => 'published-item',
        'description_markdown' => 'Item **description** <img src=x onerror=alert(1)>',
        'media_url' => 'https://example.com/media/item',
        'embed_url' => 'https://www.youtube.com/embed/demo',
        'duration_seconds' => 125,
    ]);
    $item->authors()->attach($authors);

    $response = $this->get("/items/{$group->slug}/{$item->slug}");

    $response
        ->assertSuccessful()
        ->assertSee($group->title)
        ->assertSee($item->title)
        ->assertSee($authors[0]->name)
        ->assertSee($authors[1]->name)
        ->assertSee('<strong>description</strong>', false)
        ->assertSee('<h2>Transcript</h2>', false)
        ->assertSee('<iframe', false)
        ->assertSee('https://www.youtube.com/embed/demo')
        ->assertDontSee('onerror=', false)
        ->assertDontSee("alert('x')");
});

it('returns not found for draft future and draft-parent item pages', function (): void {
    $publishedGroup = ContentGroup::factory()->published()->create(['slug' => 'published-parent']);
    $draftGroup = ContentGroup::factory()->create(['slug' => 'draft-parent']);
    $draft = ContentItem::factory()->for($publishedGroup)->withTranscription()->create(['slug' => 'draft-item']);
    $future = ContentItem::factory()->for($publishedGroup)->published(now()->addDay())->withTranscription()->create(['slug' => 'future-item']);
    $underDraftGroup = ContentItem::factory()->for($draftGroup)->published()->withTranscription()->create(['slug' => 'under-draft-group']);

    $this->get("/items/{$publishedGroup->slug}/{$draft->slug}")->assertNotFound();
    $this->get("/items/{$publishedGroup->slug}/{$future->slug}")->assertNotFound();
    $this->get("/items/{$draftGroup->slug}/{$underDraftGroup->slug}")->assertNotFound();
});

it('falls back to the source media link when embed is missing or unapproved', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'media-group']);
    $withoutEmbed = ContentItem::factory()->for($group)->published()->withTranscription()->create([
        'slug' => 'without-embed',
        'media_url' => 'https://example.com/media/without-embed',
        'embed_url' => null,
    ]);
    $unapprovedEmbed = ContentItem::factory()->for($group)->published()->withTranscription()->create([
        'slug' => 'unapproved-embed',
        'media_url' => 'https://example.com/media/unapproved-embed',
        'embed_url' => 'https://unapproved.example/embed',
    ]);

    $this->get("/items/{$group->slug}/{$withoutEmbed->slug}")
        ->assertSuccessful()
        ->assertSee('https://example.com/media/without-embed')
        ->assertDontSee('<iframe', false);

    $this->get("/items/{$group->slug}/{$unapprovedEmbed->slug}")
        ->assertSuccessful()
        ->assertSee('https://example.com/media/unapproved-embed')
        ->assertDontSee('https://unapproved.example/embed')
        ->assertDontSee('<iframe', false);
});
