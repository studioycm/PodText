<?php

use App\Enums\DashboardRange;
use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\PublicationFunnelWidget;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
