<?php

use App\Enums\SettingsBackupSource;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Jobs\SettingsBackupSnapshotJob;
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
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManager;
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManifest;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config([
        'app.url' => 'https://podtext.test',
        'settings.cache.enabled' => true,
        'settings-backups.retention' => 25,
    ]);

    Cache::flush();
    Process::fake();
    Storage::fake('local');
    clearStep10S2VSettingsState();

    $this->actingAs(User::factory()->create());
});

function clearStep10S2VSettingsState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function step10S2VSettings(): PublicContentSettings
{
    clearStep10S2VSettingsState();

    return app(PublicContentSettings::class);
}

function createStep10S2VPublicFixtures(): void
{
    $author = Author::factory()->create([
        'name' => 'Snapshot Author',
        'slug' => 'snapshot-author',
    ]);
    $contentGroup = ContentGroup::factory()
        ->published()
        ->create([
            'title' => 'Snapshot Podcast',
            'slug' => 'snapshot-podcast',
            'homepage_order' => 1,
        ]);
    $contentItem = ContentItem::factory()
        ->for($contentGroup)
        ->published()
        ->withTranscription([
            'title' => 'Snapshot Transcript',
            'transcript_markdown' => 'Visible public transcript.',
        ])
        ->create([
            'title' => 'Snapshot Episode',
            'slug' => 'snapshot-episode',
            'is_pinned' => true,
            'pinned_at' => now()->subMinute(),
            'pin_order' => 1,
        ]);

    $contentItem->transcriptions()->firstOrFail()->syncTranscribers([$author]);
}

function createStep10S2VBackup(SettingsBackupSource $source = SettingsBackupSource::Manual, ?string $label = null): SettingsBackupVersion
{
    $package = PublicSettingsPackage::fromCurrentSettings();

    return SettingsBackupVersion::query()->create([
        'scope' => $package->settingsGroup(),
        'label' => $label,
        'payload_json' => $package->toJson(),
        'checksum' => $package->checksum(),
        'payload_hash' => $package->payloadHash(),
        'source' => $source,
        'created_by_user_id' => auth()->id(),
    ]);
}

function createStep10S2VSnapshot(
    SettingsBackupVersion $backup,
    string $screenKey = 'home',
    string $theme = 'light',
    string $kind = SettingsBackupSnapshot::KIND_THUMBNAIL,
    string $format = SettingsBackupSnapshot::FORMAT_PNG,
    string $status = SettingsBackupSnapshot::STATUS_DONE,
): SettingsBackupSnapshot {
    $path = "settings-backups/{$backup->getKey()}/{$kind}/{$screenKey}-{$theme}-desktop-1440.{$format}";

    if ($status === SettingsBackupSnapshot::STATUS_DONE) {
        Storage::disk('local')->put($path, "snapshot {$screenKey}");
    }

    return SettingsBackupSnapshot::query()->create([
        'backup_id' => $backup->getKey(),
        'screen_key' => $screenKey,
        'theme' => $theme,
        'viewport' => SettingsBackupSnapshot::VIEWPORT_DESKTOP,
        'kind' => $kind,
        'format' => $format,
        'resolved_url' => "https://podtext.test/{$screenKey}",
        'path' => $status === SettingsBackupSnapshot::STATUS_DONE ? $path : null,
        'status' => $status,
        'error' => $status === SettingsBackupSnapshot::STATUS_FAILED ? 'Browser failed.' : null,
    ]);
}

it('creates the snapshots table and keeps target rows unique', function (): void {
    $backup = createStep10S2VBackup(label: 'Unique target');

    expect(Schema::hasTable('settings_backup_snapshots'))->toBeTrue()
        ->and(Schema::hasColumns('settings_backup_snapshots', [
            'backup_id',
            'screen_key',
            'theme',
            'viewport',
            'kind',
            'format',
            'resolved_url',
            'path',
            'status',
        ]))->toBeTrue();

    createStep10S2VSnapshot($backup);

    expect(fn () => SettingsBackupSnapshot::query()->create([
        'backup_id' => $backup->getKey(),
        'screen_key' => 'home',
        'theme' => 'light',
        'viewport' => SettingsBackupSnapshot::VIEWPORT_DESKTOP,
        'kind' => SettingsBackupSnapshot::KIND_THUMBNAIL,
        'format' => SettingsBackupSnapshot::FORMAT_PNG,
        'resolved_url' => 'https://podtext.test/home',
        'path' => 'settings-backups/duplicate.png',
        'status' => SettingsBackupSnapshot::STATUS_PENDING,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('schedules thumbnail-only snapshots for system backups and full configured snapshots for manual backups', function (): void {
    createStep10S2VPublicFixtures();

    $settings = step10S2VSettings();
    $settings->settings_backups = [
        'thumbnail_max_width' => 600,
        'snapshot_formats' => ['png', 'html'],
        'snapshot_themes' => ['light', 'dark'],
    ];
    $settings->save();

    SettingsBackupVersion::query()->delete();
    Queue::fake();

    $system = app(SettingsBackupManager::class)->createSystem();
    $manual = app(SettingsBackupManager::class)->createManual('Manual visual set', auth()->user(), ['png', 'html'], ['light', 'dark']);
    $manifest = app(SettingsBackupSnapshotManifest::class);
    $fullTargets = $manifest->fullTargets();
    $thumbnailTargets = $manifest->thumbnailTargets();

    expect($system)->toBeInstanceOf(SettingsBackupVersion::class)
        ->and($system->snapshots()->count())->toBe(count($thumbnailTargets))
        ->and($system->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->count())->toBe(0)
        ->and($system->snapshots()->pluck('screen_key')->sort()->values()->all())
        ->toBe(collect($thumbnailTargets)->pluck('screen_key')->sort()->values()->all())
        ->and($system->snapshots()->pluck('format')->unique()->values()->all())
        ->toBe([SettingsBackupSnapshot::FORMAT_PNG])
        ->and($manual->snapshots()->count())->toBe(count($thumbnailTargets) + (count($fullTargets) * 2 * 2))
        ->and($manual->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->pluck('screen_key')->unique()->sort()->values()->all())
        ->toBe(collect($fullTargets)->pluck('screen_key')->sort()->values()->all())
        ->and($manual->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->pluck('status')->unique()->values()->all())
        ->toBe([SettingsBackupSnapshot::STATUS_PENDING]);

    Queue::assertPushed(SettingsBackupSnapshotJob::class, 2);
});

it('creates locks-only system backups without scheduling snapshots', function (): void {
    createStep10S2VPublicFixtures();

    $settings = step10S2VSettings();
    $settings->homepage_item_limit = 17;
    $settings->save();

    SettingsBackupVersion::query()->delete();
    Queue::fake();

    $baseline = app(SettingsBackupManager::class)->createSystem();

    expect($baseline->snapshots()->count())->toBeGreaterThan(0);

    Queue::assertPushed(SettingsBackupSnapshotJob::class, 1);
    Queue::fake();

    app(SettingsImportLocks::class)->save(['homepage_item_limit']);

    $locksOnly = SettingsBackupVersion::query()
        ->whereKeyNot($baseline->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($locksOnly->source)->toBe(SettingsBackupSource::System)
        ->and($locksOnly->snapshots()->count())->toBe(0);

    Queue::assertNothingPushed();
    Queue::fake();

    $settings = step10S2VSettings();
    $settings->homepage_item_limit = 18;
    $settings->save();

    $visualBackup = SettingsBackupVersion::query()
        ->whereKeyNot($locksOnly->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($visualBackup->snapshots()->count())->toBeGreaterThan(0);

    Queue::assertPushed(SettingsBackupSnapshotJob::class, 1);
});

it('queues snapshot jobs after commit and keeps the timeout chain ordered', function (): void {
    config([
        'settings-backups.snapshot_job_timeout' => 1800,
        'horizon.defaults.supervisor-1.timeout' => 1850,
        'queue.connections.redis.retry_after' => 1900,
    ]);

    $job = new SettingsBackupSnapshotJob(123);

    expect($job)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($job->timeout)->toBe(1800)
        ->and($job->timeout)->toBeLessThan(config('horizon.defaults.supervisor-1.timeout'))
        ->and(config('horizon.defaults.supervisor-1.timeout'))->toBeLessThan(config('queue.connections.redis.retry_after'));
});

it('queues restore-created snapshot jobs only after the before-restore backup is committed', function (): void {
    createStep10S2VPublicFixtures();
    Queue::fake();

    $settings = step10S2VSettings();
    $settings->homepage_item_limit = 17;
    $settings->save();

    $backup = app(SettingsBackupManager::class)->createManual('Restore after commit source', auth()->user(), ['png'], ['light']);

    $settings = step10S2VSettings();
    $settings->homepage_item_limit = 23;
    $settings->save();

    app(SettingsBackupManager::class)->restore($backup, auth()->user());

    $beforeRestore = SettingsBackupVersion::query()
        ->where('source', SettingsBackupSource::BeforeRestore->value)
        ->latest('id')
        ->firstOrFail();

    expect($beforeRestore->snapshots()->exists())->toBeTrue();

    Queue::assertPushed(
        SettingsBackupSnapshotJob::class,
        fn (SettingsBackupSnapshotJob $job): bool => $job->backupId === $beforeRestore->getKey(),
    );
});

it('processes the whole backup through one batched spawn and isolates per-target failures', function (): void {
    Queue::fake();

    $backup = app(SettingsBackupManager::class)->createManual('Process contract', auth()->user(), ['png'], ['light']);
    $contracts = [];

    Process::fake(function ($process) use (&$contracts) {
        $payload = json_decode((string) file_get_contents((string) $process->command[2]), true, flags: JSON_THROW_ON_ERROR);
        $contracts[] = $payload;

        $results = collect((array) ($payload['targets'] ?? []))
            ->map(fn (array $target): array => [
                'snapshot_id' => $target['snapshot_id'] ?? null,
                'ok' => ($target['screen_key'] ?? null) !== 'search',
                'error' => ($target['screen_key'] ?? null) === 'search' ? 'boom' : null,
            ])
            ->values()
            ->all();

        file_put_contents((string) ($payload['results_path'] ?? ''), json_encode(['results' => $results], JSON_THROW_ON_ERROR));

        return Process::result('', 'boom', 1);
    });

    (new SettingsBackupSnapshotJob($backup->getKey()))
        ->handle(app(SettingsBackupSnapshotManager::class));

    Process::assertRanTimes(
        fn ($process): bool => is_array($process->command)
            && $process->command[0] === 'node'
            && $process->command[1] === 'scripts/settings-snapshots.mjs'
            && str_ends_with($process->command[2], '.json'),
        1,
    );

    $rows = $backup->snapshots()->orderBy('id')->get();
    $failedRows = $rows->where('status', SettingsBackupSnapshot::STATUS_FAILED);
    $firstContract = $contracts[0]['targets'][0];

    expect($contracts)->toHaveCount(1)
        ->and(count($contracts[0]['targets']))->toBe($rows->count())
        ->and($contracts[0]['results_path'])->toBeString()
        ->and($failedRows)->toHaveCount(1)
        ->and($failedRows->first()->screen_key)->toBe('search')
        ->and($failedRows->first()->error)->toContain('boom')
        ->and($rows->where('status', SettingsBackupSnapshot::STATUS_DONE))->toHaveCount($rows->count() - 1)
        ->and($backup->refresh()->exists)->toBeTrue()
        ->and($firstContract)->toHaveKeys(['snapshot_id', 'url', 'screen_key', 'theme', 'formats', 'mode', 'max_width', 'device_scale_factor', 'viewport', 'fallback_viewport', 'outputs'])
        ->and($firstContract['viewport']['width'])->toBe(1440)
        ->and($firstContract['device_scale_factor'])->toBeGreaterThan(0)
        ->and($firstContract['device_scale_factor'])->toBeLessThan(1)
        ->and($firstContract['fallback_viewport']['width'])->toBe($firstContract['max_width'])
        ->and($firstContract['outputs'])->toHaveKey(SettingsBackupSnapshot::FORMAT_PNG)
        ->and(base_path('scripts/settings-snapshots.mjs'))->toBeFile();
});

it('writes the complete batched job file before the snapshot process spawns', function (): void {
    Queue::fake();

    $backup = app(SettingsBackupManager::class)->createManual('Ordering contract', auth()->user(), ['png'], ['light']);
    $pendingIds = $backup->snapshots()->orderBy('id')->pluck('id')->all();
    $observed = [];

    Process::fake(function ($process) use (&$observed) {
        $jobPath = (string) $process->command[2];
        $raw = is_file($jobPath) ? (string) file_get_contents($jobPath) : null;
        $payload = $raw === null ? null : json_decode($raw, true);
        $observed[] = [
            'existed_at_spawn' => $raw !== null,
            'parsed' => is_array($payload),
            'target_ids' => is_array($payload) ? array_column($payload['targets'] ?? [], 'snapshot_id') : [],
            'results_path' => is_array($payload) ? ($payload['results_path'] ?? null) : null,
        ];

        if (is_array($payload) && isset($payload['results_path'])) {
            $results = collect((array) ($payload['targets'] ?? []))
                ->map(fn (array $target): array => ['snapshot_id' => $target['snapshot_id'] ?? null, 'ok' => true, 'error' => null])
                ->values()
                ->all();

            file_put_contents((string) $payload['results_path'], json_encode(['results' => $results], JSON_THROW_ON_ERROR));
        }

        return Process::result();
    });

    (new SettingsBackupSnapshotJob($backup->getKey()))
        ->handle(app(SettingsBackupSnapshotManager::class));

    /*
     * Write-then-spawn ordering (register 2.6): one-row-per-process used to
     * guarantee this for free; under batching it must hold explicitly — the
     * job file is complete on disk, with every pending row present as a
     * target, at the moment the single process spawns.
     */
    expect($observed)->toHaveCount(1)
        ->and($observed[0]['existed_at_spawn'])->toBeTrue()
        ->and($observed[0]['parsed'])->toBeTrue()
        ->and($observed[0]['target_ids'])->toBe($pendingIds)
        ->and($observed[0]['results_path'])->not->toBeNull()
        ->and($backup->snapshots()->where('status', SettingsBackupSnapshot::STATUS_DONE)->count())->toBe(count($pendingIds));
});

it('scales the batch process timeout per target and caps it under the job timeout', function (): void {
    Queue::fake();
    config([
        'settings-backups.snapshot_process_timeout' => 120,
        'settings-backups.snapshot_job_timeout' => 1800,
    ]);

    fakeSettingsSnapshotProcess();

    $backup = app(SettingsBackupManager::class)->createManual('Timeout scaling', auth()->user(), ['png'], ['light']);
    $rowCount = $backup->snapshots()->count();

    (new SettingsBackupSnapshotJob($backup->getKey()))
        ->handle(app(SettingsBackupSnapshotManager::class));

    Process::assertRan(fn ($process): bool => is_array($process->command)
        && $process->command[1] === 'scripts/settings-snapshots.mjs'
        && $process->timeout === 120 * $rowCount);

    $backup->snapshots()->update(['status' => SettingsBackupSnapshot::STATUS_PENDING]);
    config(['settings-backups.snapshot_process_timeout' => 400]);

    (new SettingsBackupSnapshotJob($backup->getKey()))
        ->handle(app(SettingsBackupSnapshotManager::class));

    /*
     * 400s × 6 targets would outlive the 1800s job timeout; the cap keeps the
     * spawn's death inside the job so per-target results can still be mapped.
     */
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && $process->command[1] === 'scripts/settings-snapshots.mjs'
        && $process->timeout === 1740);
});

it('borrows the newest identical sibling full set instead of rescheduling one', function (): void {
    fakeSettingsSnapshotProcess();

    $first = app(SettingsBackupManager::class)->createManual('Set owner P1', auth()->user(), ['png'], ['light']);

    $settings = step10S2VSettings();
    $settings->homepage_item_limit = 55;
    $settings->save();

    $second = app(SettingsBackupManager::class)->createManual('Set owner P2', auth()->user(), ['png'], ['light']);
    $third = app(SettingsBackupManager::class)->createManual('Borrower P2', auth()->user(), ['png'], ['light']);

    expect($first->refresh()->full_snapshot_source_backup_id)->toBeNull()
        ->and($first->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->where('status', SettingsBackupSnapshot::STATUS_DONE)->count())->toBe(4)
        ->and($second->refresh()->full_snapshot_source_backup_id)->toBeNull()
        ->and($second->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->count())->toBe(4)
        ->and($third->refresh()->full_snapshot_source_backup_id)->toBe($second->getKey())
        ->and($third->snapshots()->count())->toBe(2)
        ->and($third->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->count())->toBe(0);
});

it('renders a fresh full set when the identical sibling set is incomplete', function (): void {
    fakeSettingsSnapshotProcess(['search']);

    $first = app(SettingsBackupManager::class)->createManual('Incomplete owner', auth()->user(), ['png'], ['light']);
    $second = app(SettingsBackupManager::class)->createManual('Not a borrower', auth()->user(), ['png'], ['light']);

    expect($first->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->where('status', SettingsBackupSnapshot::STATUS_DONE)->count())->toBe(3)
        ->and($second->refresh()->full_snapshot_source_backup_id)->toBeNull()
        ->and($second->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->count())->toBe(4);
});

it('only borrows a sibling set that covers every requested theme and format combination', function (): void {
    fakeSettingsSnapshotProcess();

    $lightOnly = app(SettingsBackupManager::class)->createManual('Light-only owner', auth()->user(), ['png'], ['light']);
    $bothThemes = app(SettingsBackupManager::class)->createManual('Both-theme owner', auth()->user(), ['png'], ['light', 'dark']);
    $lightBorrower = app(SettingsBackupManager::class)->createManual('Light borrower', auth()->user(), ['png'], ['light']);

    expect($bothThemes->refresh()->full_snapshot_source_backup_id)->toBeNull()
        ->and($bothThemes->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->count())->toBe(8)
        ->and($lightBorrower->refresh()->full_snapshot_source_backup_id)->toBe($bothThemes->getKey())
        ->and($lightBorrower->snapshots()->where('kind', SettingsBackupSnapshot::KIND_FULL)->count())->toBe(0)
        ->and($lightOnly->refresh()->full_snapshot_source_backup_id)->toBeNull();
});

it('falls back to the source backup full set in the gallery and zip for deduped backups', function (): void {
    $owner = createStep10S2VBackup(label: 'Full set owner');
    createStep10S2VSnapshot($owner, kind: SettingsBackupSnapshot::KIND_FULL);
    $deduped = createStep10S2VBackup(label: 'Borrower');
    $deduped->forceFill(['full_snapshot_source_backup_id' => $owner->getKey()])->save();
    createStep10S2VSnapshot($deduped, status: SettingsBackupSnapshot::STATUS_PENDING);

    Livewire::test(ListSettingsBackups::class)
        ->mountAction(TestAction::make('snapshots')->table($deduped))
        ->assertMountedActionModalSee('data-kind="full"', false)
        ->assertMountedActionModalSee(__('admin.messages.settings_backup_snapshot_borrowed', ['id' => $owner->getKey()]))
        // Borrowed rows must not offer retry — recapturing would overwrite the
        // OWNER's artifact from another backup's gallery.
        ->assertDontSee('data-test="settings-backup-snapshot-retry"', false);

    $zipResponse = $this->get(route('admin.settings-backups.snapshots-zip', $deduped));
    $zipResponse->assertOk();

    $zip = new ZipArchive;
    $zip->open($zipResponse->baseResponse->getFile()->getPathname());

    expect($zip->locateName('home/light/full-desktop-1440.png'))->not->toBeFalse();
    $zip->close();

    // Deleting the owner degrades the borrower gracefully: pointer nulled,
    // nothing left to zip (its own thumbnail never finished).
    app(SettingsBackupSnapshotManager::class)->deleteBackup($owner);

    expect($deduped->refresh()->full_snapshot_source_backup_id)->toBeNull();

    $this->get(route('admin.settings-backups.snapshots-zip', $deduped))->assertNotFound();
});

it('renders the table image column and snapshot gallery controls', function (): void {
    $backup = createStep10S2VBackup(label: 'Gallery backup');
    createStep10S2VSnapshot($backup);
    createStep10S2VSnapshot($backup, screenKey: 'episode', theme: 'dark', kind: SettingsBackupSnapshot::KIND_FULL);
    createStep10S2VSnapshot($backup, screenKey: 'search', status: SettingsBackupSnapshot::STATUS_FAILED);

    Livewire::test(ListSettingsBackups::class)
        ->assertOk()
        ->assertTableColumnExists('home_thumbnail')
        ->assertActionVisible(TestAction::make('snapshots')->table($backup))
        ->mountAction(TestAction::make('snapshots')->table($backup))
        ->assertMountedActionModalSee('data-test="settings-backup-snapshots-gallery"', false)
        ->assertMountedActionModalSee('data-test="settings-backup-snapshot-screen-tab"', false)
        ->assertMountedActionModalSee('data-test="settings-backup-snapshot-theme"', false)
        ->assertMountedActionModalSee('data-test="settings-backup-snapshot-scroll-container"', false)
        ->assertMountedActionModalSee('data-test="settings-backup-snapshot-retry"', false)
        ->assertMountedActionModalSee(__('admin.actions.recapture_snapshot'))
        ->assertMountedActionModalSee(__('admin.actions.download_all_snapshots'));
});

it('removes snapshot files on explicit delete and retention prune while preserving non-system backups', function (): void {
    config(['settings-backups.retention' => 1]);

    $singleDeleteBackup = createStep10S2VBackup(label: 'Delete with files');
    createStep10S2VSnapshot($singleDeleteBackup);

    app(SettingsBackupSnapshotManager::class)->deleteBackup($singleDeleteBackup);

    Storage::disk('local')->assertMissing("settings-backups/{$singleDeleteBackup->getKey()}");
    expect(SettingsBackupVersion::query()->whereKey($singleDeleteBackup->getKey())->exists())->toBeFalse();

    $manual = createStep10S2VBackup(SettingsBackupSource::Manual, 'Manual keeper');
    createStep10S2VSnapshot($manual);
    $oldSystem = createStep10S2VBackup(SettingsBackupSource::System, 'Old system');
    createStep10S2VSnapshot($oldSystem);
    $newSystem = createStep10S2VBackup(SettingsBackupSource::System, 'New system');
    createStep10S2VSnapshot($newSystem);

    app(SettingsBackupManager::class)->prune(PublicContentSettings::group());

    expect(SettingsBackupVersion::query()->whereKey($oldSystem->getKey())->exists())->toBeFalse()
        ->and(SettingsBackupVersion::query()->whereKey($newSystem->getKey())->exists())->toBeTrue()
        ->and(SettingsBackupVersion::query()->whereKey($manual->getKey())->exists())->toBeTrue();

    Storage::disk('local')->assertMissing("settings-backups/{$oldSystem->getKey()}");
    Storage::disk('local')->assertExists("settings-backups/{$newSystem->getKey()}/thumbnail/home-light-desktop-1440.png");
    Storage::disk('local')->assertExists("settings-backups/{$manual->getKey()}/thumbnail/home-light-desktop-1440.png");
});

it('does not delete pruned snapshot files when the surrounding transaction rolls back', function (): void {
    config(['settings-backups.retention' => 1]);

    $oldSystem = createStep10S2VBackup(SettingsBackupSource::System, 'Old rollback system');
    createStep10S2VSnapshot($oldSystem);
    $newSystem = createStep10S2VBackup(SettingsBackupSource::System, 'New rollback system');
    createStep10S2VSnapshot($newSystem);

    try {
        DB::transaction(function (): void {
            app(SettingsBackupManager::class)->prune(PublicContentSettings::group());

            throw new RuntimeException('Rollback prune.');
        });
    } catch (RuntimeException) {
        // Expected rollback path.
    }

    expect(SettingsBackupVersion::query()->whereKey($oldSystem->getKey())->exists())->toBeTrue()
        ->and(SettingsBackupVersion::query()->whereKey($newSystem->getKey())->exists())->toBeTrue();

    Storage::disk('local')->assertExists("settings-backups/{$oldSystem->getKey()}/thumbnail/home-light-desktop-1440.png");
    Storage::disk('local')->assertExists("settings-backups/{$newSystem->getKey()}/thumbnail/home-light-desktop-1440.png");
});
