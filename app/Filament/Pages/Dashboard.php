<?php

namespace App\Filament\Pages;

use App\Enums\DashboardLens;
use App\Enums\DashboardRange;
use App\Enums\ImportConnectionProvider;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Filament\Widgets\ActivityStreamWidget;
use App\Filament\Widgets\BlockersQueueWidget;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\IntakeQueueWidget;
use App\Filament\Widgets\LibraryCompositionWidget;
use App\Filament\Widgets\MediaFindingsWidget;
use App\Filament\Widgets\PublicationFunnelWidget;
use App\Filament\Widgets\PublicationGapWidget;
use App\Filament\Widgets\PublicationHeatmapWidget;
use App\Filament\Widgets\PublicFormTargetWarningsWidget;
use App\Filament\Widgets\SpotifyConnectionWidget;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    use UsesAdminNavigationOrder;

    public function filtersForm(Schema $schema): Schema
    {
        // Gap-filler G1 · the two-row command bar: row 1 is lens navigation,
        // row 2 is the contextual filters. They must be separate grid rows —
        // grouped ToggleButtons are wide, and sharing one row makes them
        // overflow their cell and paint over the podcast select.
        //
        // Explicit keys: this page renders its own filters schema alongside
        // widget-owned schemas, so no component may fall back to a key derived
        // from its state path.
        // Every breakpoint must be named: `getFiltersForm()` ships
        // `md: 2, xl: 3, 2xl: 4`, and a bare `columns(1)` only overrides
        // `default`/`lg`, leaving the two rows side by side on wide screens.
        return $schema
            ->columns(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1, '2xl' => 1])
            ->components([
                Grid::make()
                    ->key('dashboardLensRow')
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        ToggleButtons::make('lens')
                            ->key('dashboardLens')
                            ->hiddenLabel()
                            ->grouped()
                            ->live()
                            ->default(DashboardLens::Overview->value)
                            ->options(DashboardLens::options()),
                    ]),
                Grid::make()
                    ->key('dashboardScopeRow')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        ToggleButtons::make('range')
                            ->key('dashboardRange')
                            ->hiddenLabel()
                            ->grouped()
                            ->live()
                            ->default(DashboardRange::Last30Days->value)
                            ->options(DashboardRange::options())
                            // A blocker is a blocker at any age, and nothing
                            // on Intake is range-scoped (decision 7): the
                            // range filter disappears on both lenses.
                            ->visible(fn (Get $get): bool => ! in_array(
                                DashboardLens::fromFilter($get('lens')),
                                [DashboardLens::Blockers, DashboardLens::Intake],
                                strict: true,
                            )),
                        Select::make('podcast')
                            ->key('dashboardPodcast')
                            ->hiddenLabel()
                            ->live()
                            ->placeholder(__('admin.dashboard.filters.all_podcasts'))
                            ->helperText(__('admin.dashboard.filters.podcast_hint'))
                            ->options(fn (): array => app(EditorialMetrics::class)->podcastOptions())
                            ->searchable()
                            ->optionsLimit(50)
                            // No Intake widget has a podcast dimension (D-5).
                            ->visible(fn (Get $get): bool => DashboardLens::fromFilter($get('lens')) !== DashboardLens::Intake),
                        Select::make('source')
                            ->key('dashboardSource')
                            ->hiddenLabel()
                            ->live()
                            ->placeholder(__('admin.dashboard.filters.all_sources'))
                            ->helperText(__('admin.dashboard.filters.source_hint'))
                            // Three fixed options: native Select, no search
                            // (settings-dashboard rule for tiny finite sets).
                            ->options(ImportConnectionProvider::options())
                            ->visible(fn (Get $get): bool => DashboardLens::fromFilter($get('lens')) === DashboardLens::Intake),
                    ]),
            ]);
    }

    /** The only filter keys the command bar owns. */
    private const FILTER_KEYS = ['lens', 'range', 'podcast', 'status', 'source'];

    /**
     * The board's own widgets write filters back here, so a legend chip, a
     * funnel gap and a lens pill all move the same state.
     *
     * Livewire events can be dispatched from the browser, and `$filters` is
     * both URL-bound and session-persisted, so anything outside the command
     * bar's own keys is ignored rather than written.
     */
    #[On('dashboard-filter')]
    public function applyDashboardFilter(string $key, ?string $value = null): void
    {
        if (! in_array($key, self::FILTER_KEYS, strict: true)) {
            return;
        }

        $filters = $this->filters ?? [];

        // The legend is a toggle: clicking the active chip clears the board.
        $filters[$key] = ($key === 'status' && ($filters[$key] ?? null) === $value)
            ? null
            : $value;

        $this->filters = $filters;
        $this->updatedFilters();
    }

    /** @return array<int, class-string<Widget>> */
    public function getWidgets(): array
    {
        return static::getWidgetsForLens(DashboardLens::fromFilter($this->filters['lens'] ?? null));
    }

    /** @return array<int, class-string<Widget>> */
    public static function getWidgetsForLens(DashboardLens $lens): array
    {
        return match ($lens) {
            DashboardLens::Overview => [
                DashboardContextWidget::class,
                PublicFormTargetWarningsWidget::class,
                PublicationFunnelWidget::class,
                EditorialStatsWidget::class,
                PublicationHeatmapWidget::class,
                ActivityStreamWidget::class,
                LibraryCompositionWidget::class,
            ],
            DashboardLens::Blockers => [
                DashboardContextWidget::class,
                PublicationGapWidget::class,
                BlockersQueueWidget::class,
            ],
            // PublicFormTargetWarningsWidget stays on Intake deliberately —
            // its warnings are intake-adjacent and it hides itself when
            // clean (the Q6 ruling, the one-lens-one-question exception).
            DashboardLens::Intake => [
                DashboardContextWidget::class,
                PublicFormTargetWarningsWidget::class,
                IntakeQueueWidget::class,
                SpotifyConnectionWidget::class,
                MediaFindingsWidget::class,
            ],
        };
    }
}
