<?php

namespace App\Filament\Exports\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

trait TracksExportLifecycle
{
    public function getJobQueue(): ?string
    {
        return 'imports-exports';
    }

    public function getJobBatchName(): ?string
    {
        $name = sprintf('%s-export-%s', self::exporterKebabName(), $this->export->getKey());

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
            self::exporterTag(),
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
        Log::channel('import_export')->info(sprintf('%s export %s', self::exporterLabel(), $event), [
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

    private static function exporterKebabName(): string
    {
        return (string) str(class_basename(static::class))
            ->beforeLast('Exporter')
            ->kebab();
    }

    private static function exporterTag(): string
    {
        return self::exporterKebabName().'-export';
    }

    private static function exporterLabel(): string
    {
        return ucfirst((string) str(class_basename(static::class))
            ->beforeLast('Exporter')
            ->snake(' '));
    }
}
