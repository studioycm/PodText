<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardLens;
use App\Enums\DashboardRange;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class DashboardContextWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard-context';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(EditorialMetrics::class)->snapshot();
        $lens = DashboardLens::fromFilter($this->pageFilters['lens'] ?? null);

        return [
            'funnel' => $metrics['funnel'],
            'lens' => $lens,
            'range' => $lens === DashboardLens::Blockers
                ? null
                : DashboardRange::fromFilter($this->pageFilters['range'] ?? null),
            'generatedAt' => Carbon::parse($metrics['generated_at'])
                ->timezone('Asia/Jerusalem')
                ->format('H:i'),
            'indexUrl' => ContentItemResource::getUrl('index'),
        ];
    }
}
