<?php

namespace App\Filament\Widgets;

use App\Enums\PublicFormSubmissionStatus;
use App\Enums\StreamEventType;
use App\Filament\Resources\Imports\ImportResource;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Board 3's first widget: what arrived and needs a decision — new public
 * submissions and imports whose rows failed. Chips narrow by kind; the
 * source filter narrows by intake channel (D-4). Chip state is
 * widget-local by decision 8: it resets on reload.
 */
class IntakeQueueWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.intake-queue';

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public ?string $kind = null;

    public function selectKind(?string $kind = null): void
    {
        // Livewire-callable with a browser-supplied argument: narrow it (raw-state).
        // Only the two queue kinds are selectable; anything else means "all".
        $this->kind = in_array($kind, [StreamEventType::Submission->value, StreamEventType::Import->value], strict: true)
            ? $kind
            : null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $source = $this->dashboardSource();
        $queue = app(EditorialMetrics::class)->intakeQueue(
            source: $source,
            kind: StreamEventType::tryFrom((string) $this->kind),
        );

        return [
            'rows' => $queue['rows'],
            'counts' => $queue['counts'],
            'activeKind' => $this->kind,
            'chipKinds' => [StreamEventType::Submission, StreamEventType::Import],
            'source' => $source,
            'submissionsUrl' => PublicFormSubmissionResource::getUrl('index', [
                'filters' => ['status' => ['value' => PublicFormSubmissionStatus::New->value]],
            ]),
            'importsUrl' => ImportResource::getUrl('index'),
        ];
    }
}
