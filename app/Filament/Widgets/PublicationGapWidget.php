<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class PublicationGapWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected string $view = 'filament.widgets.publication-gap';

    protected static ?int $sort = -40;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(EditorialMetrics::class);
        $podcastId = $this->dashboardPodcastId();
        $snapshot = $metrics->snapshot($podcastId);
        $forecast = $metrics->clearanceForecast($podcastId);
        $breakdown = $metrics->reasonBreakdown($podcastId);

        return [
            'visible' => $snapshot['funnel']['visible'],
            'invisible' => $snapshot['gap']['invisible'],
            'attention' => $snapshot['attention']['total'],
            'rate' => $metrics->visibilityRate($podcastId),
            // H2's two questions: how much is missing, and why — now split so a
            // needs-attention episode is never described as invisible.
            'gapReasons' => $breakdown['gap'],
            'attentionReasons' => $breakdown['attention'],
            'forecast' => $forecast?->timezone('Asia/Jerusalem')->format('d/m/Y'),
        ];
    }
}
