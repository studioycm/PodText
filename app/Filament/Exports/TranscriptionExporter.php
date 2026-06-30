<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Models\Transcription;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TranscriptionExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;

    protected static ?string $model = Transcription::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key')),
            ExportColumn::make('content_item_reference_key')
                ->label(__('admin.import.columns.content_item_reference_key'))
                ->state(fn (Transcription $record): ?string => $record->contentItem?->reference_key),
            ExportColumn::make('author_reference_key')
                ->label(__('admin.import.columns.author_reference_key'))
                ->state(fn (Transcription $record): ?string => $record->author?->reference_key),
            ExportColumn::make('title')
                ->label(__('admin.fields.title'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('language_code')
                ->label(__('admin.fields.language_code')),
            ExportColumn::make('transcript_markdown')
                ->label(__('admin.fields.transcript_markdown'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('status')
                ->label(__('admin.fields.status')),
            ExportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('word_count')
                ->label(__('admin.fields.word_count'))
                ->enabledByDefault(false),
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
        $body = 'Your transcription export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
