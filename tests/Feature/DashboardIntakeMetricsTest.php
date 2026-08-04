<?php

use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Models\ImportConnection;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('counts the intake queue in the snapshot', function (): void {
    PublicFormSubmission::factory()->count(2)->create();
    PublicFormSubmission::factory()->reviewed()->create();
    failedImport(failed: 3);

    $snapshot = app(EditorialMetrics::class)->intakeSnapshot();

    expect($snapshot['queue'])->toBe(['submissions' => 2, 'imports' => 1, 'failed_rows' => 3]);
});

it('merges queue rows newest-first with typed kinds and doorways', function (): void {
    PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);
    failedImport();

    $queue = app(EditorialMetrics::class)->intakeQueue();

    expect($queue['counts'])->toBe(['all' => 2, 'submissions' => 1, 'imports' => 1])
        ->and(collect($queue['rows'])->pluck('type')->all())
        ->toContain(StreamEventType::Submission, StreamEventType::Import);

    $importRow = collect($queue['rows'])->firstWhere('type', StreamEventType::Import);
    expect($importRow['title'])->toBe('episodes.csv')
        ->and($importRow['url'])->toContain('failed-rows/download')
        ->and($importRow['url'])->toContain('signature=');
});

it('narrows the queue by kind and by source', function (): void {
    PublicFormSubmission::factory()->create();
    failedImport();
    $metrics = app(EditorialMetrics::class);

    expect(collect($metrics->intakeQueue(kind: StreamEventType::Submission)['rows'])->pluck('type')->unique()->all())
        ->toBe([StreamEventType::Submission])
        // D-4: manual = the full queue; a connected-provider source has no
        // attributable rows today and must return none rather than lie.
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Manual)['counts']['all'])->toBe(2)
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['rows'])->toBe([])
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['counts']['all'])->toBe(0);
});

it('echoes the Spotify connection test and derives reduced mode', function (): void {
    $metrics = app(EditorialMetrics::class);

    expect($metrics->spotifyConnectionEcho())->toBe(['connections' => [], 'reduced' => true]);

    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => ImportConnectionAuthType::ClientCredentials,
        'status' => ImportConnectionStatus::Failed,
        'last_tested_at' => now(),
    ]);

    $echo = $metrics->spotifyConnectionEcho();
    expect($echo['reduced'])->toBeTrue()
        ->and($echo['connections'][0]['status'])->toBe(ImportConnectionStatus::Failed);

    ImportConnection::query()->update(['status' => ImportConnectionStatus::Connected]);
    expect(app(EditorialMetrics::class)->spotifyConnectionEcho()['reduced'])->toBeFalse();
});

it('builds media finding bars with hidden zero rows and gallery doorways', function (): void {
    cleanMedia();
    missingFileMedia();

    $findings = app(EditorialMetrics::class)->mediaFindings();
    $missing = collect($findings['rows'])->first(
        fn ($row): bool => $row->meta('reason') === MediaDiagnosticReason::MissingFile->value,
    );

    expect($missing)->not->toBeNull()
        ->and($missing->value)->toBe(1.0)
        ->and($missing->url)
        ->toContain('tab='.MediaLibraryTask::NeedsAttention->value)
        ->and($missing->url)->toContain('filters%5Breason%5D%5Bvalue%5D='.MediaDiagnosticReason::MissingFile->value)
        // Zero-count findings are hidden (decision 5): no bar may report 0.
        ->and(collect($findings['rows'])->every(fn ($row): bool => $row->value > 0))->toBeTrue()
        ->and($findings['rate']->of)->toBe(2);
});

it('forgets the intake snapshot on intake writes', function (): void {
    $metrics = app(EditorialMetrics::class);
    expect($metrics->intakeSnapshot()['queue']['submissions'])->toBe(0);

    PublicFormSubmission::factory()->create();
    expect(app(EditorialMetrics::class)->intakeSnapshot()['queue']['submissions'])->toBe(1);

    failedImport();
    expect(app(EditorialMetrics::class)->intakeSnapshot()['queue']['imports'])->toBe(1);

    Media::factory()->create();
    ImportConnection::factory()->create(['provider' => ImportConnectionProvider::Manual]);
    // Media/connection writes must also invalidate — the snapshot may never
    // contradict a change the editor just made (decision 12's contract).
    expect(app(EditorialMetrics::class)->intakeSnapshot()['media']['total'])->toBe(1);
});
