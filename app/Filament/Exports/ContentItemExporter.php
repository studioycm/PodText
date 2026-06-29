<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Models\ContentItem;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ContentItemExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;

    protected static ?string $model = ContentItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key')),
            ExportColumn::make('content_group_reference_key')
                ->label(__('admin.import.columns.content_group_reference_key'))
                ->state(fn (ContentItem $record): ?string => $record->contentGroup?->reference_key),
            ExportColumn::make('title')
                ->label(__('admin.fields.title'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('type_label_singular_override')
                ->label(__('admin.fields.type_label_singular_override'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('description_markdown')
                ->label(__('admin.fields.description_markdown'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('media_url')
                ->label(__('admin.fields.media_url'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('embed_url')
                ->label(__('admin.fields.embed_url'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('duration_seconds')
                ->label(__('admin.fields.duration_seconds')),
            ExportColumn::make('original_published_at')
                ->label(__('admin.fields.original_published_at')),
            ExportColumn::make('status')
                ->label(__('admin.fields.status')),
            ExportColumn::make('published_at')
                ->label(__('admin.fields.published_at')),
            ExportColumn::make('author_reference_keys')
                ->label(__('admin.import.columns.author_reference_keys'))
                ->state(fn (ContentItem $record): string => $record->authors
                    ->pluck('reference_key')
                    ->implode('|')),
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
        $body = 'Your content item export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
