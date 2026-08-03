<?php

use App\Enums\DashboardLens;
use App\Enums\DashboardRange;
use App\Enums\DashboardReason;
use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BlockersQueueWidget;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\PublicationGapWidget;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
});

/** @return array{visible: ContentItem, blocked: ContentItem, draft: ContentItem} */
function dashboardFixture(): array
{
    $group = ContentGroup::factory()->published()->create();
    $category = Category::factory()->create();

    $visible = ContentItem::factory()->for($group)->published(now()->subHour())->create([
        'embed_url' => 'https://open.spotify.com/episode/visible1',
    ]);
    $visible->categories()->attach($category);
    Transcription::factory()->for($visible)->published(now()->subHour())->create();

    $blocked = ContentItem::factory()->for($group)->published(now()->subHour())->create([
        'embed_url' => null,
        'media_url' => '',
    ]);

    $draft = ContentItem::factory()->for($group)->create([
        'status' => PublicationStatus::Draft,
    ]);

    return ['visible' => $visible, 'blocked' => $blocked, 'draft' => $draft];
}

it('denies guests and renders the dashboard for admins', function (): void {
    $this->get('/admin')->assertRedirect();

    $this->actingAs(User::factory()->admin()->create());
    $this->get('/admin')->assertSuccessful();
});

it('switches widget sets by lens and defaults to overview', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $overview = Dashboard::getWidgetsForLens(DashboardLens::Overview);
    $blockers = Dashboard::getWidgetsForLens(DashboardLens::Blockers);

    expect($overview)->toContain(DashboardContextWidget::class, EditorialStatsWidget::class)
        ->and($overview)->not->toContain(BlockersQueueWidget::class)
        ->and($blockers)->toContain(DashboardContextWidget::class, PublicationGapWidget::class, BlockersQueueWidget::class)
        ->and(DashboardLens::fromFilter(null))->toBe(DashboardLens::Overview)
        ->and(DashboardLens::fromFilter('blockers'))->toBe(DashboardLens::Blockers);
});

it('computes funnel and blocker counts from one source of truth', function (): void {
    dashboardFixture();

    $metrics = app(EditorialMetrics::class)->snapshot();

    expect($metrics['funnel']['draft'])->toBe(1)
        ->and($metrics['funnel']['published'])->toBe(2)
        ->and($metrics['funnel']['transcribed'])->toBe(1)
        ->and($metrics['funnel']['visible'])->toBe(1)
        ->and($metrics['gap']['invisible'])->toBe(1)
        ->and($metrics['gap']['missing_transcription'])->toBe(1)
        ->and($metrics['gap']['unpublished_group'])->toBe(0)
        ->and($metrics['attention']['missing_media'])->toBe(1)
        ->and($metrics['attention']['missing_category'])->toBe(1)
        ->and($metrics['attention']['total'])->toBe(1);
});

it('lists blocked published items in the queue with the visible item absent', function (): void {
    $fixture = dashboardFixture();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(BlockersQueueWidget::class)
        ->assertCanSeeTableRecords([$fixture['blocked']])
        ->assertCanNotSeeTableRecords([$fixture['visible'], $fixture['draft']]);
});

it('renders a blockers queue page within a fixed query budget', function (): void {
    $group = ContentGroup::factory()->published()->create();

    // 25 queue rows shaped like the ones that made the reasons column spend
    // four queries per record: published under a published group, no
    // transcription, no media, no category anywhere.
    ContentItem::factory()
        ->count(25)
        ->for($group)
        ->published(now()->subHour())
        ->create(['embed_url' => null, 'media_url' => '']);

    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(BlockersQueueWidget::class);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $component->set('tableRecordsPerPage', 25);

    // One table update at 25 rows must stay flat: the page fetch carries the
    // blocker facts itself, so the reasons column adds nothing per record.
    // Measured 3 queries against the fix; the per-row shape spent 105. Any
    // per-record query returning here adds 25+ and fails loudly.
    expect($queries)->toBeLessThanOrEqual(6);
});

it('computes identical blocker reasons from primed queue rows and bare records', function (): void {
    $publishedGroup = ContentGroup::factory()->published()->create();

    $bare = ContentItem::factory()->for($publishedGroup)->published(now()->subHour())
        ->create(['embed_url' => null, 'media_url' => '']);

    $withOwnCategory = ContentItem::factory()->for($publishedGroup)->published(now()->subHour())
        ->create(['embed_url' => null, 'media_url' => '']);
    $withOwnCategory->categories()->attach(Category::factory()->create());

    $draftGroup = ContentGroup::factory()->create();
    $underDraftGroup = ContentItem::factory()->for($draftGroup)->published(now()->subHour())
        ->create(['embed_url' => 'https://open.spotify.com/episode/complete']);
    $underDraftGroup->categories()->attach(Category::factory()->create());
    Transcription::factory()->for($underDraftGroup)->published(now()->subHour())->create();

    $groupWithCategory = ContentGroup::factory()->published()->create();
    $groupWithCategory->categories()->attach(Category::factory()->create());
    $inheritsCategory = ContentItem::factory()->for($groupWithCategory)->published(now()->subHour())
        ->create(['embed_url' => null, 'media_url' => '']);

    $expected = [
        $bare->getKey() => ['missing_transcription', 'missing_media', 'missing_category'],
        $withOwnCategory->getKey() => ['missing_transcription', 'missing_media'],
        $underDraftGroup->getKey() => ['unpublished_group'],
        $inheritsCategory->getKey() => ['missing_transcription', 'missing_media'],
    ];

    $metrics = app(EditorialMetrics::class);
    $rows = $metrics->queueQuery()->get()->keyBy(fn (ContentItem $row): int => $row->getKey());

    expect($rows->keys()->sort()->values()->all())->toBe(array_keys($expected));

    // A queue row answers from its own fetched facts; a bare record answers
    // from bounded per-record queries. Both sourcing paths must agree, in the
    // DashboardReason contract order.
    foreach ($expected as $id => $reasons) {
        $row = $rows->get($id);

        expect($metrics->blockerReasonsFor($row))->toBe($reasons)
            ->and($metrics->blockerReasonsFor($row->fresh()))->toBe($reasons);
    }

    // The badge and the reason filter read one truth: a row wears a reason
    // badge exactly when applyReason() would keep it.
    foreach (array_keys($expected) as $id) {
        foreach (DashboardReason::cases() as $reason) {
            $matchesFilter = $metrics->applyReason($metrics->queueQuery(), $reason->value)
                ->whereKey($id)
                ->exists();

            expect(in_array($reason->value, $metrics->blockerReasonsFor($rows->get($id)), true))->toBe(
                $matchesFilter,
                "Row {$id} disagrees with the {$reason->value} filter.",
            );
        }
    }
});

it('never polls and links stats to resource urls', function (): void {
    dashboardFixture();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditorialStatsWidget::class)
        ->assertDontSeeHtml('wire:poll')
        ->assertSeeHtml('/admin/content-items');
    Livewire::test(DashboardContextWidget::class)->assertDontSeeHtml('wire:poll');
    Livewire::test(PublicationGapWidget::class)->assertDontSeeHtml('wire:poll');
});

it('computes jerusalem-aware range periods', function (): void {
    $range = DashboardRange::fromFilter('last_7_days');
    [$start, $end] = $range->currentPeriod();

    expect($range->days())->toBe(7)
        ->and($start->timezone->getName())->toBe('UTC')
        ->and($start->copy()->setTimezone('Asia/Jerusalem')->format('H:i:s'))->toBe('00:00:00')
        ->and(DashboardRange::fromFilter(null))->toBe(DashboardRange::Last30Days)
        ->and(DashboardRange::fromFilter('bogus'))->toBe(DashboardRange::Last30Days);
});
