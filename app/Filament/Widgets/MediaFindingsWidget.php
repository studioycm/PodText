<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Widget;

/**
 * Decision 5's bars: every media diagnostic reason with a non-zero count,
 * styled by the enum's own colour/icon, each exiting into the gallery's
 * needs-attention task pre-filtered to that finding — where the repair
 * actions live. The rate line is the "clean" caption.
 */
class MediaFindingsWidget extends Widget
{
    use AdminOnlyWidget;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.media-findings';

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(EditorialMetrics::class)->mediaFindings();
    }
}
