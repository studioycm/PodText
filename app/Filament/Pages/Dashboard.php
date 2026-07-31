<?php

namespace App\Filament\Pages;

use App\Enums\DashboardLens;
use App\Enums\DashboardRange;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Filament\Widgets\BlockersQueueWidget;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\PublicationGapWidget;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    use UsesAdminNavigationOrder;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('lens')
                ->hiddenLabel()
                ->grouped()
                ->live()
                ->default(DashboardLens::Overview->value)
                ->options(DashboardLens::options()),
            ToggleButtons::make('range')
                ->hiddenLabel()
                ->grouped()
                ->live()
                ->default(DashboardRange::Last30Days->value)
                ->options(DashboardRange::options())
                // A blocker is a blocker at any age: the range filter does not
                // apply to the blockers lens, so it disappears there.
                ->visible(fn (Get $get): bool => DashboardLens::fromFilter($get('lens')) !== DashboardLens::Blockers),
        ]);
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
            DashboardLens::Overview, DashboardLens::Intake => [
                DashboardContextWidget::class,
                EditorialStatsWidget::class,
            ],
            DashboardLens::Blockers => [
                DashboardContextWidget::class,
                PublicationGapWidget::class,
                BlockersQueueWidget::class,
            ],
        };
    }
}
