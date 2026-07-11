<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Filament\Exports\Concerns\TracksExportLifecycle;
use App\Models\ContentGroup;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

class ContentGroupExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;
    use TracksExportLifecycle;

    protected static ?string $model = ContentGroup::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key')),
            ExportColumn::make('title')
                ->label(__('admin.fields.title'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('group_type_label_singular')
                ->label(__('admin.fields.group_type_label_singular'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('group_type_label_plural')
                ->label(__('admin.fields.group_type_label_plural'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('default_item_type_label_singular')
                ->label(__('admin.fields.default_item_type_label_singular'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('default_item_type_label_plural')
                ->label(__('admin.fields.default_item_type_label_plural'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('description_markdown')
                ->label(__('admin.fields.description_markdown'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('cover_path')
                ->label(__('admin.fields.cover_path'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('original_language_code')
                ->label(__('admin.fields.original_language_code')),
            ExportColumn::make('status')
                ->label(__('admin.fields.status')),
            ExportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('homepage_order')
                ->label(__('admin.fields.homepage_order')),
            ExportColumn::make('category_paths')
                ->label(__('admin.import.columns.category_paths'))
                ->state(fn (ContentGroup $record): string => self::categoryPaths($record->categories)),
            ExportColumn::make('created_at')
                ->label(__('admin.fields.created_at'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('updated_at')
                ->label(__('admin.fields.updated_at'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('categories.parent');
    }
}
