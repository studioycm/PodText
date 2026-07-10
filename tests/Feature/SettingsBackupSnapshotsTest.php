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
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
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

it('processes snapshots through the script contract and isolates per-shot failures', function (): void {
    Storage::fake('local');
    Queue::fake();

    $backup = app(SettingsBackupManager::class)->createManual('Process contract', auth()->user(), ['png'], ['light']);
    $snapshots = $backup->snapshots()->orderBy('id')->limit(2)->get();
    $contracts = [];
    $calls = 0;

    Process::fake(function ($process) use (&$contracts, &$calls) {
        $calls++;
        $contracts[] = json_decode(file_get_contents($process->command[2]), true, flags: JSON_THROW_ON_ERROR);

        return Process::result('', $calls === 1 ? '' : 'boom', $calls === 1 ? 0 : 1);
    });

    (new SettingsBackupSnapshotJob($backup->getKey(), $snapshots->modelKeys()))
        ->handle(app(SettingsBackupSnapshotManager::class));

    Process::assertRanTimes(
        fn ($process): bool => is_array($process->command)
            && $process->command[0] === 'node'
            && $process->command[1] === 'scripts/settings-snapshots.mjs'
            && str_ends_with($process->command[2], '.json'),
        2,
    );

    $firstSnapshot = $snapshots->first()->refresh();
    $secondSnapshot = $snapshots->last()->refresh();
    $firstContract = $contracts[0]['targets'][0];

    expect($firstSnapshot->status)->toBe(SettingsBackupSnapshot::STATUS_DONE)
        ->and($secondSnapshot->status)->toBe(SettingsBackupSnapshot::STATUS_FAILED)
        ->and($secondSnapshot->error)->toContain('boom')
        ->and($backup->refresh()->exists)->toBeTrue()
        ->and($firstContract)->toHaveKeys(['url', 'screen_key', 'theme', 'formats', 'mode', 'max_width', 'device_scale_factor', 'viewport', 'fallback_viewport', 'outputs'])
        ->and($firstContract['viewport']['width'])->toBe(1440)
        ->and($firstContract['device_scale_factor'])->toBeGreaterThan(0)
        ->and($firstContract['device_scale_factor'])->toBeLessThan(1)
        ->and($firstContract['fallback_viewport']['width'])->toBe($firstContract['max_width'])
        ->and($firstContract['outputs'])->toHaveKey(SettingsBackupSnapshot::FORMAT_PNG)
        ->and(base_path('scripts/settings-snapshots.mjs'))->toBeFile();
});

it('renders the table image column and snapshot gallery controls', function (): void {
    Storage::fake('local');

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
        ->assertMountedActionModalSee(__('admin.actions.download_all_snapshots'));
});

it('removes snapshot files on explicit delete and retention prune while preserving non-system backups', function (): void {
    Storage::fake('local');
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
    Storage::fake('local');
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
