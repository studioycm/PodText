<?php

namespace App\Filament\Resources\HomepageSections\Tables;

use App\Enums\HomepageSectionType;
use App\Filament\Resources\Support\ResourceTableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HomepageSectionsTable
{
    public static function configure(Table $table): Table
    {
        return ResourceTableActions::iconOnly($table)
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->foldedSearchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->foldedSearchable(against: 'slug')
                    ->toggleable(),
                TextColumn::make('type')
                    ->label(__('admin.fields.homepage_section_type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('admin.fields.category'))
                    ->foldedSearchable()
                    ->toggleable(),
                TextColumn::make('tag.name')
                    ->label(__('admin.fields.tag'))
                    ->foldedSearchable()
                    ->toggleable(),
                TextColumn::make('contentGroup.title')
                    ->label(__('admin.fields.content_group'))
                    ->foldedSearchable()
                    ->toggleable(),
                TextColumn::make('limit')
                    ->label(__('admin.fields.limit'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('admin.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_visible')
                    ->label(__('admin.fields.is_visible'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.fields.homepage_section_type'))
                    ->options(HomepageSectionType::class),
                TernaryFilter::make('is_visible')
                    ->label(__('admin.fields.is_visible')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
