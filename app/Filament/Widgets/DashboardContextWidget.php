<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Enums\FunnelStage;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Models\ContentGroup;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class DashboardContextWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.dashboard-context';

    protected static ?int $sort = -50;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** H6 · the legend is the filter: a chip writes the board's status scope. */
    public function selectStatus(string $status): void
    {
        $this->dispatch('dashboard-filter', key: 'status', value: $status);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $podcastId = $this->dashboardPodcastId();
        $metrics = app(EditorialMetrics::class)->snapshot($podcastId);
        $lens = DashboardLens::fromFilter($this->pageFilters['lens'] ?? null);

        return [
            'funnel' => $metrics['funnel'],
            'lens' => $lens,
            'range' => $lens === DashboardLens::Blockers ? null : $this->dashboardRange(),
            'status' => $this->dashboardStatus(),
            'podcast' => $podcastId === null
                ? null
                : ContentGroup::query()->whereKey($podcastId)->value('title'),
            'stages' => FunnelStage::cases(),
            'generatedAt' => Carbon::parse($metrics['generated_at'])
                ->timezone('Asia/Jerusalem')
                ->format('H:i'),
        ];
    }
}
