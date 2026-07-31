<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * H1 · the living funnel. Each stage shows how many there are now (stock) and
 * carries a micro-sparkline of that stage's movement over the selected range,
 * so one glance answers "how many" and "which way".
 */
class PublicationFunnelWidget extends Widget
{
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected string $view = 'filament.widgets.publication-funnel';

    protected static ?int $sort = -40;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** The published→visible gap is the doorway into the blockers lens. */
    public function openBlockers(): void
    {
        $this->dispatch('dashboard-filter', key: 'lens', value: DashboardLens::Blockers->value);
    }

    public function selectStage(string $stage): void
    {
        $this->dispatch('dashboard-filter', key: 'status', value: $stage);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(EditorialMetrics::class);
        $podcastId = $this->dashboardPodcastId();
        $snapshot = $metrics->snapshot($podcastId);
        $series = $metrics->funnelSeries($this->dashboardRange(), $podcastId);
        $active = $this->dashboardStatus();

        $bars = [
            'draft' => 'bg-gray-400 dark:bg-gray-500',
            'published' => 'bg-info-500',
            'transcribed' => 'bg-primary-500',
            'visible' => 'bg-success-500',
        ];

        $stages = [];

        foreach ($bars as $stage => $barClass) {
            $stages[$stage] = [
                'count' => $snapshot['funnel'][$stage],
                'series' => $series[$stage],
                'bar' => $barClass,
                'active' => $active === $stage,
                'url' => ContentItemResource::getUrl('index', $this->scopedTableFilters(
                    $stage === 'draft'
                        ? ['status' => ['value' => 'draft']]
                        : ['status' => ['value' => 'published']],
                )),
            ];
        }

        return [
            'stages' => $stages,
            'total' => max(1, array_sum(array_column($stages, 'count'))),
            'gap' => $snapshot['funnel']['published'] - $snapshot['funnel']['visible'],
        ];
    }
}
