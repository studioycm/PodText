<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Models\ContentItem;
use App\Support\Dashboard\EditorialMetrics;
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
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        $metrics = app(EditorialMetrics::class);
        $podcastId = $this->dashboardPodcastId();

        return $table
            // The Dashboard page owns the bare `filters`, `search`, `sort` and
            // `page` query-string keys through its own `#[Url] $filters`, so
            // this table namespaces its own.
            ->queryStringIdentifier('blockersQueue')
            ->heading(__('admin.dashboard.queue.heading'))
            ->description(fn (): HtmlString => $this->burnDown())
            ->query(
                $metrics
                    ->blockedQuery($podcastId)
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
                    ->formatStateUsing(fn (string $state): string => __("admin.dashboard.reasons.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'missing_transcription' => 'danger',
                        'missing_media' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem'),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label(__('admin.dashboard.queue.reasons'))
                    ->options([
                        'missing_transcription' => __('admin.dashboard.reasons.missing_transcription'),
                        'missing_media' => __('admin.dashboard.reasons.missing_media'),
                        'missing_category' => __('admin.dashboard.reasons.missing_category'),
                    ])
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

    /** H7 · the burn-down bar and clearance forecast in the queue header. */
    private function burnDown(): HtmlString
    {
        $metrics = app(EditorialMetrics::class);
        $podcastId = $this->dashboardPodcastId();
        $progress = $metrics->blockersProgress($podcastId);

        return new HtmlString(view('filament.widgets.partials.queue-burndown', [
            'remaining' => $progress['remaining'],
            'total' => $progress['total'],
            'forecast' => $metrics->clearanceForecast($podcastId)?->timezone('Asia/Jerusalem')->format('d/m/Y'),
        ])->render());
    }
}
