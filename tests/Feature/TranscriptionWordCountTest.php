<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\Transcriptions\TranscriptWordCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
});

it('counts transcript words through the shared counter', function (string $markdown, int $expected): void {
    expect(app(TranscriptWordCounter::class)->count($markdown))->toBe($expected);
})->with([
    'plain hebrew' => ['שלום לכולם ברוכים הבאים', 4],
    'markdown syntax stripped' => ["## כותרת\n\n- פריט ראשון\n- פריט שני", 5],
    'html tags stripped' => ['<p>שלום <strong>עולם</strong></p>', 2],
    'empty string' => ['', 0],
    'whitespace only' => ["   \n\t  ", 0],
]);

it('derives word_count when a transcription is created through the application', function (): void {
    $item = ContentItem::factory()->create();

    $transcription = Transcription::query()->create([
        'content_item_id' => $item->getKey(),
        'title' => 'נוצר מהאדמין',
        'transcript_markdown' => 'שלום לכולם ברוכים הבאים לפרק הראשון',
        'status' => PublicationStatus::Draft,
    ]);

    expect($transcription->refresh()->word_count)->toBe(6);
});

it('keeps an explicitly provided word_count on create', function (): void {
    $transcription = Transcription::factory()->create(['word_count' => 123]);

    expect($transcription->refresh()->word_count)->toBe(123);
});

it('recomputes word_count when the transcript body changes without an explicit override', function (): void {
    $transcription = Transcription::factory()->create(['word_count' => 999]);

    $transcription->update(['transcript_markdown' => 'מילה אחת שתיים שלוש']);

    expect($transcription->refresh()->word_count)->toBe(4);
});

it('leaves word_count untouched when unrelated fields change', function (): void {
    $transcription = Transcription::factory()->create(['word_count' => 999]);

    $transcription->update(['title' => 'כותרת חדשה']);

    expect($transcription->refresh()->word_count)->toBe(999);
});

it('feeds the public word aggregates from an application-created transcription', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $author = Author::factory()->create();

    $item = ContentItem::factory()
        ->for($group)
        ->published(now()->subHour())
        ->create();

    $transcription = Transcription::query()->create([
        'content_item_id' => $item->getKey(),
        'author_id' => $author->getKey(),
        'title' => 'תמלול ראשי',
        'transcript_markdown' => 'שלום לכולם ברוכים הבאים לפרק הראשון של הפודקאסט',
        'status' => PublicationStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    $summary = app(PublicTranscriptionAggregates::class)->contentGroupSummary($group);

    expect($transcription->refresh()->word_count)->toBe(8)
        ->and($summary['total_word_count'])->toBe(8);
});

it('backfills word counts for existing rows without touching explicit values or timestamps', function (): void {
    $withNull = Transcription::factory()->create([
        'transcript_markdown' => 'אחת שתיים שלוש',
        'word_count' => null,
    ]);
    $withValue = Transcription::factory()->create(['word_count' => 555]);
    $withEmptyBody = Transcription::factory()->create([
        'transcript_markdown' => '',
        'word_count' => null,
    ]);
    $originalUpdatedAt = $withNull->refresh()->updated_at?->toDateTimeString();

    $this->travel(10)->minutes();

    $this->artisan('transcriptions:backfill-word-counts')->assertSuccessful();

    expect($withNull->refresh()->word_count)->toBe(3)
        ->and($withNull->updated_at?->toDateTimeString())->toBe($originalUpdatedAt)
        ->and($withValue->refresh()->word_count)->toBe(555)
        ->and($withEmptyBody->refresh()->word_count)->toBe(0);
});
