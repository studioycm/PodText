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
            ExportColumn::make('embed_provider')
                ->label(__('admin.fields.embed_provider'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('media_duration_seconds')
                ->label(__('admin.fields.media_duration_seconds')),
            ExportColumn::make('external_id')
                ->label(__('admin.fields.external_id'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('external_title')
                ->label(__('admin.fields.external_title'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('external_description')
                ->label(__('admin.fields.external_description'))
                ->enabledByDefault(false)
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('external_thumbnail_url')
                ->label(__('admin.fields.external_thumbnail_url'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('external_published_at')
                ->label(__('admin.fields.external_published_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('media_metadata')
                ->label(__('admin.fields.media_metadata'))
                ->enabledByDefault(false)
                ->state(fn (ContentItem $record): ?string => self::safeSpreadsheetJson($record->media_metadata)),
            ExportColumn::make('direct_media_url')
                ->label(__('admin.fields.direct_media_url'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetText($state)),
            ExportColumn::make('original_published_at')
                ->label(__('admin.fields.original_published_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('status')
                ->label(__('admin.fields.status')),
            ExportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('is_pinned')
                ->label(__('admin.fields.is_pinned')),
            ExportColumn::make('pinned_at')
                ->label(__('admin.fields.pinned_at'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('pinned_until')
                ->label(__('admin.fields.pinned_until'))
                ->formatStateUsing(fn (mixed $state): ?string => self::safeSpreadsheetDateTime($state)),
            ExportColumn::make('pin_order')
                ->label(__('admin.fields.pin_order')),
            ExportColumn::make('category_paths')
                ->label(__('admin.import.columns.category_paths'))
                ->state(fn (ContentItem $record): string => self::categoryPaths($record->categories)),
            ExportColumn::make('content_tag_slugs')
                ->label(__('admin.import.columns.content_tag_slugs'))
                ->state(fn (ContentItem $record): string => $record->contentTags
                    ->map(fn ($tag): string => $tag->getTranslation('slug', app()->getLocale(), false))
                    ->implode('|')),
            ExportColumn::make('featured_transcription_reference_key')
                ->label(__('admin.import.columns.featured_transcription_reference_key'))
                ->state(fn (ContentItem $record): ?string => $record->featuredTranscription?->reference_key),
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
        $body = 'Your content item export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
