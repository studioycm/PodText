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

    expect($series['draft'])->toHaveCount(7)
        ->and(array_combine($keys, $series['draft'])['2026-07-28'])->toBe(1)
        ->and(array_combine($keys, $series['published'])['2026-07-29'])->toBe(1)
        ->and(array_combine($keys, $series['transcribed'])['2026-07-30'])->toBe(1)
        ->and(array_combine($keys, $series['visible'])['2026-07-29'])->toBe(1)
        ->and(array_sum($series['published']))->toBe(1);
});

it('zero fills the publication heatmap across every day of the range', function (): void {
    $group = ContentGroup::factory()->published()->create();
    ContentItem::factory()->for($group)->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))->create();

    $heatmap = app(EditorialMetrics::class)->publicationHeatmap(DashboardRange::Last7Days);

    expect($heatmap)->toHaveCount(7)
        ->and($heatmap['2026-07-30'])->toBe(1)
        ->and($heatmap['2026-07-29'])->toBe(0);
});

it('breaks podcast health into visible and blocked with a filtered doorway', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => 'Alpha Podcast']);

    $visible = ContentItem::factory()->for($group)->published(now()->subHour())->create();
    Transcription::factory()->for($visible)->published(now()->subHour())->create();
    ContentItem::factory()->for($group)->published(now()->subHour())->create();

    $health = app(EditorialMetrics::class)->podcastHealth();

    expect($health)->toHaveCount(1)
        ->and($health[0]['label'])->toBe('Alpha Podcast')
        ->and($health[0]['total'])->toBe(2)
        ->and($health[0]['visible'])->toBe(1)
        ->and($health[0]['blocked'])->toBe(1)
        ->and($health[0]['percent'])->toBe(50)
        ->and($health[0]['url'])->toContain('content-items');
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

    expect($board)->toHaveCount(1)
        ->and($board[0]['label'])->toBe('Dana')
        ->and($board[0]['transcriptions'])->toBe(1)
        ->and($board[0]['words'])->toBe(5)
        ->and($board[0]['previous'])->toBe(0)
        ->and($board[0]['url'])->toContain('transcriber_id');
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

it('reports blocker burn-down as remaining out of status-published', function (): void {
    $group = ContentGroup::factory()->published()->create();

    unblockedItem($group);
    ContentItem::factory()->for($group)->published(now()->subHour())->create();

    $progress = app(EditorialMetrics::class)->blockersProgress();

    expect($progress['remaining'])->toBe(1)
        ->and($progress['total'])->toBe(2);
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
        ->and($alphaSnapshot['blockers']['total'])->toBe(0)
        ->and($betaSnapshot['funnel']['visible'])->toBe(0)
        ->and($betaSnapshot['funnel']['draft'])->toBe(1)
        ->and($betaSnapshot['blockers']['total'])->toBe(1)
        ->and($metrics->snapshot()['funnel']['visible'])->toBe(1);
});

it('offers podcast filter options keyed by id', function (): void {
    $group = ContentGroup::factory()->create(['title' => 'Zeta Show']);

    expect(app(EditorialMetrics::class)->podcastOptions())
        ->toBe([$group->getKey() => 'Zeta Show']);
});
