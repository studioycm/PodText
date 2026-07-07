<?php

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('browses the public root in a real browser without smoke errors', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'שיחות עומק עם ניקוד',
        'slug' => 'browser-root-group',
        'description_markdown' => 'תיאור קצר.',
    ]);
    $item = ContentItem::factory()->for($group)->published()->withTranscription()->create([
        'title' => 'פרק בדפדפן',
        'slug' => 'browser-root-item',
    ]);

    visit('/')
        ->assertNoSmoke()
        ->assertScript('document.documentElement.dir', 'rtl')
        ->assertSourceHas('data-test="public-header"')
        ->assertSourceHas('data-test="homepage-section"')
        ->assertSourceHas('data-test="homepage-section-heading"')
        ->assertSee($item->title)
        ->assertSourceHas('data-test="content-item-card"')
        ->assertSourceMissing('data-test="group-search"');
});

it('searches and sorts published content items in the browser', function (): void {
    $alpha = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published(now()->subDays(2))
        ->withTranscription()
        ->create([
            'title' => 'Alpha Browser Episode',
            'slug' => 'alpha-browser-episode',
        ]);
    $beta = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published(now()->subDay())
        ->withTranscription()
        ->create([
            'title' => 'Beta Browser Episode',
            'slug' => 'beta-browser-episode',
        ]);

    visit('/search?q=Alpha&sort=title_asc')
        ->assertQueryStringHas('q', 'Alpha')
        ->assertQueryStringHas('sort', 'title_asc')
        ->assertSee($alpha->title)
        ->assertDontSee($beta->title)
        ->assertSourceHas(__('public.filters.search_items_placeholder'))
        ->assertNoJavaScriptErrors();
});

it('opens a published group and sorts its visible items in the browser', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Browser Group',
        'slug' => 'browser-group',
        'description_markdown' => 'Visible **group** description.',
    ]);
    $alpha = ContentItem::factory()->for($group)->published(now()->subDays(2))->withTranscription()->create([
        'title' => 'Alpha Browser Item',
        'slug' => 'alpha-browser-item',
    ]);
    $beta = ContentItem::factory()->for($group)->published(now()->subDay())->withTranscription()->create([
        'title' => 'Beta Browser Item',
        'slug' => 'beta-browser-item',
    ]);
    $draft = ContentItem::factory()->for($group)->withTranscription()->create([
        'title' => 'Draft Browser Item',
        'slug' => 'draft-browser-item',
    ]);

    $page = visit("/podcasts/{$group->slug}");

    $page
        ->assertNoSmoke()
        ->assertSee($group->title)
        ->assertSourceHas('<strong>group</strong>')
        ->assertSee($alpha->title)
        ->assertSee($beta->title)
        ->assertDontSee($draft->title)
        ->select('@item-sort', 'title_asc')
        ->assertSelected('@item-sort', 'title_asc')
        ->assertQueryStringHas('sort', 'title_asc')
        ->assertNoJavaScriptErrors();
});

it('opens a published item with authors approved embed and Hebrew transcript in the browser', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Browser Parent Group',
        'slug' => 'browser-parent-group',
    ]);
    $authors = Author::factory()->count(2)->create([
        'name' => 'מחבר בדיקה',
    ]);
    $item = ContentItem::factory()->for($group)->published()->withTranscription([
        'transcript_markdown' => "## תמלול\n\nשָׁלוֹם עולם",
    ])->create([
        'title' => 'Browser Published Item',
        'slug' => 'browser-published-item',
        'description_markdown' => 'Item **description**.',
        'media_url' => 'https://example.com/media/browser-item',
        'embed_url' => 'https://www.youtube.com/embed/browser-demo',
        'duration_seconds' => 125,
    ]);
    $item->effectiveTranscription()?->syncTranscribers($authors);

    visit("/items/{$group->slug}/{$item->slug}")
        ->assertNoSmoke()
        ->assertSee($group->title)
        ->assertSee($item->title)
        ->assertSee($authors[0]->name)
        ->assertSourceHas('<strong>description</strong>')
        ->assertSourceHas('<h2>תמלול</h2>')
        ->assertSee('שָׁלוֹם עולם')
        ->assertSourceHas('https://www.youtube.com/embed/browser-demo')
        ->assertSourceHas('<iframe')
        ->assertNoJavaScriptErrors();
});

it('keeps malicious markdown and unapproved embed markup out of browser source', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'slug' => 'browser-security-group',
    ]);
    $item = ContentItem::factory()->for($group)->published()->withTranscription([
        'transcript_markdown' => "Transcript <script>alert('x')</script>",
    ])->create([
        'title' => 'Browser Security Item',
        'slug' => 'browser-security-item',
        'description_markdown' => 'Safe text <img src=x onerror=alert(1)>',
        'media_url' => 'https://example.com/media/browser-security-item',
        'embed_url' => 'https://unapproved.example/embed',
    ]);

    visit("/items/{$group->slug}/{$item->slug}")
        ->assertNoSmoke()
        ->assertSee($item->title)
        ->assertSourceHas('https://example.com/media/browser-security-item')
        ->assertSourceMissing('onerror=')
        ->assertSourceMissing("alert('x')")
        ->assertSourceMissing('https://unapproved.example/embed')
        ->assertSourceMissing('<iframe')
        ->assertNoJavaScriptErrors();
});

it('blocks direct browser URLs for draft and future public records', function (): void {
    $publishedGroup = ContentGroup::factory()->published()->create(['slug' => 'browser-published-parent']);
    $draftGroup = ContentGroup::factory()->create(['slug' => 'browser-draft-group']);
    $futureGroup = ContentGroup::factory()->published(now()->addDay())->create(['slug' => 'browser-future-group']);
    $draftItem = ContentItem::factory()->for($publishedGroup)->withTranscription()->create(['slug' => 'browser-draft-item']);
    $futureItem = ContentItem::factory()->for($publishedGroup)->published(now()->addDay())->withTranscription()->create(['slug' => 'browser-future-item']);
    $underDraftGroup = ContentItem::factory()->for($draftGroup)->published()->withTranscription()->create(['slug' => 'browser-under-draft']);

    visit("/podcasts/{$draftGroup->slug}")
        ->assertPathIs("/podcasts/{$draftGroup->slug}")
        ->assertSee('404');

    visit("/podcasts/{$futureGroup->slug}")
        ->assertPathIs("/podcasts/{$futureGroup->slug}")
        ->assertSee('404');

    visit("/items/{$publishedGroup->slug}/{$draftItem->slug}")
        ->assertPathIs("/items/{$publishedGroup->slug}/{$draftItem->slug}")
        ->assertSee('404');

    visit("/items/{$publishedGroup->slug}/{$futureItem->slug}")
        ->assertPathIs("/items/{$publishedGroup->slug}/{$futureItem->slug}")
        ->assertSee('404');

    visit("/items/{$draftGroup->slug}/{$underDraftGroup->slug}")
        ->assertPathIs("/items/{$draftGroup->slug}/{$underDraftGroup->slug}")
        ->assertSee('404');
});

it('renders the public browse page on mobile without smoke errors', function (): void {
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription()
        ->create([
            'title' => 'כותרת עברית ארוכה במיוחד לבדיקת תצוגה במובייל עם ניקוד',
            'slug' => 'browser-mobile-hebrew-item',
        ]);

    visit('/')
        ->on()->mobile()
        ->assertNoSmoke()
        ->assertScript('document.documentElement.dir', 'rtl')
        ->assertSee('כותרת עברית ארוכה במיוחד לבדיקת תצוגה במובייל עם ניקוד');
});
