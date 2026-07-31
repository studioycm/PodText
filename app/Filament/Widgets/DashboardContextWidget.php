<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Models\ContentGroup;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class DashboardContextWidget extends Widget
{
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

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
            'chipClasses' => [
                'draft' => 'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300',
                'published' => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
                'transcribed' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
                'visible' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
            ],
            'generatedAt' => Carbon::parse($metrics['generated_at'])
                ->timezone('Asia/Jerusalem')
                ->format('H:i'),
        ];
    }
}
