<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
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
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected string $view = 'filament.widgets.activity-stream';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public ?string $type = null;

    public ?string $day = null;

    public function selectType(?string $type = null): void
    {
        $this->type = $type;
    }

    #[On('dashboard-day-selected')]
    public function setDay(?string $day = null): void
    {
        $this->day = $day;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'events' => app(EditorialMetrics::class)->activityStream(
                range: $this->dashboardRange(),
                type: $this->type,
                day: $this->day,
                contentGroupId: $this->dashboardPodcastId(),
            ),
            'types' => ['transcription', 'import', 'media', 'submission'],
            'activeType' => $this->type,
            'day' => $this->day,
            'badges' => [
                'transcription' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
                'import' => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
                'media' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                'submission' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
            ],
        ];
    }
}
