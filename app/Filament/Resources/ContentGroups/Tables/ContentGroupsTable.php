<?php

namespace App\Filament\Resources\ContentGroups\Tables;

use App\Enums\PublicationStatus;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Imports\ContentGroupImporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\File;

class ContentGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_path')
                    ->label(__('admin.fields.cover_path'))
                    ->disk('public')
                    ->visibility('public')
                    ->square(),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group_type_label_singular')
                    ->label(__('admin.fields.group_type_label_singular'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('default_item_type_label_singular')
                    ->label(__('admin.fields.default_item_type_label_singular'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('original_language_code')
                    ->label(__('admin.fields.original_language_code'))
                    ->formatStateUsing(fn (string $state): string => __("admin.locales.{$state}"))
                    ->badge()
                    ->searchable(),
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
                TextColumn::make('reference_key')
                    ->label(__('admin.fields.reference_key'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicationStatus::class),
                SelectFilter::make('original_language_code')
                    ->label(__('admin.fields.original_language_code'))
                    ->options(fn (): array => collect(config('localization.available_locales', ['he', 'en']))
                        ->mapWithKeys(fn (string $locale): array => [$locale => __("admin.locales.{$locale}")])
                        ->all()),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(ContentGroupImporter::class)
                    ->maxRows(1000)
                    ->chunkSize(25)
                    ->fileRules([File::types(['csv', 'txt'])->max(10240)]),
                ExportAction::make()
                    ->exporter(ContentGroupExporter::class)
                    ->maxRows(10000),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContentGroupExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
