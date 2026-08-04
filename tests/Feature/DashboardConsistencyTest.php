<?php

use App\Enums\DashboardRange;
use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Widgets\ActivityStreamWidget;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\PublicationFunnelWidget;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Jerusalem'));
    $this->actingAs(User::factory()->admin()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Every tier the decision-10 pairs reconcile, with hand-computable numbers.
 * All wall times are inside the last-30-days range and stored as UTC walls
 * (the repo's cast semantics).
 *
 * Expected arithmetic (verify against the tier contracts before touching):
 *   published = 4 (visible, noTranscript, orphan, needy)
 *   visible   = 2 (visible, needy — needy is public but incomplete)
 *   invisible = 2 (noTranscript → missing_transcription;
 *                  orphan → unpublished_group; NO overlap by construction)
 *   attention = 1 (needy — missing_media AND missing_category)
 *   draft     = 1
 *   queue     = 3 distinct episodes (noTranscript, orphan, needy)
 *
 * @return array{alpha: ContentGroup, draftGroup: ContentGroup}
 */
function consistencyFixture(): array
{
    $alpha = ContentGroup::factory()->published()->create(['title' => 'Alpha Podcast']);
    $draftGroup = ContentGroup::factory()->create(['title' => 'Shelved Podcast']);
    $category = Category::factory()->create();

    $visible = ContentItem::factory()->for($alpha)
        ->published(Carbon::parse('2026-07-29 09:00'))
        ->create(['title' => 'Fully Visible', 'embed_url' => 'https://open.spotify.com/episode/c1']);
    $visible->categories()->attach($category);
    Transcription::factory()->for($visible)
        ->published(Carbon::parse('2026-07-29 10:00'))
        ->create();

    $noTranscript = ContentItem::factory()->for($alpha)
        ->published(Carbon::parse('2026-07-28 09:00'))
        ->create(['title' => 'Awaiting Transcript', 'embed_url' => 'https://open.spotify.com/episode/c2']);
    $noTranscript->categories()->attach($category);

    $orphan = ContentItem::factory()->for($draftGroup)
        ->published(Carbon::parse('2026-07-27 09:00'))
        ->create(['title' => 'Orphaned Episode', 'embed_url' => 'https://open.spotify.com/episode/c3']);
    $orphan->categories()->attach($category);
    Transcription::factory()->for($orphan)
        ->published(Carbon::parse('2026-07-27 10:00'))
        ->create();

    $needy = ContentItem::factory()->for($alpha)
        ->published(Carbon::parse('2026-07-26 09:00'))
        ->create(['title' => 'Needy Episode', 'embed_url' => null, 'media_url' => '']);
    Transcription::factory()->for($needy)
        ->published(Carbon::parse('2026-07-26 10:00'))
        ->create();

    ContentItem::factory()->for($alpha)->create(['status' => PublicationStatus::Draft]);

    return ['alpha' => $alpha, 'draftGroup' => $draftGroup];
}

it('shows one visible number across the funnel, the stat card and the legend chip', function (): void {
    consistencyFixture();

    $funnel = app(EditorialMetrics::class)->snapshot()['funnel'];

    // The independent oracle: the public visibility contract itself.
    expect($funnel['visible'])->toBe(2)
        ->and($funnel['visible'])->toBe(ContentItem::query()->published()->count())
        ->and($funnel['published'])->toBe(4)
        ->and($funnel['draft'])->toBe(1);

    // Surface checks at the view-data level (assertSee on a bare digit is
    // satisfiable by unrelated page content — vacuous-assertSee). Livewire
    // v4 has no component-level assertViewHas — that name falls through to
    // the HTTP wrapper's view, whose data lacks widget keys — so the lever
    // is Testable::viewData(), which reads the component's own render.
    expect(Livewire::test(DashboardContextWidget::class)->viewData('funnel')['visible'])->toBe(2)
        // The funnel widget surfaces per-stage entries, each carrying the
        // snapshot count it renders.
        ->and(Livewire::test(PublicationFunnelWidget::class)->viewData('stages')['visible']['count'])->toBe(2);

    // Verified shape: getViewData()['cards'] rows carry 'key' and 'value',
    // and one card's key is exactly 'visible'.
    expect(collect(Livewire::test(EditorialStatsWidget::class)->viewData('cards'))
        ->contains(fn (array $card): bool => ($card['key'] ?? null) === 'visible'
            && ($card['value'] ?? null) === 2))->toBeTrue();
});

it('reconciles the heatmap total with the funnel published series', function (): void {
    consistencyFixture();

    $metrics = app(EditorialMetrics::class);
    $range = DashboardRange::Last30Days;

    $publishedSeries = $metrics->funnelSeries($range)['published'];
    $heatmap = $metrics->publicationHeatmap($range);

    // Two independently implemented aggregations of the same events.
    // SeriesRow::$points is array<int, float> (verified) — cast the sum or
    // the strict toBe() fails on 4.0 vs 4.
    expect($heatmap->total())->toBe((int) array_sum($publishedSeries->points))
        ->and($heatmap->total())->toBe(4);
});

it('reconciles blocked across the gap rows, the queue and the burn-down', function (): void {
    consistencyFixture();

    $metrics = app(EditorialMetrics::class);
    $snapshot = $metrics->snapshot();

    // The gap rows carry exactly the invisible tier, reason by reason —
    // the fixture is built overlap-free, so the per-row pins are exact.
    $gapRows = collect($metrics->reasonBreakdown()['gap'])
        ->mapWithKeys(fn ($row): array => [$row->meta('reason') => (int) $row->value]);

    expect($snapshot['gap']['invisible'])->toBe(2)
        ->and($gapRows->get('missing_transcription'))->toBe(1)
        ->and($gapRows->get('unpublished_group'))->toBe(1);

    // Attention reasons OVERLAP by design (one episode, two findings):
    // total counts episodes, the rows count findings — total ≤ sum (D-2).
    expect($snapshot['attention']['total'])->toBe(1)
        ->and($snapshot['attention']['missing_media'])->toBe(1)
        ->and($snapshot['attention']['missing_category'])->toBe(1);

    // The queue holds BOTH tiers as distinct episodes (verified in
    // queueQuery(): the union of all four blocker reasons over
    // status-published — the phase-1 handoff correction made real).
    expect($metrics->queueQuery()->count())->toBe(3);

    // The burn-down bars read the same tier numbers.
    $progress = $metrics->blockersProgress();
    expect($progress['invisible']->remaining)->toBe($snapshot['gap']['invisible'])
        ->and($progress['attention']->remaining)->toBe($snapshot['attention']['total'])
        ->and($progress['invisible']->total)->toBe($snapshot['funnel']['published']);
});

it('reconciles per-podcast health totals against the scoped funnel', function (): void {
    $fixture = consistencyFixture();

    $metrics = app(EditorialMetrics::class);
    $rows = collect($metrics->podcastHealth())
        ->keyBy(fn ($row): string => $row->label);

    foreach ([
        'Alpha Podcast' => $fixture['alpha'],
        'Shelved Podcast' => $fixture['draftGroup'],
    ] as $label => $group) {
        $scoped = $metrics->snapshot($group->getKey())['funnel'];

        expect((int) $rows->get($label)->value)->toBe($scoped['visible'])
            ->and((int) $rows->get($label)->of)->toBe($scoped['published']);
    }

    // Anchor the absolute numbers once so the loop cannot pass on 0 == 0.
    expect((int) $rows->get('Alpha Podcast')->of)->toBe(3)
        ->and((int) $rows->get('Alpha Podcast')->value)->toBe(2)
        ->and((int) $rows->get('Shelved Podcast')->of)->toBe(1)
        ->and((int) $rows->get('Shelved Podcast')->value)->toBe(0);
});

it('reconciles the intake queue chips with the intake snapshot', function (): void {
    Storage::fake('public');
    PublicFormSubmission::factory()->count(2)->create();
    failedImport(failed: 2);

    $metrics = app(EditorialMetrics::class);
    $snapshot = $metrics->intakeSnapshot()['queue'];
    $counts = $metrics->intakeQueue()['counts'];

    expect($counts)->toBe([
        'all' => $snapshot['submissions'] + $snapshot['imports'],
        'submissions' => $snapshot['submissions'],
        'imports' => $snapshot['imports'],
    ])
        ->and($snapshot)->toBe(['submissions' => 2, 'imports' => 1, 'failed_rows' => 2]);
});

it('keeps the media findings relations honest', function (): void {
    Storage::fake('public');
    cleanMedia();
    missingFileMedia();
    missingFileMedia();

    $metrics = app(EditorialMetrics::class);
    $media = $metrics->intakeSnapshot()['media'];
    $findings = $metrics->mediaFindings();

    // flagged counts DISTINCT media; per-reason counts can overlap on a
    // multi-finding file — the honest relations, not a forced equality (D-2).
    expect($media['flagged'])->toBeLessThanOrEqual(array_sum($media['findings']))
        ->and($media['flagged'])->toBeLessThanOrEqual($media['total'])
        ->and($findings['rate']->covered)->toBe($media['total'] - $media['flagged'])
        ->and($findings['rate']->of)->toBe($media['total'])
        ->and($media['total'])->toBe(3);
});

it('keeps stock totals still while a flow widget follows the range and the legend', function (): void {
    consistencyFixture();

    $metrics = app(EditorialMetrics::class);

    // Stock: the funnel's totals are range-blind by contract. The widget
    // surfaces stages[stage][count] (Testable::viewData — Livewire v4 has
    // no component-level assertViewHas).
    $stockAtThirty = $metrics->snapshot()['funnel'];

    $stageCounts = fn (array $stages): array => collect($stages)
        ->map(fn (array $stage): int => $stage['count'])
        ->all();

    expect($stageCounts(Livewire::test(PublicationFunnelWidget::class, ['pageFilters' => ['range' => DashboardRange::Last7Days->value]])->viewData('stages')))
        ->toBe($stockAtThirty);

    // Flow: the heatmap's total moves with the range and stays reconciled
    // with the series under the SAME narrowed window.
    expect($metrics->publicationHeatmap(DashboardRange::Last7Days)->total())
        ->toBe((int) array_sum($metrics->funnelSeries(DashboardRange::Last7Days)['published']->points));

    // Legend-as-filter: a status chip narrows the STREAM (flow) and leaves
    // the funnel (stock) untouched — synthesis rule 1 across two widgets.
    expect(Livewire::test(ActivityStreamWidget::class, ['pageFilters' => ['status' => 'visible']])->viewData('activeType'))
        ->toBe('transcription');

    expect($stageCounts(Livewire::test(PublicationFunnelWidget::class, ['pageFilters' => ['status' => 'visible']])->viewData('stages')))
        ->toBe($stockAtThirty);
});
