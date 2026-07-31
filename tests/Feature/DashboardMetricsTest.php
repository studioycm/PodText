<?php

use App\Enums\DashboardLens;
use App\Enums\DashboardRange;
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
        ->and($metrics['blockers']['missing_transcription'])->toBe(1)
        ->and($metrics['blockers']['missing_media'])->toBe(1)
        ->and($metrics['blockers']['missing_category'])->toBe(1)
        ->and($metrics['blockers']['total'])->toBe(1);
});

it('lists blocked published items in the queue with the visible item absent', function (): void {
    $fixture = dashboardFixture();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(BlockersQueueWidget::class)
        ->assertCanSeeTableRecords([$fixture['blocked']])
        ->assertCanNotSeeTableRecords([$fixture['visible'], $fixture['draft']]);
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
