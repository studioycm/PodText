<?php

use App\Enums\SettingsBackupSource;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    for ($i = 1; $i <= 5; $i++) {
        $settings = step10S2Settings();
        $settings->homepage_item_limit = 40 + $i;
        $settings->save();
    }

    $backups = SettingsBackupVersion::query()->orderBy('id')->get();

    expect($backups)->toHaveCount(3)
        ->and($backups->pluck('source')->unique()->all())->toBe([SettingsBackupSource::System]);
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
        ->and(SettingsBackupVersion::query()->where('source', SettingsBackupSource::System->value)->count())->toBe(1);
});
