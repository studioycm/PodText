<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\EscapesSpreadsheetFormulae;
use App\Models\ContentGroup;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
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

    public function getJobQueue(): ?string
    {
        $this->logLifecycle('dispatch queue resolved', [
            'queue' => 'imports-exports',
        ]);

        return 'imports-exports';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('categories.parent');
    }

    public function getJobBatchName(): ?string
    {
        $name = "content-group-export-{$this->export->getKey()}";

        $this->logLifecycle('batch name resolved', [
            'batch' => $name,
        ]);

        return $name;
    }

    /**
     * @return array<int, string>
     */
    public function getJobTags(): array
    {
        return [
            ...parent::getJobTags(),
            'filament-export',
            'content-group-export',
        ];
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

    public static function modifyCompletedNotification(Notification $notification, Export $export): Notification
    {
        self::logLifecycleFor($export, 'completion notification prepared', [
            'failed_rows' => $export->getFailedRowsCount(),
        ]);

        return $notification;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logLifecycle(string $event, array $context = []): void
    {
        self::logLifecycleFor($this->export, $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logLifecycleFor(Export $export, string $event, array $context = []): void
    {
        Log::channel('import_export')->info("Content group export {$event}", [
            'export_id' => $export->getKey(),
            'user_id' => $export->user_id,
            'exporter' => $export->exporter,
            'total_rows' => $export->total_rows,
            'processed_rows' => $export->processed_rows,
            'successful_rows' => $export->successful_rows,
            'completed_at' => $export->completed_at instanceof CarbonInterface
                ? $export->completed_at->toISOString()
                : $export->completed_at,
        ] + $context);
    }
}
