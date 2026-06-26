<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Models\ContentGroup;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ContentGroupExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;

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
                ->label(__('admin.fields.published_at')),
            ExportColumn::make('created_at')
                ->label(__('admin.fields.created_at'))
                ->enabledByDefault(false),
            ExportColumn::make('updated_at')
                ->label(__('admin.fields.updated_at'))
                ->enabledByDefault(false),
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
        $body = 'Your content group export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
