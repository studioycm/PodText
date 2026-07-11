<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Filament\Exports\Concerns\TracksExportLifecycle;
use App\Models\Category;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

class CategoryExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;
    use TracksExportLifecycle;

    protected static ?string $model = Category::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('path')
                ->label(__('admin.import.columns.category_path'))
                ->state(fn (Category $record): string => self::categoryPath($record->loadMissing('parent'))),
            ExportColumn::make('name')
                ->label(__('admin.fields.name'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('parent_slug')
                ->label(__('admin.import.columns.parent_category_path'))
                ->state(fn (Category $record): ?string => $record->parent ? self::categoryPath($record->parent) : null),
            ExportColumn::make('is_visible')
                ->label(__('admin.fields.is_visible')),
            ExportColumn::make('sort_order')
                ->label(__('admin.fields.sort_order')),
            ExportColumn::make('description_markdown')
                ->label(__('admin.fields.description_markdown'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
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
        return $query->with('parent');
    }
}
