<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Widget;

class PublicationGapWidget extends Widget
{
    protected string $view = 'filament.widgets.publication-gap';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(EditorialMetrics::class);
        $snapshot = $metrics->snapshot();
        $forecast = $metrics->clearanceForecast();

        return [
            'visible' => $snapshot['funnel']['visible'],
            'blocked' => $snapshot['blockers']['total'],
            'reasons' => [
                'missing_transcription' => ['count' => $snapshot['blockers']['missing_transcription'], 'color' => 'danger'],
                'missing_media' => ['count' => $snapshot['blockers']['missing_media'], 'color' => 'warning'],
                'missing_category' => ['count' => $snapshot['blockers']['missing_category'], 'color' => 'info'],
            ],
            'forecast' => $forecast?->timezone('Asia/Jerusalem')->format('d/m/Y'),
        ];
    }
}
