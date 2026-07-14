<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Forms\Components\IconSelect;
use App\Filament\Forms\Components\PublicationStatusSelect;
use App\Livewire\Admin\SettingsImportLocksManager;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Settings\SettingsSp3aMeasurementFixture;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsImportLockSurfaceRegistry;
use App\Support\SettingsLifecycle\SettingsLifecycleGroups;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function clearSp3aSettingsState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(AdminUxSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function sp3aPublicSettings(): PublicContentSettings
{
    clearSp3aSettingsState();

    return app(PublicContentSettings::class);
}

function setSp3aMode(TranscriptionMode $mode): void
{
    clearSp3aSettingsState();
    $settings = app(AdminUxSettings::class);
    $settings->transcription_mode = $mode->value;
    $settings->save();
    clearSp3aSettingsState();
}

it('keeps the committed measurement payload deterministic and near 37 KB', function (): void {
    $fixture = app(SettingsSp3aMeasurementFixture::class);
    $payload = $fixture->payload();

    expect($payload['card_templates'])->toHaveCount(9)
        ->and(collect($payload['card_templates'])->pluck('parts')->flatten(1))->toHaveCount(54)
        ->and($fixture->bytes())->toBeBetween(37_000, 39_000)
        ->and($fixture->payload())->toBe($payload);
});

it('memoizes lifecycle derivation by group and payload without changing unit bytes', function (): void {
    $schema = app(SettingsLifecycleSchema::class);
    $payload = $schema->payloadForGroup();
    $firstJson = json_encode($schema->units($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $secondJson = json_encode($schema->units($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $freshJson = json_encode((new SettingsLifecycleSchema(app(SettingsLifecycleGroups::class)))->units($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect(hash('sha256', $firstJson))->toBe('61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8')
        ->and($secondJson)->toBe($firstJson)
        ->and($freshJson)->toBe($firstJson)
        ->and($schema->metrics())->toMatchArray([
            'derivations' => 1,
            'duplicate_loads' => 0,
            'group_payload_loads' => 1,
        ]);
});

it('separates lifecycle memo entries for different payloads and application scopes', function (): void {
    $schema = app(SettingsLifecycleSchema::class);
    $current = $schema->payloadForGroup();
    $imported = $current;
    $imported['route_labels'][] = ['route_key' => 'home', 'label' => 'Imported home'];

    expect($schema->units($current))->not->toBe($schema->units($imported))
        ->and($schema->metrics()['derivations'])->toBe(2);

    app()->forgetScopedInstances();
    $nextRequestSchema = app(SettingsLifecycleSchema::class);

    expect($nextRequestSchema)->not->toBe($schema)
        ->and($nextRequestSchema->metrics()['derivations'])->toBe(0);
});

it('maps visible lock surfaces onto unchanged units and reports retired locks', function (): void {
    $registry = app(SettingsImportLockSurfaceRegistry::class);
    $maintenanceUnits = $registry->sectionUnitPaths('maintenance');

    expect($maintenanceUnits)->toContain('maintenance.enabled', 'maintenance.raw_html_override')
        ->and($registry->importantFieldUnitPath('maintenance.enabled'))->toBe('maintenance.enabled')
        ->and($registry->importantFieldUnitPath('homepage_item_limit'))->toBeNull()
        ->and($registry->retiredLockedPaths(['item_page.dates']))->toBe(['item_page.dates']);

    app(SettingsImportLocks::class)->save(['item_page.dates']);

    expect(app(SettingsImportLocks::class)->lockedPaths())->toBe(['item_page.dates']);

    Livewire::test(SettingsImportLocksManager::class)
        ->assertSee(__('admin.settings_import_locks.retired_report', ['count' => 1]));
});

it('enforces a section surface lock for every covered import unit', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $registry = app(SettingsImportLockSurfaceRegistry::class);
    $maintenancePaths = $registry->sectionUnitPaths('maintenance');
    app(SettingsImportLocks::class)->save($maintenancePaths);

    $settings = sp3aPublicSettings();
    $settings->maintenance = [
        ...$settings->maintenance,
        'enabled' => false,
        'retry_after_hours' => 24,
    ];
    $settings->save();
    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['maintenance']['enabled'] = true;
    $payload['maintenance']['retry_after_hours'] = 12;
    $package = PublicSettingsPackage::fromArray([
        'schema_version' => PublicSettingsPackage::SCHEMA_VERSION,
        'generated_at' => now()->toIso8601String(),
        'app_version' => app()->version(),
        'settings_group' => PublicContentSettings::group(),
        'settings_migration_watermark' => app(PublicFrontConfigCache::class)->settingsMigrationWatermark(),
        'payload' => $payload,
        'checksum' => PublicSettingsPackage::payloadChecksum($payload),
    ]);

    $report = app(SettingsBackupManager::class)->import($package, $maintenancePaths, $admin);

    expect($report->appliedPaths())->toBe([])
        ->and(sp3aPublicSettings()->maintenance)->toMatchArray([
            'enabled' => false,
            'retry_after_hours' => 24,
        ]);
});

it('preserves gated settings for admin restore while applying ordinary values', function (): void {
    setSp3aMode(TranscriptionMode::Multi);
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $settings = sp3aPublicSettings();
    $settings->homepage_item_limit = 77;
    $settings->transcription_policy = [
        ...$settings->transcription_policy,
        'public_mode' => 'all_published',
        'count_mode' => 'all_published',
        'show_multiple_transcriptions_on_item_page' => true,
    ];
    $settings->save();
    $backup = app(SettingsBackupManager::class)->createManual('SP3A forged restore', $admin);

    $settings = sp3aPublicSettings();
    $settings->homepage_item_limit = 11;
    $settings->transcription_policy = [
        ...$settings->transcription_policy,
        'public_mode' => 'featured_only',
        'count_mode' => 'featured_only',
        'show_multiple_transcriptions_on_item_page' => false,
    ];
    $settings->save();

    app(SettingsBackupManager::class)->restore($backup, $admin);
    $restored = sp3aPublicSettings();

    expect($restored->homepage_item_limit)->toBe(77)
        ->and($restored->transcription_policy)->toMatchArray([
            'public_mode' => 'featured_only',
            'count_mode' => 'featured_only',
            'show_multiple_transcriptions_on_item_page' => false,
        ]);
});

it('allows a super admin in multi mode to import the complete gated payload', function (): void {
    setSp3aMode(TranscriptionMode::Multi);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 66;
    $payload['transcription_policy'] = [
        ...$payload['transcription_policy'],
        'public_mode' => 'all_published',
        'count_mode' => 'all_published',
        'show_multiple_transcriptions_on_item_page' => true,
    ];
    $package = PublicSettingsPackage::fromArray([
        'schema_version' => PublicSettingsPackage::SCHEMA_VERSION,
        'generated_at' => now()->toIso8601String(),
        'app_version' => app()->version(),
        'settings_group' => PublicContentSettings::group(),
        'settings_migration_watermark' => app(PublicFrontConfigCache::class)->settingsMigrationWatermark(),
        'payload' => $payload,
        'checksum' => PublicSettingsPackage::payloadChecksum($payload),
    ]);

    app(SettingsBackupManager::class)->import($package, [
        'homepage_item_limit',
        'transcription_policy.public_mode',
        'transcription_policy.count_mode',
        'transcription_policy.show_multiple_transcriptions_on_item_page',
    ], $superAdmin);

    $settings = sp3aPublicSettings();

    expect($settings->homepage_item_limit)->toBe(66)
        ->and($settings->transcription_policy)->toMatchArray([
            'public_mode' => 'all_published',
            'count_mode' => 'all_published',
            'show_multiple_transcriptions_on_item_page' => true,
        ]);
});

it('preloads bounded selects, keeps icon search async, and memoizes template options', function (): void {
    $bounded = PublicationStatusSelect::make();
    $async = IconSelect::make('icon');

    expect($bounded->isPreloaded())->toBeTrue()
        ->and($bounded->getOptions())->not->toBeEmpty()
        ->and($async->isPreloaded())->toBeFalse()
        ->and($async->getOptionsLimit())->toBe(50)
        ->and($async->getSearchResults('home'))->not->toBeEmpty();

    $context = Mockery::mock(PublicFrontRenderContext::class);
    $context->shouldReceive('cardTemplates')->once()->andReturn([]);
    $resolver = new PublicFrontCardTemplateResolver($context);

    expect($resolver->optionsForFamily('content_item'))->not->toBeEmpty()
        ->and($resolver->optionsForFamily('content_item'))->not->toBeEmpty();
});
