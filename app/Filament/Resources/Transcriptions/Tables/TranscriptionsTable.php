<?php

namespace App\Filament\Resources\Transcriptions\Tables;

use App\Enums\PublicationStatus;
use App\Filament\Exports\TranscriptionExporter;
use App\Filament\Imports\TranscriptionImporter;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\Transcription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\File;

class TranscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('authors'))
            ->columns([
                TextColumn::make('contentItem.title')
                    ->label(__('admin.fields.content_item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transcriber_names')
                    ->label(__('admin.fields.transcribers'))
                    ->state(fn (Transcription $record): string => implode(', ', $record->transcriberNames()))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('authors', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))),
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
                SelectFilter::make('transcriber_id')
                    ->label(__('admin.fields.transcribers'))
                    ->options(fn (): array => Author::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('authors', fn (Builder $query): Builder => $query->whereKey($data['value']))
                        : $query),
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
            ->headerActions([
                ImportAction::make()
                    ->importer(TranscriptionImporter::class)
                    ->maxRows(1000)
                    ->chunkSize(10)
                    ->fileRules([File::types(['csv', 'txt'])->max(10240)]),
                ExportAction::make()
                    ->exporter(TranscriptionExporter::class)
                    ->maxRows(10000),
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
                    ExportBulkAction::make()
                        ->exporter(TranscriptionExporter::class)
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
