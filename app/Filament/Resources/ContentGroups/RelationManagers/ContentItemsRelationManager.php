<?php

namespace App\Filament\Resources\ContentGroups\RelationManagers;

use App\Enums\PublicationStatus;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Actions\EditEffectiveTranscriptionAction;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Support\PublicFront\PublicDefaultImageResolver;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContentItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'contentItems';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return ContentItemResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'categories',
                    'contentGroup',
                    'tags',
                    'featuredTranscription.authors',
                    'latestPublishedTranscription.authors',
                ])
                ->withCount('transcriptions')
                ->latest('published_at')
                ->latest('id'))
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
                TextColumn::make('effective_type_label')
                    ->label(__('admin.fields.effective_type_label'))
                    ->state(fn (ContentItem $record): string => $record->effectiveTypeLabelSingular())
                    ->badge(),
                TextColumn::make('effective_transcribers')
                    ->label(__('admin.fields.transcribers'))
                    ->state(fn (ContentItem $record): string => implode(', ', $record->effectiveTranscription()?->transcriberNames() ?? []))
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
                TextColumn::make('transcriptions_count')
                    ->label(__('admin.tabs.transcriptions'))
                    ->counts('transcriptions')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('is_pinned')
                    ->label(__('admin.fields.is_pinned'))
                    ->state(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? __('admin.labels.active') : __('admin.labels.inactive'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? 'warning' : 'gray')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicationStatus::class),
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
                TernaryFilter::make('is_pinned')
                    ->label(__('admin.fields.is_pinned')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.actions.classic_create'))
                    ->createAnother(false)
                    ->modalWidth(Width::SevenExtraLarge),
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
                ContentItemsTable::addTranscriptionAction(),
                EditAction::make()
                    ->label(__('admin.actions.classic_edit'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->modalWidth(Width::SevenExtraLarge),
                Action::make('openResource')
                    ->label(__('admin.actions.open_content_item_resource'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make(__('admin.tabs.content_items'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->badge((string) $ownerRecord->contentItems()->count())
            ->badgeColor('info')
            ->badgeTooltip(__('admin.tabs.content_items_badge_tooltip'));
    }
}
