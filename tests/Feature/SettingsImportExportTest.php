<?php

use App\Enums\SettingsBackupSource;
use App\Filament\Pages\ImportPublicSettings;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Livewire\Admin\SettingsImportWizard;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use App\Support\SettingsLifecycle\SettingsLifecycleSelectionState;
use App\Support\SettingsLifecycle\SettingsPackageImportAnalyzer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
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
    Queue::fake();
    clearStep10S1aSettingsState();

    $this->actingAs(User::factory()->create());
});

function clearStep10S1aSettingsState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function step10S1aSettings(): PublicContentSettings
{
    clearStep10S1aSettingsState();

    return app(PublicContentSettings::class);
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function step10S1aPackageArray(array $payload, int $schemaVersion = PublicSettingsPackage::SCHEMA_VERSION, ?string $watermark = null): array
{
    return [
        'schema_version' => $schemaVersion,
        'generated_at' => now()->toIso8601String(),
        'app_version' => app()->version(),
        'settings_group' => PublicContentSettings::group(),
        'settings_migration_watermark' => $watermark ?? app(PublicFrontConfigCache::class)->settingsMigrationWatermark(),
        'payload' => $payload,
        'checksum' => PublicSettingsPackage::payloadChecksum($payload),
    ];
}

/**
 * @param  array<string, mixed>  $package
 */
function step10S1aUploadedPackage(array $package): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'public-settings.json',
        json_encode($package, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

it('derives lifecycle units and keeps the semantic overlay in sync with defaults', function (): void {
    $schema = app(SettingsLifecycleSchema::class);
    $payload = $schema->payloadForGroup();
    $paths = collect($schema->units($payload))->pluck('path');

    expect($schema->managedGroups())->toContain(PublicContentSettings::group())
        ->and($paths)->toContain('homepage_item_limit')
        ->and($paths)->toContain('settings_backups.thumbnail_max_width')
        ->and($paths)->toContain('card_templates');

    foreach ($schema->overlaySemantics() as $semantic => $semanticPaths) {
        foreach ($semanticPaths as $path) {
            expect(data_get($payload, $path))->not->toBe('__missing__', "Missing overlay {$semantic} path [{$path}].");
        }
    }
});

it('exposes export and import actions on settings surfaces', function (): void {
    Livewire::test(PublicContentSettingsPage::class)
        ->assertActionVisible(TestAction::make('exportPublicSettings'))
        ->callAction(TestAction::make('exportPublicSettings'))
        ->assertFileDownloaded();

    Livewire::test(ListSettingsBackups::class)
        ->assertActionVisible(TestAction::make('exportPublicSettings')->table())
        ->assertActionVisible(TestAction::make('importSettings')->table())
        ->callAction(TestAction::make('exportPublicSettings')->table())
        ->assertFileDownloaded();
});

it('imports a package round trip and creates a before-import backup', function (): void {
    $settings = step10S1aSettings();
    $settings->homepage_item_limit = 17;
    $settingsBackups = $settings->settings_backups;
    $settingsBackups['thumbnail_max_width'] = 400;
    $settings->settings_backups = $settingsBackups;
    $settings->save();

    SettingsBackupVersion::query()->delete();

    $package = PublicSettingsPackage::fromCurrentSettings()->toArray();
    $paths = collect(app(SettingsLifecycleSchema::class)->units($package['payload']))
        ->pluck('path')
        ->all();

    $settings = step10S1aSettings();
    $settingsBackups = $settings->settings_backups;
    $settingsBackups['thumbnail_max_width'] = 800;
    $settings->settings_backups = $settingsBackups;
    $settings->homepage_item_limit = 28;
    $settings->save();

    expect(app(PublicFrontConfigReader::class)->group('settings_backups')['thumbnail_max_width'])->toBe(800);

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($package))
        ->call('loadUploadedPackage')
        ->set('selectedPaths', $paths)
        ->call('applyImport')
        ->assertSet('step', 'complete');

    $restored = step10S1aSettings();

    expect($restored->homepage_item_limit)->toBe(17)
        ->and(app(PublicFrontConfigReader::class)->group('settings_backups')['thumbnail_max_width'])->toBe(400)
        ->and(SettingsBackupVersion::query()->where('source', SettingsBackupSource::BeforeImport->value)->count())->toBe(1);
});

it('applies only selected scalar and nested setting units', function (): void {
    $settings = step10S1aSettings();
    $settings->homepage_item_limit = 10;
    $settings->show_latest_section = false;
    $settingsBackups = $settings->settings_backups;
    $settingsBackups['thumbnail_max_width'] = 400;
    $settings->settings_backups = $settingsBackups;
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 22;
    $payload['show_latest_section'] = true;
    $payload['settings_backups']['thumbnail_max_width'] = 800;
    $package = step10S1aPackageArray($payload);

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($package))
        ->call('loadUploadedPackage')
        ->set('selectedPaths', ['homepage_item_limit', 'settings_backups.thumbnail_max_width'])
        ->call('applyImport')
        ->assertSet('step', 'complete');

    $settings = step10S1aSettings();

    expect($settings->homepage_item_limit)->toBe(22)
        ->and($settings->show_latest_section)->toBeFalse()
        ->and($settings->settings_backups['thumbnail_max_width'])->toBe(800);
});

it('computes tri-state group toggle semantics', function (): void {
    $rows = [
        ['group' => 'sample', 'path' => 'sample.one', 'selectable' => true],
        ['group' => 'sample', 'path' => 'sample.two', 'selectable' => true],
        ['group' => 'sample', 'path' => 'sample.locked', 'selectable' => false],
    ];
    $state = app(SettingsLifecycleSelectionState::class);

    expect($state->groupState($rows, [], 'sample'))->toBe('none')
        ->and($state->groupState($rows, ['sample.one'], 'sample'))->toBe('some')
        ->and($state->groupState($rows, ['sample.one', 'sample.two'], 'sample'))->toBe('all')
        ->and($state->toggleGroup($rows, [], 'sample'))->toBe(['sample.one', 'sample.two'])
        ->and($state->toggleGroup($rows, ['sample.one', 'sample.two'], 'sample'))->toBe([]);
});

it('refuses checksum tampering and newer schema versions', function (): void {
    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $tampered = step10S1aPackageArray($payload);
    $tampered['payload']['homepage_item_limit'] = 1234;

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($tampered))
        ->call('loadUploadedPackage')
        ->assertSet('step', 'source')
        ->assertSee(__('admin.messages.settings_backup_checksum_invalid'));

    $newer = step10S1aPackageArray($payload, schemaVersion: PublicSettingsPackage::SCHEMA_VERSION + 1);

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($newer))
        ->call('loadUploadedPackage')
        ->assertSet('step', 'source')
        ->assertSee(__('admin.messages.settings_backup_schema_unsupported'));
});

it('warns for watermark mismatch and missing files without blocking import', function (): void {
    Storage::fake('public');

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['default_images']['global'] = [
        'mode' => 'custom',
        'path' => 'default-images/missing.png',
    ];
    $package = step10S1aPackageArray($payload, watermark: 'old-watermark');

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($package))
        ->call('loadUploadedPackage')
        ->assertSet('step', 'dry-run')
        ->assertSee(__('admin.messages.settings_import_watermark_mismatch'))
        ->assertSee('default-images/missing.png');
});

it('marks scalar type mismatches as non-selectable row errors and never applies them', function (): void {
    $settings = step10S1aSettings();
    $settings->homepage_item_limit = 11;
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 'not-an-integer';
    $package = step10S1aPackageArray($payload);

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($package))
        ->call('loadUploadedPackage')
        ->assertSet('step', 'dry-run')
        ->assertSet('selectedPaths', fn (array $paths): bool => ! in_array('homepage_item_limit', $paths, true))
        ->set('selectedPaths', ['homepage_item_limit'])
        ->call('applyImport')
        ->assertSet('step', 'complete');

    expect(step10S1aSettings()->homepage_item_limit)->toBe(11);
});

it('produces identical dry runs for backup source and uploaded source', function (): void {
    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = $payload['homepage_item_limit'] + 3;
    $package = step10S1aPackageArray($payload);

    $backup = SettingsBackupVersion::query()->create([
        'scope' => PublicContentSettings::group(),
        'label' => 'Dry run source',
        'payload_json' => json_encode($package, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'checksum' => $package['checksum'],
        'payload_hash' => PublicSettingsPackage::payloadChecksum($payload),
        'source' => SettingsBackupSource::Manual,
        'created_by_user_id' => auth()->id(),
    ]);

    $uploaded = Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage($package))
        ->call('loadUploadedPackage')
        ->instance()
        ->dryRunSignature();

    $fromBackup = Livewire::test(SettingsImportWizard::class)
        ->set('selectedBackupId', $backup->getKey())
        ->call('loadBackupPackage')
        ->instance()
        ->dryRunSignature();

    expect($fromBackup)->toBe($uploaded);
});

it('keeps the v1 upgrade pipeline identity path and blocks guest import access', function (): void {
    $package = PublicSettingsPackage::fromCurrentSettings()->toArray();

    expect(PublicSettingsPackage::fromArray($package)->payload())->toBe($package['payload'])
        ->and(app(SettingsPackageImportAnalyzer::class)->analyzeArray($package)->refused())->toBeFalse();

    auth()->logout();

    $this->get(ImportPublicSettings::getUrl())
        ->assertRedirect('/admin/login');
});
