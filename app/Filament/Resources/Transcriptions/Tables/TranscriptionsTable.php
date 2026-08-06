<?php

namespace App\Filament\Resources\Transcriptions\Tables;

use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Filament\Exports\TranscriptionExporter;
use App\Filament\Imports\TranscriptionImporter;
use App\Filament\Resources\Support\ResourceTableActions;
use App\Models\Transcription;
use App\Support\Search\FoldedSearch;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use App\Support\Transcriptions\SingleTranscriptionLens;
use App\Support\Transcriptions\TranscriptionModeLabel;
use App\Support\UiTimezone;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\File;

class TranscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return ResourceTableActions::iconOnly($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => app(SingleTranscriptionLens::class)
                ->applyAdminCurrentScope($query->with('authors')))
            ->columns([
                TextColumn::make('contentItem.title')
                    ->label(TranscriptionModeLabel::text('admin.fields.content_item'))
                    ->foldedSearchable()
                    ->sortable(),
                TextColumn::make('transcriber_names')
                    ->label(__('admin.fields.transcribers'))
                    ->state(fn (Transcription $record): string => implode(', ', $record->transcriberNames()))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('authors', fn (Builder $query): Builder => $query->where('name_search', 'like', FoldedSearch::pattern($search)))),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->foldedSearchable()
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
                    ->dateTime('d/m/Y H:i', UiTimezone::name())
                    ->sortable(),
                TextColumn::make('word_count')
                    ->label(__('admin.fields.word_count'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime('d/m/Y H:i', UiTimezone::name())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_key')
                    ->label(__('admin.fields.reference_key'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('history')
                    ->label(TranscriptionModeLabel::text('admin.filters.transcription_history'))
                    ->baseQuery(fn (Builder $query): Builder => app(SingleTranscriptionLens::class)
                        ->removeAdminCurrentScope($query))
                    ->visible(fn (): bool => MultiTranscriptionSurfaces::currentUserCan(
                        UserRole::SuperAdmin,
                        requiresMode: false,
                    )),
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicationStatus::class),
                SelectFilter::make('transcriber_id')
                    ->label(__('admin.fields.transcribers'))
                    ->relationship('authors', 'name')
                    ->searchable()
                    ->optionsLimit(50),
                SelectFilter::make('content_group_id')
                    ->label(__('admin.fields.content_group'))
                    ->relationship('contentItem.contentGroup', 'title')
                    ->searchable()
                    ->optionsLimit(50),
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
                    ->visible(fn (Transcription $record): bool => MultiTranscriptionSurfaces::isMultiMode()
                        && $record->isPublished())
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
