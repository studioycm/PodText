<?php

use App\Enums\DashboardRange;
use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Imports\ContentItemImporter;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\MediaAttachment;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Dashboard\Data\BreakdownRow;
use App\Support\Dashboard\Data\Heatmap;
use App\Support\Dashboard\Data\SeriesRow;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
    // Fixed Jerusalem wall clock so day bucketing is deterministic.
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Jerusalem'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** A published episode the public can see and that carries no blocker. */
function unblockedItem(ContentGroup $group): ContentItem
{
    $item = ContentItem::factory()->for($group)->published(now()->subHour())->create([
        'embed_url' => 'https://open.spotify.com/episode/unblocked',
    ]);
    $item->categories()->attach(Category::factory()->create());
    Transcription::factory()->for($item)->published(now()->subHour())->create();

    return $item;
}

it('lists jerusalem day keys oldest first for the range', function (): void {
    $keys = DashboardRange::Last7Days->dayKeys();

    expect($keys)->toHaveCount(7)
        ->and($keys[0])->toBe('2026-07-25')
        ->and(end($keys))->toBe('2026-07-31');
});

it('buckets funnel movement into jerusalem days aligned to the day keys', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published(Carbon::parse('2026-07-29 12:00', 'Asia/Jerusalem'))->create();
    $item->forceFill(['created_at' => Carbon::parse('2026-07-28 12:00', 'Asia/Jerusalem')])->save();
    Transcription::factory()->for($item)->published(Carbon::parse('2026-07-30 12:00', 'Asia/Jerusalem'))->create();

    $series = app(EditorialMetrics::class)->funnelSeries(DashboardRange::Last7Days);
    $keys = DashboardRange::Last7Days->dayKeys();

    expect($series['draft'])->toBeInstanceOf(SeriesRow::class)
        ->and($series['draft']->points)->toHaveCount(7)
        ->and(array_combine($keys, $series['draft']->points)['2026-07-28'])->toBe(1.0)
        ->and(array_combine($keys, $series['published']->points)['2026-07-29'])->toBe(1.0)
        ->and(array_combine($keys, $series['transcribed']->points)['2026-07-30'])->toBe(1.0)
        // Published on the 29th but only transcribed on the 30th, so the public
        // could not see it until the 30th.
        ->and(array_combine($keys, $series['visible']->points)['2026-07-30'])->toBe(1.0)
        ->and(array_combine($keys, $series['visible']->points)['2026-07-29'])->toBe(0.0)
        // value is period movement, previousValue the period before it.
        ->and($series['published']->value)->toBe(1.0)
        ->and($series['published']->previous)->toBe(0.0)
        ->and($series['published']->delta())->toBe(1);
});

it('buckets the visible series on the day the episode became visible', function (): void {
    $group = ContentGroup::factory()->published()->create();

    // Published long before the range; the transcript went live inside it, so
    // this is the day the public could first see the episode.
    $item = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-20 09:00', 'Asia/Jerusalem'))
        ->create();
    Transcription::factory()->for($item)
        ->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))
        ->create();

    $series = app(EditorialMetrics::class)->funnelSeries(DashboardRange::Last7Days);
    $visible = array_combine(DashboardRange::Last7Days->dayKeys(), $series['visible']->points);

    expect($visible['2026-07-30'])->toBe(1.0)
        ->and(array_sum($series['visible']->points))->toBe(1.0)
        // The publication date is outside the range and must not be used.
        ->and(array_sum($series['published']->points))->toBe(0.0);
});

it('zero fills the publication heatmap across every day of the range', function (): void {
    $group = ContentGroup::factory()->published()->create();
    ContentItem::factory()->for($group)->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))->create();

    $heatmap = app(EditorialMetrics::class)->publicationHeatmap(DashboardRange::Last7Days);

    expect($heatmap)->toBeInstanceOf(Heatmap::class)
        ->and($heatmap->entries)->toHaveCount(7)
        ->and($heatmap->entries['2026-07-30'])->toBe(1)
        ->and($heatmap->entries['2026-07-29'])->toBe(0);
});

it('breaks podcast health into visible and blocked with a filtered doorway', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => 'Alpha Podcast']);

    $visible = ContentItem::factory()->for($group)->published(now()->subHour())->create();
    Transcription::factory()->for($visible)->published(now()->subHour())->create();
    ContentItem::factory()->for($group)->published(now()->subHour())->create();

    $health = app(EditorialMetrics::class)->podcastHealth();

    // `of` is the whole this row is part of, so percent() is honest and
    // `previous` stays free to mean an actual previous period.
    expect($health)->toHaveCount(1)
        ->and($health[0])->toBeInstanceOf(BreakdownRow::class)
        ->and($health[0]->label)->toBe('Alpha Podcast')
        ->and($health[0]->value)->toBe(1.0)
        ->and($health[0]->of)->toBe(2.0)
        ->and($health[0]->previous)->toBeNull()
        ->and($health[0]->percent())->toBe(50)
        ->and($health[0]->remainder())->toBe(1)
        ->and($health[0]->color)->toBe('danger')
        ->and($health[0]->url)->toContain('content-items');
});

it('ranks transcribers by published transcriptions with words and a previous period', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published(now()->subDay())->create();
    $author = Author::factory()->create(['name' => 'Dana']);

    Transcription::factory()->for($item)->forAuthor($author)
        ->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))
        ->create(['transcript_markdown' => 'one two three four five']);

    Transcription::factory()->for($item)->forAuthor($author)
        ->published(Carbon::parse('2026-07-10 09:00', 'Asia/Jerusalem'))
        ->create(['transcript_markdown' => 'older transcript body']);

    $board = app(EditorialMetrics::class)->transcriberBoard(DashboardRange::Last7Days);

    // Words ride in `meta`, so the row needs no wrapper array around it.
    expect($board)->toHaveCount(1)
        ->and($board[0])->toBeInstanceOf(BreakdownRow::class)
        ->and($board[0]->label)->toBe('Dana')
        ->and($board[0]->value)->toBe(1.0)
        ->and($board[0]->previous)->toBe(0.0)
        ->and($board[0]->delta())->toBe(1)
        ->and($board[0]->url)->toContain('transcriber_id')
        ->and($board[0]->meta('words'))->toBe(5);
});

it('types the activity stream and filters it by chip and by day', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published(now()->subDay())->create(['title' => 'Episode One']);
    Transcription::factory()->for($item)->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))->create();

    PublicFormSubmission::factory()->create([
        'created_at' => Carbon::parse('2026-07-29 09:00', 'Asia/Jerusalem'),
    ]);

    MediaAttachment::factory()->create([
        'attachable_type' => ContentItem::class,
        'attachable_id' => $item->getKey(),
        'created_at' => Carbon::parse('2026-07-30 11:00', 'Asia/Jerusalem'),
    ]);

    Import::query()->create([
        'completed_at' => Carbon::parse('2026-07-28 09:00', 'Asia/Jerusalem'),
        'created_at' => Carbon::parse('2026-07-28 09:00', 'Asia/Jerusalem'),
        'file_name' => 'episodes.csv',
        'file_path' => 'imports/episodes.csv',
        'importer' => ContentItemImporter::class,
        'processed_rows' => 3,
        'total_rows' => 3,
        'successful_rows' => 3,
        'user_id' => User::factory()->admin()->create()->getKey(),
    ]);

    $metrics = app(EditorialMetrics::class);

    $all = $metrics->activityStream(DashboardRange::Last7Days);
    $transcriptions = $metrics->activityStream(DashboardRange::Last7Days, type: 'transcription');
    $byDay = $metrics->activityStream(DashboardRange::Last7Days, day: '2026-07-29');

    expect($all)->toHaveCount(4)
        ->and(collect($all)->pluck('type')->unique()->sort()->values()->all())
        ->toBe(['import', 'media', 'submission', 'transcription'])
        ->and($all[0]['type'])->toBe('media')
        ->and($transcriptions)->toHaveCount(1)
        ->and($transcriptions[0]['title'])->toBe('Episode One')
        ->and($byDay)->toHaveCount(1)
        ->and($byDay[0]['type'])->toBe('submission');
});

it('separates invisible episodes from episodes that merely need attention', function (): void {
    $group = ContentGroup::factory()->published()->create();

    // Visible and complete.
    unblockedItem($group);

    // Invisible: published and otherwise complete, but no published transcript.
    $noTranscript = ContentItem::factory()->for($group)->published(now()->subHour())->create([
        'embed_url' => 'https://open.spotify.com/episode/no-transcript',
    ]);
    $noTranscript->categories()->attach(Category::factory()->create());

    // Invisible for the reason nothing tracked before: the podcast is a draft.
    $draftGroup = ContentGroup::factory()->create(['status' => PublicationStatus::Draft]);
    $underDraft = ContentItem::factory()->for($draftGroup)->published(now()->subHour())->create([
        'embed_url' => 'https://open.spotify.com/episode/draft-group',
    ]);
    $underDraft->categories()->attach(Category::factory()->create());
    Transcription::factory()->for($underDraft)->published(now()->subHour())->create();

    // Publicly visible, but missing a category: needs attention, not invisible.
    $incomplete = ContentItem::factory()->for($group)->published(now()->subHour())->create([
        'embed_url' => 'https://open.spotify.com/episode/no-category',
    ]);
    Transcription::factory()->for($incomplete)->published(now()->subHour())->create();

    $snapshot = app(EditorialMetrics::class)->snapshot();

    expect($snapshot['gap']['invisible'])->toBe(2)
        ->and($snapshot['gap']['missing_transcription'])->toBe(1)
        ->and($snapshot['gap']['unpublished_group'])->toBe(1)
        ->and($snapshot['attention']['missing_category'])->toBe(1)
        ->and($snapshot['attention']['missing_media'])->toBe(0)
        ->and($snapshot['attention']['total'])->toBe(1)
        // The needs-attention episode is visible: the two tiers do not overlap
        // in meaning, and the funnel gap counts only the invisible ones.
        ->and($snapshot['funnel']['visible'])->toBe(2)
        ->and($snapshot['funnel']['published'] - $snapshot['funnel']['visible'])
        ->toBe($snapshot['gap']['invisible']);
});

it('names the podcast-not-published reason on a queue row', function (): void {
    $draftGroup = ContentGroup::factory()->create(['status' => PublicationStatus::Draft]);
    $item = ContentItem::factory()->for($draftGroup)->published(now()->subHour())->create([
        'embed_url' => 'https://open.spotify.com/episode/draft-group',
    ]);
    $item->categories()->attach(Category::factory()->create());
    Transcription::factory()->for($item)->published(now()->subHour())->create();

    $metrics = app(EditorialMetrics::class);

    expect($metrics->blockerReasonsFor($item->fresh()))->toBe(['unpublished_group'])
        ->and($metrics->queueQuery()->pluck('id')->all())->toBe([$item->getKey()]);
});

it('reports a burn-down per tier and forecasts only the transcribable one', function (): void {
    $group = ContentGroup::factory()->published()->create();

    unblockedItem($group);
    ContentItem::factory()->for($group)->published(now()->subHour())->create();

    $progress = app(EditorialMetrics::class)->blockersProgress();

    expect($progress['invisible']->remaining)->toBe(1)
        ->and($progress['invisible']->total)->toBe(2)
        ->and($progress['invisible']->cleared())->toBe(1)
        ->and($progress['invisible']->percent())->toBe(50)
        ->and($progress['attention']->remaining)->toBe(1)
        ->and($progress['attention']->total)->toBe(2)
        // Transcripts carry a published_at to pace from; the category pivot has
        // no timestamps, so only the invisible tier can forecast honestly.
        ->and($progress['attention']->forecast)->toBeNull();
});

it('scopes every item-derived number to the selected podcast', function (): void {
    $alpha = ContentGroup::factory()->published()->create();
    $beta = ContentGroup::factory()->published()->create();

    unblockedItem($alpha);
    ContentItem::factory()->for($beta)->published(now()->subHour())->create();
    ContentItem::factory()->for($beta)->create(['status' => PublicationStatus::Draft]);

    $metrics = app(EditorialMetrics::class);
    $alphaSnapshot = $metrics->snapshot($alpha->getKey());
    $betaSnapshot = $metrics->snapshot($beta->getKey());

    expect($alphaSnapshot['funnel']['visible'])->toBe(1)
        ->and($alphaSnapshot['funnel']['draft'])->toBe(0)
        ->and($alphaSnapshot['gap']['invisible'])->toBe(0)
        ->and($betaSnapshot['funnel']['visible'])->toBe(0)
        ->and($betaSnapshot['funnel']['draft'])->toBe(1)
        ->and($betaSnapshot['gap']['invisible'])->toBe(1)
        ->and($metrics->snapshot()['funnel']['visible'])->toBe(1);
});

it('offers podcast filter options keyed by id', function (): void {
    $group = ContentGroup::factory()->create(['title' => 'Zeta Show']);

    expect(app(EditorialMetrics::class)->podcastOptions())
        ->toBe([$group->getKey() => 'Zeta Show']);
});
