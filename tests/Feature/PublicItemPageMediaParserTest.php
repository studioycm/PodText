<?php

use App\Enums\PublicationStatus;
use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\BrowseTagContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContributor;
use App\Livewire\Public\ContentItemTranscriptViewer;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Transcription;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Transcripts\TranscriptSegmentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

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

function saveStep10RIp2PublicFrontSettings(array $settings): void
{
    foreach ($settings as $key => $value) {
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

function putPrompt12PodcastPaletteCover(string $path): void
{
    $image = imagecreatetruecolor(30, 30);

    imagefilledrectangle($image, 0, 0, 9, 29, imagecolorallocate($image, 37, 99, 235));
    imagefilledrectangle($image, 10, 0, 19, 29, imagecolorallocate($image, 22, 163, 74));
    imagefilledrectangle($image, 20, 0, 29, 29, imagecolorallocate($image, 220, 38, 38));

    ob_start();
    imagepng($image);
    $contents = ob_get_clean();

    imagedestroy($image);

    Storage::disk('public')->put($path, $contents);
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

it('renders the configured item page header image podcast identity and info line', function (): void {
    $transcriber = Author::factory()->create([
        'name' => 'Header Transcriber',
        'slug' => 'header-transcriber',
    ]);
    $category = Category::factory()->create([
        'name' => 'Header Category',
        'slug' => 'header-category',
    ]);
    $tag = ContentTag::findOrCreate('Header Tag', 'content')->enable();
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Header Podcast',
        'slug' => 'header-podcast',
        'default_item_type_label_singular' => 'Hidden Type Marker',
    ]);
    [$item, $transcription] = createPrompt12PublicItem([
        'title' => 'Configured Header Title',
        'slug' => 'configured-header-title',
        'description_markdown' => 'Header **description**',
        'duration_seconds' => 3661,
        'external_thumbnail_url' => 'https://cdn.example.test/item-header.jpg',
        'original_published_at' => now()->subDays(30),
        'published_at' => now()->subDays(3),
    ], [
        'published_at' => now()->subDays(2),
        'word_count' => 420,
    ], group: $group, transcriber: $transcriber);

    $item->categories()->attach($category);
    $item->attachTag($tag);

    $defaults = PublicFrontConfigRegistry::defaults()['item_page'];
    saveStep10RIp2PublicFrontSettings([
        'item_page' => [
            ...$defaults,
            'show_breadcrumbs' => false,
            'podcast_identity' => [
                'mode' => 'text',
                'color' => 'success',
                'icon' => 'podcast',
                'icon_position' => 'inline_after',
            ],
            'dates' => [
                ...$defaults['dates'],
                'display' => 'both',
                'site_published' => [
                    'label_mode' => 'long',
                    'label_override' => 'On this site',
                    'icon' => 'calendar',
                    'icon_position' => 'inline_before',
                ],
                'original_published' => [
                    'label_mode' => 'long',
                    'label_override' => 'Original source',
                    'icon' => 'calendar',
                    'icon_position' => 'inline_after',
                ],
                'transcription_date' => [
                    'enabled' => true,
                    'label_mode' => 'short',
                    'label_override' => 'Transcript date',
                    'icon' => 'document',
                    'icon_position' => 'inline_before',
                ],
            ],
            'info_fields' => [
                [
                    'field' => 'categories',
                    'label_mode' => 'long',
                    'label_override' => null,
                    'icon' => 'folder',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'info',
                ],
                [
                    'field' => 'tags',
                    'label_mode' => 'long',
                    'label_override' => null,
                    'icon' => 'tag',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'success',
                ],
                [
                    'field' => 'site_published_date',
                    'label_mode' => 'short',
                    'label_override' => null,
                    'icon' => 'calendar',
                    'icon_position' => 'inline_after',
                    'size' => 'md',
                    'color' => 'primary',
                ],
                [
                    'field' => 'original_published_date',
                    'label_mode' => 'short',
                    'label_override' => null,
                    'icon' => 'calendar',
                    'icon_position' => 'inline_after',
                    'size' => 'md',
                    'color' => 'warning',
                ],
                [
                    'field' => 'transcription_date',
                    'label_mode' => 'short',
                    'label_override' => null,
                    'icon' => 'document',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'gray',
                ],
                [
                    'field' => 'duration',
                    'label_mode' => 'hidden',
                    'label_override' => null,
                    'icon' => 'clock',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'gray',
                ],
                [
                    'field' => 'transcribers',
                    'label_mode' => 'long',
                    'label_override' => null,
                    'icon' => 'users',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'gray',
                ],
                [
                    'field' => 'reading_time',
                    'label_mode' => 'short',
                    'label_override' => null,
                    'icon' => 'clock',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'gray',
                ],
                [
                    'field' => 'word_count',
                    'label_mode' => 'short',
                    'label_override' => null,
                    'icon' => 'document',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'gray',
                ],
            ],
        ],
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertDontSee('data-test="item-breadcrumbs"', false)
        ->assertSee('data-test="item-page-image"', false)
        ->assertSee('data-item-page-image-source="item"', false)
        ->assertSee('https://cdn.example.test/item-header.jpg', false)
        ->assertSee('data-test="item-page-title"', false)
        ->assertSee('Configured Header Title')
        ->assertSee('data-test="item-podcast-identity"', false)
        ->assertSee('data-podcast-identity-mode="text"', false)
        ->assertSee(ShowContentGroup::getUrl(['contentGroupSlug' => $group->slug], panel: 'public'), false)
        ->assertSee(BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public'), false)
        ->assertSee(BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public'), false)
        ->assertSee(ShowContributor::getUrl(['authorSlug' => $transcriber->slug], panel: 'public'), false)
        ->assertSee('data-test="item-info-categories"', false)
        ->assertSee('data-test="item-info-tags"', false)
        ->assertSee('data-card-part-icon="calendar"', false)
        ->assertSee('data-card-part-icon="document"', false)
        ->assertSee('data-card-part-icon-position="inline_after"', false)
        ->assertSeeInOrder([
            'Header Category',
            'Header Tag',
            'On this site',
            $item->published_at->timezone('Asia/Jerusalem')->format('d/m/Y'),
            'Original source',
            $item->original_published_at->timezone('Asia/Jerusalem')->format('d/m/Y'),
            'Transcript date',
            $transcription->published_at->timezone('Asia/Jerusalem')->format('d/m/Y'),
        ])
        ->assertSee('data-test="item-duration"', false)
        ->assertSee(__('public.labels.duration_value', ['duration' => '01:01:01']))
        ->assertSee('data-test="reading-time"', false)
        ->assertSee('data-test="transcript-length"', false)
        ->assertSee('Header Transcriber')
        ->assertDontSee('Hidden Type Marker');
});

it('renders podcast identity with title row positioning and sampled podcast image color', function (): void {
    Storage::fake('public');

    $coverPath = 'content-groups/covers/palette-cover.png';
    putPrompt12PodcastPaletteCover($coverPath);

    $group = ContentGroup::factory()->published()->create([
        'title' => 'Palette Podcast',
        'slug' => 'palette-podcast',
        'cover_path' => $coverPath,
    ]);
    [$item] = createPrompt12PublicItem([
        'title' => 'Palette Episode',
        'slug' => 'palette-episode',
        'external_thumbnail_url' => null,
    ], group: $group);

    $defaults = PublicFrontConfigRegistry::defaults()['item_page'];
    saveStep10RIp2PublicFrontSettings([
        'item_page' => [
            ...$defaults,
            'show_breadcrumbs' => false,
            'podcast_identity' => [
                'mode' => 'title',
                'color' => 'image_2',
                'icon' => 'podcast',
                'icon_position' => 'inline_before',
                'position' => 'title_row_after',
                'size' => 'title',
            ],
        ],
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="item-title-row"', false)
        ->assertSee('data-podcast-identity-mode="title"', false)
        ->assertSee('data-podcast-identity-position="title_row_after"', false)
        ->assertSee('data-podcast-identity-size="title"', false)
        ->assertSee('data-podcast-identity-color="image_2"', false)
        ->assertSee('--podcast-identity-color: #16a34a', false)
        ->assertSeeInOrder([
            'Palette Episode',
            'Palette Podcast',
        ]);
});

it('uses the podcast cover as the item page image fallback', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'slug' => 'cover-fallback-podcast',
        'cover_path' => 'content-groups/covers/fallback-cover.jpg',
    ]);
    [$item] = createPrompt12PublicItem([
        'slug' => 'cover-fallback-item',
        'external_thumbnail_url' => null,
    ], group: $group);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="item-page-image"', false)
        ->assertSee('data-item-page-image-source="group"', false)
        ->assertSee('content-groups/covers/fallback-cover.jpg', false);
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
