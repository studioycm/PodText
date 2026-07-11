<?php

use App\Filament\Exports\ContentGroupExporter;
use Filament\Actions\Exports\Models\Export;

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
