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
        'description_markdown' => 'תיאור ציבורי קצר.',
    ]);

    visit('/')
        ->assertNoSmoke()
        ->assertScript('document.documentElement.dir', 'rtl')
        ->assertSee(__('public.pages.browse.title'))
        ->assertSee($group->title);
});

it('searches and sorts published groups in the browser', function (): void {
    $alpha = ContentGroup::factory()->published(now()->subDays(2))->create([
        'title' => 'Alpha Browser Podcast',
        'slug' => 'alpha-browser-podcast',
    ]);
    $beta = ContentGroup::factory()->published(now()->subDay())->create([
        'title' => 'Beta Browser Podcast',
        'slug' => 'beta-browser-podcast',
    ]);

    $page = visit('/');

    $page
        ->select('@group-sort', 'title')
        ->assertSelected('@group-sort', 'title')
        ->fill('@group-search', 'Alpha')
        ->assertValue('@group-search', 'Alpha')
        ->assertQueryStringHas('sort', 'title')
        ->assertQueryStringHas('q', 'Alpha')
        ->assertSee($alpha->title)
        ->assertDontSee($beta->title)
        ->assertNoJavaScriptErrors();
});

it('opens a published group and sorts its visible item rows in the browser', function (): void {
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

    $page = visit("/groups/{$group->slug}");

    $page
        ->assertNoSmoke()
        ->assertSee($group->title)
        ->assertSourceHas('<strong>group</strong>')
        ->assertSee($alpha->title)
        ->assertSee($beta->title)
        ->assertDontSee($draft->title)
        ->select('@item-sort', 'title')
        ->assertSelected('@item-sort', 'title')
        ->assertQueryStringHas('sort', 'title')
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
    $item->authors()->attach($authors);

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

    visit("/groups/{$draftGroup->slug}")
        ->assertPathIs("/groups/{$draftGroup->slug}")
        ->assertSee('404');

    visit("/groups/{$futureGroup->slug}")
        ->assertPathIs("/groups/{$futureGroup->slug}")
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
    ContentGroup::factory()->published()->create([
        'title' => 'כותרת עברית ארוכה במיוחד לבדיקת תצוגה במובייל עם ניקוד',
        'slug' => 'browser-mobile-hebrew-group',
    ]);

    visit('/')
        ->on()->mobile()
        ->assertNoSmoke()
        ->assertScript('document.documentElement.dir', 'rtl')
        ->assertSee('כותרת עברית ארוכה במיוחד לבדיקת תצוגה במובייל עם ניקוד');
});
