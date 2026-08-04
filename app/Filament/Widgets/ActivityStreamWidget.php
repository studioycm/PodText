<?php

namespace App\Filament\Widgets;

use App\Enums\StreamEventType;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * G3's single list. "Recent published episodes" is this stream filtered to the
 * transcription chip, so there is never a second recent list to disagree with.
 * H4 feeds it a day from the heatmap above.
 */
class ActivityStreamWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.activity-stream';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public ?string $type = null;

    public ?string $day = null;

    public function selectType(?string $type = null): void
    {
        // Livewire-callable with a browser-supplied argument: narrow it (raw-state).
        $this->type = StreamEventType::tryFrom((string) $type)?->value;
    }

    #[On('dashboard-day-selected')]
    public function setDay(?string $day = null): void
    {
        $this->day = $day;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        // H6 · a legend chip narrows this flow widget; an explicit chip here
        // still wins, because it is the more specific choice.
        $type = $this->type ?? EditorialMetrics::streamTypeForStatus($this->dashboardStatus());

        return [
            'events' => app(EditorialMetrics::class)->activityStream(
                range: $this->dashboardRange(),
                type: $type,
                day: $this->day,
                contentGroupId: $this->dashboardPodcastId(),
            ),
            'types' => StreamEventType::cases(),
            'activeType' => $type,
            'day' => $this->day,
        ];
    }
}
