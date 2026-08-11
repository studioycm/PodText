<?php

use App\Enums\SettingsBackupSource;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config([
        'settings.cache.enabled' => true,
        'settings-backups.retention' => 25,
    ]);

    Cache::flush();
    Process::fake();
    Storage::fake('local');
    clearStep10S2SettingsState();

    $this->actingAs(User::factory()->create());
});

function clearStep10S2SettingsState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function step10S2Settings(): PublicContentSettings
{
    clearStep10S2SettingsState();

    return app(PublicContentSettings::class);
}

it('creates manual backups from the admin table and system backups from settings saves', function (): void {
    Livewire::test(ListSettingsBackups::class)
        ->assertOk()
        ->assertActionVisible(TestAction::make('createBackup')->table())
        ->mountAction(TestAction::make('createBackup')->table())
        ->set('mountedActions.0.data.label', 'Manual S2 backup')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $manualBackup = SettingsBackupVersion::query()
        ->where('source', SettingsBackupSource::Manual->value)
        ->firstOrFail();

    expect($manualBackup->label)->toBe('Manual S2 backup')
        ->and($manualBackup->created_by_user_id)->toBe(auth()->id())
        ->and($manualBackup->package()->checksumValid())->toBeTrue()
        ->and($manualBackup->package()->payload())->toHaveKeys(['homepage_item_limit', 'settings_backups']);

    $settings = step10S2Settings();
    $settings->homepage_item_limit = $settings->homepage_item_limit + 1;
    $settings->save();

    expect(SettingsBackupVersion::query()->where('source', SettingsBackupSource::System->value)->count())->toBe(1);
});

it('dedupes identical system backups and prunes by retention', function (): void {
    $settings = step10S2Settings();
    $settings->homepage_item_limit = 31;
    $settings->save();
    $settings->save();

    expect(SettingsBackupVersion::query()->where('source', SettingsBackupSource::System->value)->count())->toBe(1);

    SettingsBackupVersion::query()->delete();
    config(['settings-backups.retention' => 3]);

    app(SettingsBackupManager::class)->createManual('Manual retention keeper', auth()->user());
    app(SettingsBackupManager::class)->create(SettingsBackupSource::BeforeImport, 'Before import retention keeper', auth()->user());
    app(SettingsBackupManager::class)->create(SettingsBackupSource::BeforeRestore, 'Before restore retention keeper', auth()->user());

    for ($i = 1; $i <= 5; $i++) {
        $settings = step10S2Settings();
        $settings->homepage_item_limit = 40 + $i;
        $settings->save();
    }

    $backups = SettingsBackupVersion::query()->orderBy('id')->get();

    expect($backups->where('source', SettingsBackupSource::System)->values())->toHaveCount(3)
        ->and($backups->where('source', SettingsBackupSource::Manual)->values())->toHaveCount(1)
        ->and($backups->where('source', SettingsBackupSource::BeforeImport)->values())->toHaveCount(1)
        ->and($backups->where('source', SettingsBackupSource::BeforeRestore)->values())->toHaveCount(1);
});

it('prunes before-import and before-restore backups past their per-source ceilings', function (): void {
    config([
        'settings-backups.retention_before_import' => 2,
        'settings-backups.retention_before_restore' => 2,
    ]);

    $manager = app(SettingsBackupManager::class);
    $manual = $manager->createManual('Manual keeper', auth()->user());

    /*
     * The generic Process fake writes no results file, so every full row ends
     * FAILED and no backup can borrow — each source's ceiling applies to
     * plain, unborrowed rows here.
     */
    $beforeImports = collect(range(1, 4))
        ->map(fn (int $i) => $manager->create(SettingsBackupSource::BeforeImport, "BI {$i}", auth()->user()));
    $beforeRestores = collect(range(1, 4))
        ->map(fn (int $i) => $manager->create(SettingsBackupSource::BeforeRestore, "BR {$i}", auth()->user()));

    expect(SettingsBackupVersion::query()->where('source', SettingsBackupSource::BeforeImport->value)->pluck('label')->all())
        ->toBe(['BI 3', 'BI 4'])
        ->and(SettingsBackupVersion::query()->where('source', SettingsBackupSource::BeforeRestore->value)->pluck('label')->all())
        ->toBe(['BR 3', 'BR 4'])
        ->and(SettingsBackupVersion::query()->whereKey($manual->getKey())->exists())->toBeTrue();

    Storage::disk('local')->assertMissing("settings-backups/{$beforeImports->first()->getKey()}");
    Storage::disk('local')->assertMissing("settings-backups/{$beforeRestores->first()->getKey()}");
    expect(Storage::disk('local')->directoryExists("settings-backups/{$beforeImports->last()->getKey()}"))->toBeTrue();
});

it('keeps manual backups forever by default and prunes them only when a ceiling is configured', function (): void {
    $manager = app(SettingsBackupManager::class);

    $manuals = collect(range(1, 3))
        ->map(fn (int $i) => $manager->createManual("Manual {$i}", auth()->user()));

    expect(SettingsBackupVersion::query()->where('source', SettingsBackupSource::Manual->value)->count())->toBe(3);

    config(['settings-backups.retention_manual' => 1]);

    $manager->createManual('Manual 4', auth()->user());

    expect(SettingsBackupVersion::query()->where('source', SettingsBackupSource::Manual->value)->pluck('label')->all())
        ->toBe(['Manual 4']);

    Storage::disk('local')->assertMissing("settings-backups/{$manuals->first()->getKey()}");
});

it('shows the retention policy notice on the backups table', function (): void {
    config([
        'settings-backups.retention' => 25,
        'settings-backups.retention_before_import' => 10,
        'settings-backups.retention_before_restore' => 7,
    ]);

    Livewire::test(ListSettingsBackups::class)
        ->assertOk()
        ->assertSee(__('admin.messages.settings_backups_retention_notice_manual_forever', [
            'system' => 25,
            'before_import' => 10,
            'before_restore' => 7,
        ]));

    config(['settings-backups.retention_manual' => 5]);

    Livewire::test(ListSettingsBackups::class)
        ->assertOk()
        ->assertSee(__('admin.messages.settings_backups_retention_notice_manual_capped', [
            'system' => 25,
            'before_import' => 10,
            'before_restore' => 7,
            'manual' => 5,
        ]));
});

it('runs manual backup creation under the public settings write lock', function (): void {
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        new PublicContentSettingsWriteCoordinator(leaseSeconds: 300, waitSeconds: 0),
    );

    $lock = Cache::lock(PublicContentSettingsWriteCoordinator::LOCK_KEY, 300);

    expect($lock->get())->toBeTrue();

    try {
        /*
         * An uncoordinated createManual() would be the one prune() path able
         * to interleave with a coordinated borrow establishment (reviewer
         * finding 3 on the 1.9 design): it must join the write lock instead
         * of proceeding.
         */
        expect(fn () => app(SettingsBackupManager::class)->createManual('Locked out', auth()->user()))
            ->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }

    $backup = app(SettingsBackupManager::class)->createManual('Lock released', auth()->user());

    expect($backup)->toBeInstanceOf(SettingsBackupVersion::class);
});

it('downloads a checksum-valid package and protects the backup resource from guests', function (): void {
    $backup = app(SettingsBackupManager::class)->createManual('Download package', auth()->user());

    Livewire::test(ListSettingsBackups::class)
        ->assertActionVisible(TestAction::make('download')->table($backup))
        ->assertActionVisible(TestAction::make('compare')->table($backup))
        ->assertActionVisible(TestAction::make('restore')->table($backup))
        ->callAction(TestAction::make('download')->table($backup))
        ->assertFileDownloaded($backup->downloadFilename(), $backup->payload_json);

    $package = PublicSettingsPackage::fromArray(json_decode($backup->payload_json, true, flags: JSON_THROW_ON_ERROR));

    expect($package->checksumValid())->toBeTrue();

    auth()->logout();

    $this->get(SettingsBackupResource::getUrl('index'))
        ->assertRedirect('/admin/login');
});

it('compares scalar and nested settings changes against the current settings', function (): void {
    $backup = app(SettingsBackupManager::class)->createManual('Compare source', auth()->user());

    $settings = step10S2Settings();
    $settingsBackups = $settings->settings_backups;
    $settingsBackups['thumbnail_max_width'] = 400;
    $settings->settings_backups = $settingsBackups;
    $settings->homepage_item_limit = $settings->homepage_item_limit + 5;
    $settings->save();

    $diff = app(SettingsBackupManager::class)->compare($backup);
    $lines = implode("\n", $diff->lines());

    expect($diff->hasChanges())->toBeTrue()
        ->and($lines)->toContain('homepage_item_limit')
        ->and($lines)->toContain('settings_backups.thumbnail_max_width');

    Livewire::test(ListSettingsBackups::class)
        ->mountAction(TestAction::make('compare')->table($backup))
        ->assertMountedActionModalSee('homepage_item_limit')
        ->assertMountedActionModalSee('settings_backups.thumbnail_max_width');
});

it('restores a backup round trip, creates before-restore backup, and invalidates public config cache', function (): void {
    $settings = step10S2Settings();
    $settings->homepage_item_limit = 17;
    $originalBackupSettings = $settings->settings_backups;
    $originalBackupSettings['thumbnail_max_width'] = 800;
    $settings->settings_backups = $originalBackupSettings;
    $settings->save();

    SettingsBackupVersion::query()->delete();

    $backup = app(SettingsBackupManager::class)->createManual('Original settings', auth()->user());

    $settings = step10S2Settings();
    $changedBackupSettings = $settings->settings_backups;
    $changedBackupSettings['thumbnail_max_width'] = 400;
    $settings->settings_backups = $changedBackupSettings;
    $settings->homepage_item_limit = 23;
    $settings->save();

    expect(app(PublicFrontConfigReader::class)->group('settings_backups')['thumbnail_max_width'])->toBe(400);

    app(SettingsBackupManager::class)->restore($backup, auth()->user());

    $restoredSettings = step10S2Settings();

    expect($restoredSettings->homepage_item_limit)->toBe(17)
        ->and(app(PublicFrontConfigReader::class)->group('settings_backups')['thumbnail_max_width'])->toBe(800)
        ->and(SettingsBackupVersion::query()->where('source', SettingsBackupSource::BeforeRestore->value)->count())->toBe(1)
        ->and(SettingsBackupVersion::query()->where('source', SettingsBackupSource::System->value)->count())->toBeGreaterThanOrEqual(1);
});
