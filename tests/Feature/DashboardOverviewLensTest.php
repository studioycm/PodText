<?php

use App\Enums\DashboardLens;
use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ActivityStreamWidget;
use App\Filament\Widgets\BlockersQueueWidget;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\LibraryCompositionWidget;
use App\Filament\Widgets\PublicationFunnelWidget;
use App\Filament\Widgets\PublicationHeatmapWidget;
use App\Models\Author;
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

/** @return array{group: ContentGroup, visible: ContentItem, blocked: ContentItem} */
function overviewFixture(): array
{
    $group = ContentGroup::factory()->published()->create(['title' => 'Alpha Podcast']);
    $category = Category::factory()->create();

    $visible = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))
        ->create(['title' => 'Visible Episode', 'embed_url' => 'https://open.spotify.com/episode/ov1']);
    $visible->categories()->attach($category);
    Transcription::factory()->for($visible)
        ->forAuthor(Author::factory()->create(['name' => 'Dana']))
        ->published(Carbon::parse('2026-07-30 10:00', 'Asia/Jerusalem'))
        ->create(['transcript_markdown' => 'one two three four five']);

    $blocked = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-29 09:00', 'Asia/Jerusalem'))
        ->create(['title' => 'Blocked Episode', 'embed_url' => null, 'media_url' => '']);

    ContentItem::factory()->for($group)->create(['status' => PublicationStatus::Draft]);

    return ['group' => $group, 'visible' => $visible, 'blocked' => $blocked];
}

it('renders the overview board in board 1 order', function (): void {
    expect(Dashboard::getWidgetsForLens(DashboardLens::Overview))->toBe([
        DashboardContextWidget::class,
        PublicationFunnelWidget::class,
        EditorialStatsWidget::class,
        PublicationHeatmapWidget::class,
        ActivityStreamWidget::class,
        LibraryCompositionWidget::class,
    ]);
});

it('writes legend chip selections into page filters and toggles them off', function (): void {
    Livewire::test(Dashboard::class)
        ->dispatch('dashboard-filter', key: 'status', value: 'draft')
        ->assertSet('filters.status', 'draft')
        ->dispatch('dashboard-filter', key: 'status', value: 'draft')
        ->assertSet('filters.status', null)
        ->dispatch('dashboard-filter', key: 'lens', value: DashboardLens::Blockers->value)
        ->assertSet('filters.lens', DashboardLens::Blockers->value);
});

it('dispatches a filter write when a legend chip is clicked', function (): void {
    overviewFixture();

    Livewire::test(DashboardContextWidget::class)
        ->call('selectStatus', 'draft')
        ->assertDispatched('dashboard-filter');
});

it('scopes the stats spine to the selected podcast', function (): void {
    $fixture = overviewFixture();
    $other = ContentGroup::factory()->published()->create();
    ContentItem::factory()->for($other)->create(['status' => PublicationStatus::Draft]);

    Livewire::test(EditorialStatsWidget::class, ['pageFilters' => []])
        ->assertSee('2');

    Livewire::test(EditorialStatsWidget::class, ['pageFilters' => ['podcast' => $fixture['group']->getKey()]])
        ->assertSeeHtml('filters%5Bcontent_group_id%5D%5Bvalue%5D='.$fixture['group']->getKey())
        ->assertDontSeeHtml('wire:poll');
});

it('renders the living funnel with per-stage sparklines and a gap doorway', function (): void {
    overviewFixture();

    Livewire::test(PublicationFunnelWidget::class)
        ->assertSeeHtml('data-testid="funnel-segment-visible"')
        ->assertSeeHtml('data-testid="funnel-spark-transcribed"')
        ->assertSeeHtml('data-testid="funnel-gap"')
        ->assertDontSeeHtml('wire:poll')
        ->call('openBlockers')
        ->assertDispatched('dashboard-filter');
});

it('shows composite stat cards with a composition strip and a filtered doorway', function (): void {
    overviewFixture();

    Livewire::test(EditorialStatsWidget::class)
        ->assertSeeHtml('data-testid="stat-composition-visible"')
        ->assertSeeHtml('filters%5Bstatus%5D%5Bvalue%5D=published');
});

it('filters the activity stream to a heatmap day and to a chip', function (): void {
    overviewFixture();

    Livewire::test(PublicationHeatmapWidget::class)
        ->assertSeeHtml('data-testid="heatmap-day-2026-07-30"')
        ->call('selectDay', '2026-07-30')
        ->assertDispatched('dashboard-day-selected');

    Livewire::test(ActivityStreamWidget::class)
        ->assertSee('Visible Episode')
        ->dispatch('dashboard-day-selected', day: '2026-07-29')
        ->assertSet('day', '2026-07-29')
        ->assertDontSee('Visible Episode')
        ->call('selectType', 'submission')
        ->assertSet('type', 'submission');
});

it('renders the library composition band with podcast health and the transcriber board', function (): void {
    overviewFixture();

    Livewire::test(LibraryCompositionWidget::class)
        ->assertSee('Alpha Podcast')
        ->assertSee('Dana')
        ->assertSeeHtml('data-testid="podcast-health-row"')
        ->assertSeeHtml('data-testid="transcriber-row"')
        ->assertDontSeeHtml('wire:poll');
});

it('tags every overview widget as stock or flow', function (): void {
    overviewFixture();

    Livewire::test(PublicationFunnelWidget::class)->assertSeeHtml('data-testid="widget-tag-stock"');
    Livewire::test(EditorialStatsWidget::class)->assertSeeHtml('data-testid="widget-tag-stock"');
    Livewire::test(LibraryCompositionWidget::class)->assertSeeHtml('data-testid="widget-tag-stock"');
    Livewire::test(PublicationHeatmapWidget::class)->assertSeeHtml('data-testid="widget-tag-flow"');
    Livewire::test(ActivityStreamWidget::class)->assertSeeHtml('data-testid="widget-tag-flow"');
});

it('validates unvalidated page filter data before querying with it', function (): void {
    $fixture = overviewFixture();

    // pageFilters is live, URL-bound and unvalidated by Filament's own docs:
    // an unknown podcast falls back to the whole library, and a status that is
    // not a funnel stage is ignored rather than echoed into a translation key.
    Livewire::test(EditorialStatsWidget::class, ['pageFilters' => ['podcast' => 999999]])
        ->assertSeeHtml('filters%5Bstatus%5D%5Bvalue%5D=published')
        ->assertDontSeeHtml('filters%5Bcontent_group_id%5D');

    Livewire::test(DashboardContextWidget::class, ['pageFilters' => ['podcast' => 999999]])
        ->assertSee(__('admin.dashboard.filters.all_podcasts'));

    Livewire::test(DashboardContextWidget::class, ['pageFilters' => ['status' => '../../evil']])
        ->assertDontSee('evil');

    // A valid podcast still scopes.
    Livewire::test(DashboardContextWidget::class, ['pageFilters' => ['podcast' => $fixture['group']->getKey()]])
        ->assertSee('Alpha Podcast');
});

it('ignores filter keys the command bar does not own', function (): void {
    Livewire::test(Dashboard::class)
        ->dispatch('dashboard-filter', key: 'evil', value: 'x')
        ->assertSet('filters.evil', null)
        ->dispatch('dashboard-filter', key: 'podcast', value: '3')
        ->assertSet('filters.podcast', '3');
});

it('lets a legend chip scope the flow widgets', function (): void {
    overviewFixture();

    // A stage chip narrows the stream to the event kind that stage is made of.
    Livewire::test(ActivityStreamWidget::class, ['pageFilters' => ['status' => 'transcribed']])
        ->assertSet('type', null)
        ->assertSee(__('admin.dashboard.stream.types.transcription'));

    expect(EditorialMetrics::streamTypeForStatus('transcribed'))->toBe('transcription')
        ->and(EditorialMetrics::streamTypeForStatus('visible'))->toBe('transcription')
        ->and(EditorialMetrics::streamTypeForStatus('draft'))->toBeNull()
        ->and(EditorialMetrics::streamTypeForStatus(null))->toBeNull();
});

it('refuses to render editorial widgets for a non-admin', function (): void {
    expect(EditorialStatsWidget::canView())->toBeTrue();

    $this->actingAs(User::factory()->create(['role' => UserRole::User]));

    expect(EditorialStatsWidget::canView())->toBeFalse()
        ->and(ActivityStreamWidget::canView())->toBeFalse()
        ->and(BlockersQueueWidget::canView())->toBeFalse()
        ->and(LibraryCompositionWidget::canView())->toBeFalse();
});

it('forgets the metrics snapshot when editorial content is written', function (): void {
    $fixture = overviewFixture();
    $metrics = app(EditorialMetrics::class);

    expect($metrics->snapshot()['funnel']['draft'])->toBe(1);

    // Without invalidation this would still read 1 for up to a minute.
    ContentItem::factory()->for($fixture['group'])->create(['status' => PublicationStatus::Draft]);

    expect($metrics->snapshot()['funnel']['draft'])->toBe(2);
});

it('lays the command bar out as two rows with explicit distinct keys', function (): void {
    $rows = Livewire::test(Dashboard::class)->instance()->getFiltersForm()->getComponents(withHidden: true);

    $rowKeys = collect($rows)->map(fn ($row): ?string => $row->getKey(isAbsolute: false))->all();
    $fieldKeys = collect($rows)
        ->flatMap(fn ($row): array => $row->getChildSchema()->getComponents(withHidden: true))
        ->map(fn ($field): ?string => $field->getKey(isAbsolute: false))
        ->all();

    // Row 1 is lens navigation, row 2 the contextual filters (gap-filler G1).
    expect($rowKeys)->toBe(['dashboardLensRow', 'dashboardScopeRow'])
        ->and($fieldKeys)->toBe(['dashboardLens', 'dashboardRange', 'dashboardPodcast'])
        ->and(array_unique([...$rowKeys, ...$fieldKeys]))->toHaveCount(5);
});

it('keeps the queue table query string distinct from the dashboard filters', function (): void {
    overviewFixture();

    $widget = Livewire::test(BlockersQueueWidget::class)->instance();

    // The Dashboard page binds `#[Url] public ?array $filters`; an unidentified
    // table widget would claim the same query-string keys on the same page.
    expect($widget->getTable()->getQueryStringIdentifier())->toBe('blockersQueue')
        ->and($widget->getIdentifiedTableQueryStringPropertyNameFor('filters'))->toBe('blockersQueueFilters')
        ->and($widget->getIdentifiedTableQueryStringPropertyNameFor('search'))->not->toBe('search');
});

it('puts the burn-down finish line in the blockers queue header', function (): void {
    overviewFixture();

    Livewire::test(BlockersQueueWidget::class)
        ->assertSeeHtml('data-testid="queue-burndown"')
        ->assertSee('1');
});
