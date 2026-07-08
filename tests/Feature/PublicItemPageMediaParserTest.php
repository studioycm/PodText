<?php

use App\Enums\PublicationStatus;
use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\BrowseTagContentItems;
use App\Filament\Public\Pages\ShowContributor;
use App\Livewire\Public\ContentItemTranscriptViewer;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Transcription;
use App\Support\Transcripts\TranscriptSegmentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createPrompt12PublicItem(
    array $itemAttributes = [],
    array $transcriptionAttributes = [],
    ?ContentGroup $group = null,
    ?Author $transcriber = null,
): array {
    $group ??= ContentGroup::factory()->published()->create([
        'title' => 'Prompt Twelve Podcast',
        'slug' => 'prompt-twelve-podcast',
    ]);

    $item = ContentItem::factory()
        ->for($group)
        ->published($itemAttributes['published_at'] ?? now()->subMinute())
        ->create([
            'title' => 'Prompt Twelve Episode',
            'slug' => 'prompt-twelve-episode',
            ...$itemAttributes,
        ]);

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($transcriber)
        ->published($transcriptionAttributes['published_at'] ?? now()->subMinute())
        ->create([
            'title' => 'Main transcript',
            'transcript_markdown' => '[00:01:23] Host: Opening segment',
            ...$transcriptionAttributes,
        ]);

    $item->update(['featured_transcription_id' => $transcription->id]);

    return [$item->refresh(), $transcription->refresh(), $group->refresh()];
}

it('parses timestamp and speaker transcript segments', function (): void {
    $segments = app(TranscriptSegmentParser::class)->parse(<<<'MARKDOWN'
[00:01:23] Speaker One: Transcript text

[00:02:34] Speaker Two:
Transcript text on the next line.
Still the same segment.
MARKDOWN);

    expect($segments)->toHaveCount(2)
        ->and($segments[0])->toMatchArray([
            'seconds' => 83,
            'timestamp' => '00:01:23',
            'speaker' => 'Speaker One',
            'markdown' => 'Transcript text',
            'anchor' => 't-83',
        ])
        ->and($segments[1])->toMatchArray([
            'seconds' => 154,
            'timestamp' => '00:02:34',
            'speaker' => 'Speaker Two',
            'markdown' => "Transcript text on the next line.\nStill the same segment.",
            'anchor' => 't-154',
        ]);
});

it('renders a public item page with safe media metadata contributor links and transcript metrics', function (): void {
    $creditedAuthor = Author::factory()->create([
        'name' => 'Credited Author',
        'slug' => 'credited-author',
    ]);
    $transcriber = Author::factory()->create([
        'name' => 'Transcript Contributor',
        'slug' => 'transcript-contributor',
    ]);
    $category = Category::factory()->create([
        'name' => 'Prompt Category',
        'slug' => 'prompt-category',
    ]);
    $tag = ContentTag::findOrCreate('Prompt Tag', 'content')->enable();

    [$item, $transcription, $group] = createPrompt12PublicItem([
        'description_markdown' => 'Episode **description**',
        'media_url' => 'https://example.com/media/source',
        'embed_url' => 'https://www.youtube.com/embed/prompt12',
        'embed_provider' => 'youtube',
        'duration_seconds' => 3723,
        'media_duration_seconds' => 3723,
        'external_title' => 'External media title',
        'external_description' => 'External media description',
    ], [
        'title' => 'Primary transcript',
        'transcript_markdown' => '[00:01:23] Host: שלום עולם',
        'word_count' => 420,
    ], transcriber: $transcriber);

    $item->categories()->attach($category);
    $item->attachTag($tag);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee($item->title)
        ->assertSee('<strong>description</strong>', false)
        ->assertSee('<iframe', false)
        ->assertSee('src="https://www.youtube.com/embed/prompt12"', false)
        ->assertSee('data-test="media-provider"', false)
        ->assertSee('youtube')
        ->assertSee('External media title')
        ->assertDontSee(ShowContributor::getUrl(['authorSlug' => $creditedAuthor->slug], panel: 'public'), false)
        ->assertSee(ShowContributor::getUrl(['authorSlug' => $transcriber->slug], panel: 'public'), false)
        ->assertSee(BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public'), false)
        ->assertSee(BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public'), false)
        ->assertSee('data-test="reading-time"', false)
        ->assertSee('data-test="transcript-length"', false)
        ->assertSee('data-test="item-duration"', false)
        ->assertSee($transcription->published_at->timezone('Asia/Jerusalem')->format('d/m/Y'));
});

it('renders approved embeds and falls back for unapproved http or raw embed values', function (string $embedUrl): void {
    [$item, , $group] = createPrompt12PublicItem([
        'media_url' => 'https://example.com/media/fallback-source',
        'embed_url' => $embedUrl,
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('https://example.com/media/fallback-source')
        ->assertSee('data-test="media-source-link"', false)
        ->assertDontSee('<iframe', false)
        ->assertDontSee($embedUrl, false);
})->with([
    'unapproved host' => ['https://unapproved.example/embed'],
    'http url' => ['http://www.youtube.com/embed/prompt12'],
    'raw iframe' => ['<iframe src="https://www.youtube.com/embed/prompt12"></iframe>'],
]);

it('returns not found for draft items and items without effective published transcriptions', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'prompt12-visibility']);
    $draft = ContentItem::factory()->for($group)->withTranscription()->create(['slug' => 'draft-item']);
    $withoutTranscription = ContentItem::factory()->for($group)->published()->create(['slug' => 'without-transcription']);
    $draftTranscription = ContentItem::factory()->for($group)->published()->withTranscription([
        'status' => PublicationStatus::Draft,
        'published_at' => null,
    ])->create(['slug' => 'draft-transcription']);

    $this->get("/items/{$group->slug}/{$draft->slug}")->assertNotFound();
    $this->get("/items/{$group->slug}/{$withoutTranscription->slug}")->assertNotFound();
    $this->get("/items/{$group->slug}/{$draftTranscription->slug}")->assertNotFound();
});

it('defaults to the effective transcription and hides alternate transcription tabs until enabled', function (): void {
    [$item, $featured, $group] = createPrompt12PublicItem([
        'slug' => 'tabbed-item',
    ], [
        'title' => 'Featured transcript',
        'transcript_markdown' => '[00:01:23] Host: Featured body',
        'published_at' => now()->subDays(3),
    ]);
    $alternate = Transcription::factory()
        ->for($item)
        ->published(now()->subDay())
        ->create([
            'title' => 'Alternate transcript',
            'transcript_markdown' => '[00:02:00] Guest: Alternate body',
        ]);
    Transcription::factory()
        ->for($item)
        ->create([
            'title' => 'Draft transcript',
            'transcript_markdown' => '[00:03:00] Hidden: Draft body',
        ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertDontSee('data-test="transcript-tabs"', false)
        ->assertDontSee('Alternate transcript')
        ->assertSee('data-selected-transcription="'.$featured->reference_key.'"', false)
        ->assertSee('Featured body')
        ->assertDontSee('Draft transcript')
        ->assertDontSee('Draft body')
        ->assertDontSee('Alternate body');

    Livewire::withQueryParams(['transcription' => $alternate->reference_key])
        ->test(ContentItemTranscriptViewer::class, ['contentItem' => $item->refresh()])
        ->assertSet('selectedTranscription', $featured->reference_key)
        ->assertSee('Featured body')
        ->assertDontSee('Alternate body')
        ->assertDontSee('Draft transcript');
});

it('renders timestamp anchors and local-only viewer controls without player sync hooks', function (): void {
    [$item, , $group] = createPrompt12PublicItem(transcriptionAttributes: [
        'transcript_markdown' => '[00:01:23] Host: Segment body',
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="transcript-viewer"', false)
        ->assertSee('data-test="toggle-timestamps"', false)
        ->assertSee('data-test="toggle-speakers"', false)
        ->assertSee('showTimestamps', false)
        ->assertSee('showSpeakers', false)
        ->assertSee('localStorage', false)
        ->assertSee('id="t-83"', false)
        ->assertSee('href="#t-83"', false)
        ->assertSee('dir="ltr"', false)
        ->assertDontSee('timeupdate', false)
        ->assertDontSee('currentTime', false)
        ->assertDontSee('wire:poll', false);
});

it('falls back to sanitized markdown when no parseable transcript segments exist', function (): void {
    [$item, , $group] = createPrompt12PublicItem(transcriptionAttributes: [
        'transcript_markdown' => "## Plain Transcript\n\nשלום <script>alert('x')</script>",
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('<h2>Plain Transcript</h2>', false)
        ->assertDontSee('data-test="transcript-segment"', false)
        ->assertDontSee("alert('x')");
});

it('renders parsed segment markdown with transcript formatting and no long transcript truncation', function (): void {
    $longTail = str_repeat("שורת מקטע ארוכה עם תוכן עברי\n", 900).'FINAL_PARSED_SEGMENT_TOKEN';

    [$item, , $group] = createPrompt12PublicItem(transcriptionAttributes: [
        'transcript_markdown' => <<<MARKDOWN
[00:01:23] Host: Segment with **bold**, *italic*, and ***bold italic***.
single soft break

{$longTail}
MARKDOWN,
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="transcript-segment"', false)
        ->assertSee('data-test="transcript-segment-content"', false)
        ->assertSee('<strong>bold</strong>', false)
        ->assertSee('<em>italic</em>', false)
        ->assertSee('<br', false)
        ->assertSee('FINAL_PARSED_SEGMENT_TOKEN')
        ->assertSee('id="t-83"', false)
        ->assertSee('href="#t-83"', false)
        ->assertSee('dir="ltr"', false);
});

it('renders fallback transcript markdown with paragraphs soft breaks styling and no long transcript truncation', function (): void {
    $longTail = str_repeat("שורת תמלול מלאה עם תוכן עברי\n", 900).'FINAL_FALLBACK_TRANSCRIPT_TOKEN';

    [$item, , $group] = createPrompt12PublicItem(transcriptionAttributes: [
        'transcript_markdown' => <<<MARKDOWN
## Plain Transcript

First paragraph with **bold**, *italic*, and ***bold italic***.
single soft break

Second paragraph.

{$longTail}
MARKDOWN,
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="transcript-fallback-content"', false)
        ->assertDontSee('data-test="transcript-segment"', false)
        ->assertSee('<h2>Plain Transcript</h2>', false)
        ->assertSee('<strong>bold</strong>', false)
        ->assertSee('<em>italic</em>', false)
        ->assertSee('<br', false)
        ->assertSee('<p>Second paragraph.</p>', false)
        ->assertSee('FINAL_FALLBACK_TRANSCRIPT_TOKEN')
        ->assertSee('data-test="toggle-timestamps"', false)
        ->assertSee('data-test="toggle-speakers"', false);
});
