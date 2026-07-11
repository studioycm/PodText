<?php

use App\Filament\Exports\AuthorExporter;
use App\Filament\Exports\CategoryExporter;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Exports\TranscriptionExporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

it('supervises the import export queue with horizon', function (): void {
    expect(config('horizon.defaults.supervisor-1.queue'))
        ->toContain('default')
        ->toContain('imports-exports')
        ->and(config('horizon.waits'))->toHaveKey('redis:imports-exports')
        ->and(config('logging.channels.import_export.driver'))->toBe('daily');
});

it('adds content group export queue breadcrumbs for horizon', function (): void {
    $export = new Export;
    $export->forceFill([
        'id' => 123,
        'file_disk' => 'local',
        'file_name' => 'test.csv',
        'exporter' => ContentGroupExporter::class,
        'processed_rows' => 0,
        'total_rows' => 6,
        'successful_rows' => 0,
        'user_id' => 1,
    ]);

    $exporter = new ContentGroupExporter($export, ['title' => 'Title'], []);

    expect($exporter->getJobQueue())
        ->toBe('imports-exports')
        ->and($exporter->getJobBatchName())->toBe('content-group-export-123')
        ->and($exporter->getJobTags())
        ->toContain('export123')
        ->toContain('filament-export')
        ->toContain('content-group-export');
});

it('adds shared export queue lifecycle metadata to every native exporter', function (string $exporterClass, string $batchName, string $tag): void {
    $export = new Export;
    $export->forceFill([
        'id' => 123,
        'file_disk' => 'local',
        'file_name' => 'test.csv',
        'exporter' => $exporterClass,
        'processed_rows' => 0,
        'total_rows' => 6,
        'successful_rows' => 0,
        'user_id' => 1,
    ]);

    $exporter = new $exporterClass($export, ['title' => 'Title'], []);

    expect($exporter->getJobQueue())
        ->toBe('imports-exports')
        ->and($exporter->getJobBatchName())->toBe($batchName)
        ->and($exporter->getJobTags())
        ->toContain('export123')
        ->toContain('filament-export')
        ->toContain($tag);
})->with([
    'authors' => [AuthorExporter::class, 'author-export-123', 'author-export'],
    'categories' => [CategoryExporter::class, 'category-export-123', 'category-export'],
    'content groups' => [ContentGroupExporter::class, 'content-group-export-123', 'content-group-export'],
    'content items' => [ContentItemExporter::class, 'content-item-export-123', 'content-item-export'],
    'transcriptions' => [TranscriptionExporter::class, 'transcription-export-123', 'transcription-export'],
]);

it('filters non import export queue events before logging', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger
        ->shouldReceive('info')
        ->once()
        ->with('Import/export queue job queueing', Mockery::on(
            fn (array $context): bool => ($context['queue'] ?? null) === 'imports-exports'
                && ($context['connection'] ?? null) === 'redis'
                && ($context['job'] ?? null) === 'App\\Jobs\\SettingsSnapshotJob',
        ));

    Log::shouldReceive('channel')
        ->once()
        ->with('import_export')
        ->andReturn($logger);

    $payload = json_encode([
        'displayName' => 'App\\Jobs\\SettingsSnapshotJob',
        'uuid' => 'snapshot-job-uuid',
        'data' => [
            'commandName' => 'App\\Jobs\\SettingsSnapshotJob',
        ],
    ], JSON_THROW_ON_ERROR);

    Event::dispatch(new JobQueueing(
        connectionName: 'redis',
        queue: 'default',
        job: 'App\\Jobs\\SettingsSnapshotJob',
        payload: $payload,
        delay: null,
    ));

    Event::dispatch(new JobQueueing(
        connectionName: 'redis',
        queue: 'imports-exports',
        job: 'App\\Jobs\\SettingsSnapshotJob',
        payload: $payload,
        delay: null,
    ));
});
