<?php

namespace App\Filament\Resources\ContentTags\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContentTagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->content())
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_enabled')
                    ->label(__('admin.fields.is_enabled'))
                    ->boolean(),
                TextColumn::make('enabled_at')
                    ->label(__('admin.fields.enabled_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable(),
                TextColumn::make('moderation_state')
                    ->label(__('admin.fields.moderation_state'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('order_column')
                    ->label(__('admin.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_enabled')
                    ->label(__('admin.fields.is_enabled')),
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
