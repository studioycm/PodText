<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Livewire\Public\ContentItemSearch;
use App\Livewire\Public\ContentItemTranscriptViewer;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\Transcripts\TranscriptSegmentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
});

function derivedStateMarkdown(): string
{
    return "[00:00:01] מנחה: שלום לכולם\n\n[00:00:12] אורחת: תודה שהזמנתם אותי";
}

it('persists parsed segments when a transcription is saved through the application', function (): void {
    $item = ContentItem::factory()->create();

    $transcription = Transcription::query()->create([
        'content_item_id' => $item->getKey(),
        'title' => 'תמלול עם מקטעים',
        'transcript_markdown' => derivedStateMarkdown(),
        'status' => PublicationStatus::Draft,
    ]);

    // toEqual, not toBe: parsed_segments is a mysql JSON column, which
    // re-serializes each segment's own key order on write, independent of
    // app behaviour (spec §7 SQL strict mode). Segment list order stays
    // enforced either way.
    expect($transcription->refresh()->parsed_segments)
        ->toEqual(app(TranscriptSegmentParser::class)->parse(derivedStateMarkdown()))
        ->and($transcription->parsed_segments)->not->toBe([]);
});

it('re-derives segments when the body changes and keeps explicit values', function (): void {
    $explicit = [['speaker' => 'ידני', 'text' => 'נשמר']];
    $transcription = Transcription::factory()->create(['parsed_segments' => $explicit]);

    expect($transcription->refresh()->parsed_segments)->toEqual($explicit);

    $transcription->update(['transcript_markdown' => derivedStateMarkdown()]);

    expect($transcription->refresh()->parsed_segments)
        ->toEqual(app(TranscriptSegmentParser::class)->parse(derivedStateMarkdown()));
});

it('renders the public viewer from persisted segments without re-parsing', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $author = Author::factory()->create();
    $item = ContentItem::factory()->for($group)->published(now()->subHour())->create();
    $transcription = Transcription::query()->create([
        'content_item_id' => $item->getKey(),
        'author_id' => $author->getKey(),
        'title' => 'תמלול ראשי',
        'transcript_markdown' => derivedStateMarkdown(),
        'status' => PublicationStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    // Tamper the persisted segments beneath Eloquent: if the viewer re-parsed
    // the Markdown per view, the tampered text could never appear.
    DB::table('transcriptions')->where('id', $transcription->getKey())->update([
        'parsed_segments' => json_encode([
            [
                'seconds' => 1,
                'timestamp' => '00:00:01',
                'speaker' => 'מנחה',
                'markdown' => 'טקסט מהעמודה השמורה',
                'anchor' => 't-1',
            ],
        ], JSON_UNESCAPED_UNICODE),
    ]);

    Livewire::test(ContentItemTranscriptViewer::class, ['contentItem' => $item->refresh()])
        ->assertSee('טקסט מהעמודה השמורה')
        ->assertDontSee('תודה שהזמנתם אותי');
});

it('backfills parsed segments for existing rows without touching explicit ones', function (): void {
    $withNull = Transcription::factory()->create([
        'transcript_markdown' => derivedStateMarkdown(),
        'parsed_segments' => null,
    ]);
    $explicit = [['speaker' => 'ידני', 'text' => 'נשמר']];
    $withValue = Transcription::factory()->create(['parsed_segments' => $explicit]);

    $this->artisan('transcriptions:backfill-parsed-segments')->assertSuccessful();

    // toEqual: see the mysql JSON key-reordering reasoning above.
    expect($withNull->refresh()->parsed_segments)
        ->toEqual(app(TranscriptSegmentParser::class)->parse(derivedStateMarkdown()))
        ->and($withValue->refresh()->parsed_segments)->toEqual($explicit);
});

it('serves the public filter options from a bounded cache across search renders', function (): void {
    ContentGroup::factory()->published()->create();

    Livewire::test(ContentItemSearch::class);

    DB::enableQueryLog();
    Livewire::test(ContentItemSearch::class);
    $log = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $optionQueries = $log->filter(
        fn (string $sql): bool => (str_contains($sql, 'content_groups') && str_contains($sql, 'order by "title"'))
            || str_contains($sql, 'embed_provider')
            || str_contains($sql, 'categories'),
    );

    expect($optionQueries->values()->all())->toBe([]);
});
