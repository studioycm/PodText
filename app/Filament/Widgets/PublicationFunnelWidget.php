<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Enums\EpisodeListScope;
use App\Enums\FunnelStage;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
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
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

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

        $stages = [];

        foreach (FunnelStage::cases() as $stage) {
            $row = $series[$stage->value];

            $stages[$stage->value] = [
                'stage' => $stage,
                'count' => $snapshot['funnel'][$stage->value],
                'row' => $row,
                'series' => $row->points,
                'delta' => $row->delta(),
                'bar' => $stage->barClass(),
                'active' => $active === $stage->value,
                // Only two stages have a scope that answers exactly what the
                // number counted. Published means status-published AND
                // released, and Transcribed adds a transcript on top — neither
                // is any tab or union of tabs, and both used to send
                // status=published, a superset. ES-1: no link beats a link to
                // an approximately-right list.
                'url' => match ($stage) {
                    FunnelStage::Draft => ContentItemResource::getUrl('index', $this->scopedTableScope(EpisodeListScope::Drafts)),
                    FunnelStage::Visible => ContentItemResource::getUrl('index', $this->scopedTableScope(EpisodeListScope::Visible)),
                    default => null,
                },
            ];
        }

        return [
            'stages' => $stages,
            'total' => max(1, array_sum(array_column($stages, 'count'))),
            // The gap is exactly status-published minus visible: the invisible
            // tier, never the needs-attention one.
            'gap' => $snapshot['gap']['invisible'],
        ];
    }
}
