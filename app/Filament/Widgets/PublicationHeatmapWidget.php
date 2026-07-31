<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * H4 · the navigating heatmap. Density is the overview; clicking a day filters
 * the activity stream below it to that day, so the detail arrives without
 * leaving the board.
 */
class PublicationHeatmapWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected string $view = 'filament.widgets.publication-heatmap';

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public ?string $selectedDay = null;

    public function selectDay(?string $day = null): void
    {
        $this->selectedDay = ($this->selectedDay === $day) ? null : $day;

        $this->dispatch('dashboard-day-selected', day: $this->selectedDay);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $data = app(EditorialMetrics::class)
            ->publicationHeatmap($this->dashboardRange(), $this->dashboardPodcastId());

        $days = $data->entries;
        $peak = max(1, ...array_values($days));

        return [
            'days' => collect($days)
                ->map(fn (int $count, string $day): array => [
                    'day' => $day,
                    'count' => $count,
                    'label' => Carbon::parse($day)->format('d/m/Y'),
                    'level' => $count === 0 ? 0 : (int) max(1, ceil(($count / $peak) * 4)),
                ])
                ->values()
                ->all(),
            'selectedDay' => $this->selectedDay,
            'description' => $data->description,
            'total' => array_sum($days),
        ];
    }
}
