<?php

/**
 * F12 characterization pin — the per-operation cost structure of
 * SettingsBackupManager AFTER the operator-approved register-1.8 fixes.
 *
 * Counts, per operation: SettingsSaved events (by settings class),
 * SettingsBackupVersion rows (by source), snapshot rows (by kind/status),
 * SettingsBackupSnapshotJob executions, and node subprocess spawns
 * (Process fake with the batched results contract). The diagnosed pre-fix
 * numbers stay in per-scenario comments; every drop names the fix that
 * caused it:
 *
 * - Fix 1 (batching): each backup's snapshot rows render through ONE node
 *   spawn instead of one per row — spawns count backups now, not rows.
 * - Fix 2 (no-op short-circuit): a candidate-empty import keeps its audit
 *   BeforeImport row but schedules no snapshots and skips the unconditional
 *   save→SettingsSaved→createSystem cycle.
 * - Fix 3 (full-set dedup) never fires in these scenarios — no scenario
 *   creates two full-source backups with identical payload-minus-locks where
 *   the earlier one owns a DONE full set — so it is pinned by the dedicated
 *   tests in SettingsBackupSnapshotsTest instead.
 *
 * The still-pinned diagnosis result: one import() fires exactly ONE batched
 * save (never per-unit), and full-source backups still schedule
 * thumbs + fullTargets × themes × formats ROWS (row counts did not change —
 * only spawns and the no-op flow did).
 *
 * Evidence for docs/phase-02/open-findings-triage.md F12,
 * .superpowers/sdd/task-F12-report.md, and the fix handoff
 * .superpowers/sdd/F12-fix-handoff.md.
 */

use App\Enums\SettingsBackupSource;
use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\SettingsBackupSnapshot;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManifest;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsImportLockSurfaceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://podtext.test']);

    Cache::flush();
    // Fix 1 made DONE conditional on the per-target results file the script
    // contract now promises; a plain Process::fake() would mark every row
    // FAILED, so the pin fakes the full batched results contract instead.
    fakeSettingsSnapshotProcess();
    Storage::fake('local');

    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();

    $this->savedEvents = [];
    $this->jobsProcessed = 0;

    Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
        $class = class_basename($event->settings::class);
        $this->savedEvents[$class] = ($this->savedEvents[$class] ?? 0) + 1;
    });

    Event::listen(JobProcessed::class, function (JobProcessed $event): void {
        if (str_contains($event->job->resolveName(), 'SettingsBackupSnapshotJob')) {
            $this->jobsProcessed++;
        }
    });
});

function f12FreshSettings(): PublicContentSettings
{
    app()->forgetInstance(PublicContentSettings::class);
    app(SettingsContainer::class)->clearCache();

    return app(PublicContentSettings::class);
}

/**
 * @return array<string, mixed>
 */
function f12State(): array
{
    return [
        'saved_events' => test()->savedEvents,
        'snapshot_jobs_processed' => test()->jobsProcessed,
        'backups' => SettingsBackupVersion::query()->orderBy('id')->get()
            ->map(fn (SettingsBackupVersion $backup): array => [
                'id' => $backup->getKey(),
                'source' => $backup->source->value,
                'rows' => $backup->snapshots()
                    ->selectRaw('kind, status, count(*) as c')
                    ->groupBy('kind', 'status')
                    ->orderBy('kind')
                    ->get()
                    ->map(fn (SettingsBackupSnapshot $row): string => "{$row->kind}:{$row->status}={$row->c}")
                    ->values()
                    ->all(),
                'rows_total' => $backup->snapshots()->count(),
            ])->values()->all(),
        'snapshot_rows_total' => SettingsBackupSnapshot::query()->count(),
        'snapshot_rows_done' => SettingsBackupSnapshot::query()->where('status', SettingsBackupSnapshot::STATUS_DONE)->count(),
    ];
}

/**
 * @return array{thumbnail_targets: int, full_targets: int}
 */
function f12ManifestCounts(): array
{
    $manifest = app(SettingsBackupSnapshotManifest::class);

    return [
        'thumbnail_targets' => count($manifest->thumbnailTargets()),
        'full_targets' => count($manifest->fullTargets()),
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function f12Package(array $payload): PublicSettingsPackage
{
    return PublicSettingsPackage::fromArray([
        'schema_version' => PublicSettingsPackage::SCHEMA_VERSION,
        'generated_at' => now()->toIso8601String(),
        'app_version' => app()->version(),
        'settings_group' => PublicContentSettings::group(),
        'settings_migration_watermark' => app(PublicFrontConfigCache::class)->settingsMigrationWatermark(),
        'payload' => $payload,
        'checksum' => PublicSettingsPackage::payloadChecksum($payload),
    ]);
}

function f12ContentFixtures(): void
{
    $author = Author::factory()->create([
        'name' => 'F12 Author',
        'slug' => 'f12-author',
    ]);
    $contentGroup = ContentGroup::factory()
        ->published()
        ->create([
            'title' => 'F12 Podcast',
            'slug' => 'f12-podcast',
            'homepage_order' => 1,
        ]);
    $contentItem = ContentItem::factory()
        ->for($contentGroup)
        ->published()
        ->withTranscription([
            'title' => 'F12 Transcript',
            'transcript_markdown' => 'Visible public transcript.',
        ])
        ->create([
            'title' => 'F12 Episode',
            'slug' => 'f12-episode',
            'is_pinned' => true,
            'pinned_at' => now()->subMinute(),
            'pin_order' => 1,
        ]);

    $contentItem->transcriptions()->firstOrFail()->syncTranscribers([$author]);
}

function f12NodeSpawnFilter(): Closure
{
    return function ($process): bool {
        $command = $process->command;
        $command = is_array($command) ? implode(' ', $command) : (string) $command;

        return str_contains($command, 'settings-snapshots.mjs');
    };
}

it('A: one explicit save costs one system backup, one job, thumbnail-only spawns', function (): void {
    $settings = f12FreshSettings();
    $settings->homepage_item_limit = 41;
    $settings->save();

    $state = f12State();
    $manifest = f12ManifestCounts();
    dump(['scenario' => 'A: explicit save (bare DB)', 'manifest' => $manifest, ...$state]);

    expect($state['saved_events'])->toBe(['PublicContentSettings' => 1])
        ->and($state['snapshot_jobs_processed'])->toBe(1)
        ->and(count($state['backups']))->toBe(1)
        ->and($state['backups'][0]['source'])->toBe(SettingsBackupSource::System->value)
        ->and($state['backups'][0]['rows_total'])->toBe($manifest['thumbnail_targets'])
        ->and($state['snapshot_rows_done'])->toBe($state['snapshot_rows_total']);

    // Was 2 (one spawn per thumbnail row); fix 1 renders the system backup's
    // 2 rows through one batched spawn.
    Process::assertRanTimes(f12NodeSpawnFilter(), 1);
});

it('B: one import on a bare DB fires ONE batched save but pays two backups and thumbs+full spawns', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 66;
    $payload['transcription_policy'] = [
        ...$payload['transcription_policy'],
        'public_mode' => 'all_published',
        'count_mode' => 'all_published',
        'show_multiple_transcriptions_on_item_page' => true,
    ];

    $startedAt = hrtime(true);
    $report = app(SettingsBackupManager::class)->import(f12Package($payload), [
        'homepage_item_limit',
        'transcription_policy.public_mode',
        'transcription_policy.count_mode',
        'transcription_policy.show_multiple_transcriptions_on_item_page',
    ], $superAdmin);
    $importMs = (hrtime(true) - $startedAt) / 1e6;

    $state = f12State();
    $manifest = f12ManifestCounts();
    dump([
        'scenario' => 'B: single import, 4 selected paths (bare DB)',
        'manifest' => $manifest,
        'applied_paths' => $report->appliedPaths(),
        'import_ms_under_process_fake' => round($importMs, 1),
        ...$state,
    ]);

    $expectedBeforeImportRows = $manifest['thumbnail_targets'] + ($manifest['full_targets'] * 2 * 1);

    expect($state['saved_events']['PublicContentSettings'] ?? 0)->toBe(1)
        ->and($report->appliedPaths())->not->toBe([])
        ->and($state['snapshot_jobs_processed'])->toBe(2)
        ->and(collect($state['backups'])->pluck('source')->all())
        ->toBe([SettingsBackupSource::BeforeImport->value, SettingsBackupSource::System->value])
        ->and($state['backups'][0]['rows_total'])->toBe($expectedBeforeImportRows)
        ->and($state['backups'][1]['rows_total'])->toBe($manifest['thumbnail_targets'])
        ->and($state['snapshot_rows_done'])->toBe($state['snapshot_rows_total']);

    // Was 12 (one spawn per row: 10 before_import + 2 system); fix 1 batches
    // each backup into one spawn — one per backup, two per import.
    Process::assertRanTimes(f12NodeSpawnFilter(), 2);
});

it('C: the same single import on a content-rich DB fans out to every full target', function (): void {
    f12ContentFixtures();
    setTestTranscriptionMode(TranscriptionMode::Multi);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 66;
    $payload['transcription_policy'] = [
        ...$payload['transcription_policy'],
        'public_mode' => 'all_published',
        'count_mode' => 'all_published',
        'show_multiple_transcriptions_on_item_page' => true,
    ];

    $report = app(SettingsBackupManager::class)->import(f12Package($payload), [
        'homepage_item_limit',
        'transcription_policy.public_mode',
        'transcription_policy.count_mode',
        'transcription_policy.show_multiple_transcriptions_on_item_page',
    ], $superAdmin);

    $state = f12State();
    $manifest = f12ManifestCounts();
    dump(['scenario' => 'C: single import (content-rich DB)', 'manifest' => $manifest, 'applied_paths' => $report->appliedPaths(), ...$state]);

    $expectedBeforeImportRows = $manifest['thumbnail_targets'] + ($manifest['full_targets'] * 2 * 1);

    expect($manifest['full_targets'])->toBe(7)
        ->and($state['saved_events']['PublicContentSettings'] ?? 0)->toBe(1)
        ->and($state['snapshot_jobs_processed'])->toBe(2)
        ->and($state['backups'][0]['rows_total'])->toBe($expectedBeforeImportRows)
        ->and($state['backups'][1]['rows_total'])->toBe($manifest['thumbnail_targets'])
        ->and($state['snapshot_rows_done'])->toBe($state['snapshot_rows_total']);

    // Was 18 (16 before_import rows + 2 system rows, one spawn each); fix 1
    // makes the content-rich import cost the same two spawns as the bare one —
    // the 7-target fan-out now amortizes inside one node boot.
    Process::assertRanTimes(f12NodeSpawnFilter(), 2);
});

it('D: createManual pays a full set; an ungated restore pays another and dedups its system backup', function (): void {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);

    $settings = f12FreshSettings();
    $settings->homepage_item_limit = 77;
    $settings->save();

    $manual = app(SettingsBackupManager::class)->createManual('F12 manual', $superAdmin);
    $afterManual = f12State();

    $settings = f12FreshSettings();
    $settings->homepage_item_limit = 11;
    $settings->save();
    $afterSecondSave = f12State();

    app(SettingsBackupManager::class)->restore($manual, $superAdmin);

    $state = f12State();
    $manifest = f12ManifestCounts();
    dump([
        'scenario' => 'D: manual backup + ungated (super admin) restore (bare DB)',
        'manifest' => $manifest,
        'after_manual' => $afterManual,
        'after_second_save' => $afterSecondSave,
        'final' => $state,
    ]);

    $fullSet = $manifest['thumbnail_targets'] + ($manifest['full_targets'] * 2 * 1);
    $sources = collect($state['backups'])->pluck('source')->all();

    expect($afterManual['backups'][1]['source'])->toBe(SettingsBackupSource::Manual->value)
        ->and($afterManual['backups'][1]['rows_total'])->toBe($fullSet)
        ->and($afterManual['saved_events'])->toBe(['PublicContentSettings' => 1])
        ->and($state['saved_events'])->toBe(['PublicContentSettings' => 3])
        ->and($sources)->toBe([
            SettingsBackupSource::System->value,
            SettingsBackupSource::Manual->value,
            SettingsBackupSource::System->value,
            SettingsBackupSource::BeforeRestore->value,
        ])
        ->and(collect($state['backups'])->last()['rows_total'])->toBe($fullSet)
        ->and($state['snapshot_rows_done'])->toBe($state['snapshot_rows_total']);

    // Was 24 across the scenario (2+10+2+10 rows, one spawn each); fix 1
    // spends one spawn per backup: system + manual + system + before_restore.
    // Fix 3 does not fire here: the manual backup (payload 77) is the only
    // full-set owner, and the before_restore backup captures payload 11.
    Process::assertRanTimes(f12NodeSpawnFilter(), 4);
});

it('E: a gated (admin) restore additionally creates a post-restore system backup', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);

    $settings = f12FreshSettings();
    $settings->homepage_item_limit = 77;
    $settings->transcription_policy = [
        ...$settings->transcription_policy,
        'public_mode' => 'all_published',
        'count_mode' => 'all_published',
        'show_multiple_transcriptions_on_item_page' => true,
    ];
    $settings->save();
    $backup = app(SettingsBackupManager::class)->createManual('F12 gated restore', $admin);

    $settings = f12FreshSettings();
    $settings->homepage_item_limit = 11;
    $settings->transcription_policy = [
        ...$settings->transcription_policy,
        'public_mode' => 'featured_only',
        'count_mode' => 'featured_only',
        'show_multiple_transcriptions_on_item_page' => false,
    ];
    $settings->save();

    app(SettingsBackupManager::class)->restore($backup, $admin);

    $state = f12State();
    $manifest = f12ManifestCounts();
    dump(['scenario' => 'E: gated (admin) restore (bare DB)', 'manifest' => $manifest, ...$state]);

    $sources = collect($state['backups'])->pluck('source')->all();

    expect($sources)->toBe([
        SettingsBackupSource::System->value,
        SettingsBackupSource::Manual->value,
        SettingsBackupSource::System->value,
        SettingsBackupSource::BeforeRestore->value,
        SettingsBackupSource::System->value,
    ])
        ->and($state['snapshot_rows_done'])->toBe($state['snapshot_rows_total']);

    // Was 26 across the scenario (2+10+2+10+2 rows, one spawn each); fix 1
    // spends one spawn per backup — five backups, five spawns. Fix 3 does not
    // fire: the two full-source backups capture different payloads.
    Process::assertRanTimes(f12NodeSpawnFilter(), 5);
});

it('F: a fully locked no-op import keeps its audit backup row but skips snapshots and the save cycle', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $registry = app(SettingsImportLockSurfaceRegistry::class);
    $maintenancePaths = $registry->sectionUnitPaths('maintenance');
    app(SettingsImportLocks::class)->save($maintenancePaths);
    $afterLockSave = f12State();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['maintenance']['enabled'] = true;
    $payload['maintenance']['retry_after_hours'] = 12;

    $report = app(SettingsBackupManager::class)->import(f12Package($payload), $maintenancePaths, $admin);

    $state = f12State();
    $manifest = f12ManifestCounts();
    dump([
        'scenario' => 'F: fully locked no-op import (bare DB)',
        'manifest' => $manifest,
        'after_lock_save' => $afterLockSave,
        'applied_paths' => $report->appliedPaths(),
        'final' => $state,
    ]);

    $beforeImport = SettingsBackupVersion::query()
        ->where('source', SettingsBackupSource::BeforeImport->value)
        ->latest('id')
        ->firstOrFail();

    /*
     * Fix 2: the merge is computed BEFORE the BeforeImport backup now, so a
     * candidate-empty import keeps its audit row + import_report but
     * schedules no snapshots and skips the save→SettingsSaved→createSystem
     * cycle. Was (diagnosed): saved 2 / before_import with the 10-row full
     * set / 2 jobs — with 12 spawns before fix 1 and 2 after it. Everything
     * still counted below comes from the antecedent lock save alone.
     */
    expect($report->appliedPaths())->toBe([])
        ->and($state['saved_events']['PublicContentSettings'] ?? 0)->toBe(1)
        ->and($state['snapshot_jobs_processed'])->toBe(1)
        ->and(collect($state['backups'])->pluck('source')->all())
        ->toBe([SettingsBackupSource::System->value, SettingsBackupSource::BeforeImport->value])
        ->and(collect($state['backups'])->last()['rows_total'])->toBe(0)
        ->and($state['snapshot_rows_total'])->toBe($manifest['thumbnail_targets'])
        ->and($state['snapshot_rows_done'])->toBe($state['snapshot_rows_total'])
        ->and(filled($beforeImport->import_report))->toBeTrue()
        ->and($report->beforeImportBackupId)->toBe($beforeImport->getKey());

    Process::assertRanTimes(f12NodeSpawnFilter(), 1);
});
