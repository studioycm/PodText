<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\ContentItem;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BlockersQueueWidget extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.queue.heading'))
            ->description(__('admin.dashboard.queue.description'))
            ->query(
                app(EditorialMetrics::class)
                    ->blockedQuery()
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
}
