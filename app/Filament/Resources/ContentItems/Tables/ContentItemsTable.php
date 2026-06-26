<?php

namespace App\Filament\Resources\ContentItems\Tables;

use App\Enums\PublicationStatus;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Imports\ContentItemImporter;
use App\Models\ContentItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\File;

class ContentItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
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
                TextColumn::make('authors.name')
                    ->label(__('admin.fields.authors'))
                    ->badge()
                    ->separator(', '),
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
                    ->dateTime()
                    ->sortable(),
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
                SelectFilter::make('authors')
                    ->label(__('admin.fields.authors'))
                    ->relationship('authors', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContentItemExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
