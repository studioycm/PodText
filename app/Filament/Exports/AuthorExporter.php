<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Filament\Exports\Concerns\TracksExportLifecycle;
use App\Models\Author;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class AuthorExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;
    use TracksExportLifecycle;

    protected static ?string $model = Author::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key')),
            ExportColumn::make('name')
                ->label(__('admin.fields.author_name'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('bio_markdown')
                ->label(__('admin.fields.bio_markdown'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('created_at')
                ->label(__('admin.fields.created_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('updated_at')
                ->label(__('admin.fields.updated_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
        ];
    }
}
