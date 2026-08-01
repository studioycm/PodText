<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardReason;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Models\ContentItem;
use App\Support\Dashboard\EditorialMetrics;
use App\Support\UiTimezone;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class BlockersQueueWidget extends TableWidget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        $metrics = app(EditorialMetrics::class);
        $podcastId = $this->dashboardPodcastId();

        return $table
            ->heading(__('admin.dashboard.queue.heading'))
            ->description(fn (): HtmlString => $this->burnDown())
            ->query(
                $metrics
                    ->queueQuery($podcastId)
                    ->with(['contentGroup.categories', 'categories'])
                    ->latest('content_items.published_at'),
            )
            ->paginated([10, 25])
            ->emptyStateHeading(__('admin.dashboard.queue.empty_heading'))
            ->emptyStateDescription(__('admin.dashboard.queue.empty_description'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('contentGroup.title')
                    ->label(__('admin.resources.content_group.singular'))
                    ->limit(40),
                TextColumn::make('blocker_reasons')
                    ->label(__('admin.dashboard.queue.reasons'))
                    ->badge()
                    ->state(fn (ContentItem $record): array => app(EditorialMetrics::class)->blockerReasonsFor($record))
                    ->formatStateUsing(fn (string $state): string => DashboardReason::from($state)->getLabel())
                    ->color(fn (string $state): string => DashboardReason::from($state)->getColor())
                    ->icon(fn (string $state): Heroicon => DashboardReason::from($state)->getIcon()),
                TextColumn::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->dateTime('d/m/Y H:i', UiTimezone::name()),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label(__('admin.dashboard.queue.reasons'))
                    ->options(DashboardReason::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? app(EditorialMetrics::class)->applyReason($query, $data['value'])
                        : $query),
            ])
            ->recordActions([
                Action::make('fix')
                    ->label(__('admin.dashboard.queue.fix'))
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record])),
            ]);
    }

    /**
     * H7 · one burn-down bar per tier. Both counts come from the cached
     * snapshot, so the second bar adds no queries; only the invisible tier
     * carries a forecast, because the category pivot has no timestamps to pace
     * needs-attention work from.
     */
    private function burnDown(): HtmlString
    {
        $progress = app(EditorialMetrics::class)->blockersProgress($this->dashboardPodcastId());

        return new HtmlString(view('filament.widgets.partials.queue-burndown', [
            'tiers' => $progress,
        ])->render());
    }
}
