<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Models\Author;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class AuthorExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;

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
                ->label(__('admin.fields.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('admin.fields.updated_at')),
        ];
    }

    public function getJobQueue(): ?string
    {
        return 'imports-exports';
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addHour();
    }

    /**
     * @return array<int, int>
     */
    public function getJobBackoff(): array
    {
        return [30, 120, 300];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your author export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
