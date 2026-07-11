<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Filament\Exports\Concerns\TracksExportLifecycle;
use App\Models\Transcription;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class TranscriptionExporter extends Exporter
{
    use EscapesSpreadsheetFormulae;
    use TracksExportLifecycle;

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
                ->state(fn (Transcription $record): ?string => $record->primaryTranscriber()?->reference_key),
            ExportColumn::make('primary_transcriber_reference_key')
                ->label(__('admin.import.columns.primary_transcriber_reference_key'))
                ->state(fn (Transcription $record): ?string => $record->primaryTranscriber()?->reference_key),
            ExportColumn::make('transcriber_reference_keys')
                ->label(__('admin.import.columns.transcriber_reference_keys'))
                ->state(fn (Transcription $record): string => $record->authors
                    ->pluck('reference_key')
                    ->implode('|')),
            ExportColumn::make('transcriber_names')
                ->label(__('admin.import.columns.transcriber_names'))
                ->state(fn (Transcription $record): string => self::safeSpreadsheetText(implode('|', $record->transcriberNames()))),
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

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'contentItem',
            'authors',
            'author',
        ]);
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
