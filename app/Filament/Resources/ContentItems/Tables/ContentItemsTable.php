<?php

namespace App\Filament\Resources\ContentItems\Tables;

use App\Enums\PublicationStatus;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Actions\EditEffectiveTranscriptionAction;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Models\Author;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\Transcriptions\TranscriptionModeLabel;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\File;

class ContentItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'contentGroup',
                    'featuredTranscription.authors',
                    'latestPublishedTranscription.authors',
                ])
                ->withCount('transcriptions'))
            ->columns([
                ImageColumn::make('effective_image')
                    ->label(__('admin.fields.effective_image'))
                    ->state(fn (ContentItem $record): ?string => app(PublicDefaultImageResolver::class)->contentItemImage($record)['url'])
                    ->imageSize(48)
                    ->square(),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contentGroup.title')
                    ->label(__('admin.fields.content_group'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('effective_type_label')
                    ->label(__('admin.fields.effective_type_label'))
                    ->state(fn (ContentItem $record): string => $record->effectiveTypeLabelSingular())
                    ->badge(),
                TextColumn::make('effective_transcribers')
                    ->label(__('admin.fields.transcribers'))
                    ->state(fn (ContentItem $record): string => self::effectiveTranscriberNames($record))
                    ->badge()
                    ->separator(', '),
                TextColumn::make('effective_transcription_context')
                    ->label(__('admin.fields.effective_transcription'))
                    ->state(fn (ContentItem $record): ?string => EditEffectiveTranscriptionAction::contextStateFor($record))
                    ->placeholder(__('admin.labels.none'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => EditEffectiveTranscriptionAction::contextColorFor($record))
                    ->toggleable(),
                TextColumn::make('categories.name')
                    ->label(__('admin.fields.categories'))
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tags.name')
                    ->label(__('admin.fields.tags'))
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration_seconds')
                    ->label(__('admin.fields.duration_seconds'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable(),
                TextColumn::make('featuredTranscription.title')
                    ->label(__('admin.fields.featured_transcription'))
                    ->placeholder(__('admin.labels.none'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('embed_provider')
                    ->label(__('admin.fields.embed_provider'))
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_pinned')
                    ->label(__('admin.fields.is_pinned'))
                    ->state(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? __('admin.labels.active') : __('admin.labels.inactive'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? 'warning' : 'gray')
                    ->toggleable(),
                TextColumn::make('pin_order')
                    ->label(__('admin.fields.pin_order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('media_url')
                    ->label(__('admin.fields.media_url'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('embed_url')
                    ->label(__('admin.fields.embed_url'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_key')
                    ->label(__('admin.fields.reference_key'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('content_group_id')
                    ->label(__('admin.fields.content_group'))
                    ->relationship('contentGroup', 'title')
                    ->searchable()
                    ->preload(),
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
                        ? $query->whereHas('transcriptions.authors', fn (Builder $query): Builder => $query->whereKey($data['value']))
                        : $query),
                SelectFilter::make('categories')
                    ->label(__('admin.fields.categories'))
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('content_tags')
                    ->label(__('admin.fields.tags'))
                    ->multiple()
                    ->options(fn (): array => ContentTag::query()
                        ->content()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (ContentTag $tag): array => [$tag->getKey() => $tag->name])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? [])
                        ? $query->whereHas('tags', fn (Builder $query): Builder => $query->whereIn('tags.id', $data['values']))
                        : $query),
                SelectFilter::make('embed_provider')
                    ->label(__('admin.fields.embed_provider'))
                    ->options(fn (): array => ContentItem::query()
                        ->whereNotNull('embed_provider')
                        ->distinct()
                        ->orderBy('embed_provider')
                        ->pluck('embed_provider', 'embed_provider')
                        ->all()),
                TernaryFilter::make('is_pinned')
                    ->label(__('admin.fields.is_pinned')),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(ContentItemImporter::class)
                    ->maxRows(1000)
                    ->chunkSize(10)
                    ->fileRules([File::types(['csv', 'txt'])->max(10240)]),
                ExportAction::make()
                    ->exporter(ContentItemExporter::class)
                    ->maxRows(10000),
            ])
            ->recordUrl(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record]))
            ->recordActions([
                Action::make('openEpisodeWorkspace')
                    ->label(__('admin.actions.open_episode_workspace'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record])),
                ContentImageActions::contentItemImage(),
                ContentImageActions::downloadExternalImage(),
                ContentImageActions::downloadExternalImage(overwrite: true),
                EditEffectiveTranscriptionAction::make(),
                self::addTranscriptionAction(),
                EditAction::make()
                    ->label(__('admin.actions.classic_edit'))
                    ->icon(Heroicon::OutlinedDocumentText),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContentItemExporter::class)
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function addTranscriptionAction(): Action
    {
        return Action::make('addTranscription')
            ->label(TranscriptionModeLabel::text('admin.actions.add_transcription'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->schema([
                RelationshipOptionForms::configureTranscriberOptionsSelect(
                    Select::make('transcriber_ids'),
                    episodeLanguage: true,
                ),
                TextInput::make('title')
                    ->label(__('admin.fields.title'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcription_title'))
                    ->maxLength(255),
                TextInput::make('language_code')
                    ->label(__('admin.fields.language_code'))
                    ->helperText(__('admin.helpers.language_code'))
                    ->default('he')
                    ->required()
                    ->maxLength(10),
                Select::make('status')
                    ->label(__('admin.fields.status'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcription_status'))
                    ->options(PublicationStatus::class)
                    ->default(PublicationStatus::Draft->value)
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcription_published_at'))
                    ->displayFormat('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem'),
                MarkdownEditor::make('transcript_markdown')
                    ->label(__('admin.fields.transcript_markdown'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcript_markdown'))
                    ->disableToolbarButtons(['attachFiles'])
                    ->fileAttachments(false)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (ContentItem $record, array $data): void {
                $transcriberIds = $data['transcriber_ids'] ?? [];
                unset($data['transcriber_ids']);

                $transcription = $record->transcriptions()->create($data);
                $transcription->syncTranscribers($transcriberIds);

                Notification::make()
                    ->success()
                    ->title(TranscriptionModeLabel::text('admin.notifications.transcription_created'))
                    ->body(TranscriptionModeLabel::text('admin.notifications.first_transcription_featured'))
                    ->send();
            });
    }

    private static function effectiveTranscriberNames(ContentItem $record): string
    {
        return implode(', ', $record->effectiveTranscription()?->transcriberNames() ?? []);
    }
}
