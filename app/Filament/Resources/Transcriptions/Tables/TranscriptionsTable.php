<?php

namespace App\Filament\Resources\Transcriptions\Tables;

use App\Enums\PublicationStatus;
use App\Models\ContentGroup;
use App\Models\Transcription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TranscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contentItem.title')
                    ->label(__('admin.fields.content_item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label(__('admin.fields.author'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('language_code')
                    ->label(__('admin.fields.language_code'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable(),
                TextColumn::make('word_count')
                    ->label(__('admin.fields.word_count'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_key')
                    ->label(__('admin.fields.reference_key'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicationStatus::class),
                SelectFilter::make('author_id')
                    ->label(__('admin.fields.author'))
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('content_group_id')
                    ->label(__('admin.fields.content_group'))
                    ->options(fn (): array => ContentGroup::query()
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('contentItem', fn (Builder $query): Builder => $query->where('content_group_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('setFeatured')
                    ->label(__('admin.actions.set_featured_transcription'))
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->visible(fn (Transcription $record): bool => $record->isPublished())
                    ->action(function (Transcription $record): void {
                        $record->contentItem()->update([
                            'featured_transcription_id' => $record->getKey(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title(__('admin.notifications.featured_transcription_saved'))
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
