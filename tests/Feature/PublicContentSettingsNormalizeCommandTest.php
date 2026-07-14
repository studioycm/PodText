<?php

use App\Enums\SettingsBackupSource;
use App\Models\SettingsBackupVersion;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    fakeSettingsBackupSnapshotQueue();
    clearSp2NormalizeSettingsState();
});

function clearSp2NormalizeSettingsState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function updateSp2NormalizeSetting(string $name, array $payload): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    clearSp2NormalizeSettingsState();
}

it('validates only the selected public front setting groups', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validateGroups([
        'display_defaults' => [
            'layout' => 'rows',
            'raw_classes' => 'p-4',
        ],
        'maintenance' => 'not-an-array',
        'unexpected_group' => [],
    ], ['display_defaults']);

    $paths = collect($result->invalidConfigArray())->pluck('path')->all();

    expect($result->config())->toHaveKey('display_defaults')
        ->and($result->config())->not->toHaveKey('maintenance')
        ->and($result->group('display_defaults'))->toMatchArray([
            'layout' => 'rows',
            'density' => 'comfortable',
            'image_size' => 'medium',
        ])
        ->and($paths)->toContain('display_defaults.raw_classes')
        ->and($paths)->not->toContain('maintenance')
        ->and($paths)->not->toContain('unexpected_group');
});

it('reports normalization changes in dry run without writing settings', function (): void {
    $legacyDisplayDefaults = [
        'layout' => 'rows',
        'density' => 'invalid-density',
        'raw_classes' => 'p-4',
    ];

    updateSp2NormalizeSetting('display_defaults', $legacyDisplayDefaults);

    $exitCode = Artisan::call('settings:normalize-public-content');
    $output = Artisan::output();

    clearSp2NormalizeSettingsState();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Public content JSON settings normalization report.')
        ->and($output)->toContain('display_defaults')
        ->and($output)->toContain('display_defaults.raw_classes')
        ->and($output)->toContain('display_defaults.density')
        ->and($output)->toContain('Dry run only')
        ->and(app(PublicContentSettings::class)->display_defaults)->toBe($legacyDisplayDefaults)
        ->and(SettingsBackupVersion::query()->count())->toBe(0);
});

it('creates a system backup and writes normalized settings when applied', function (): void {
    $legacyDisplayDefaults = [
        'layout' => 'rows',
        'density' => 'invalid-density',
        'raw_classes' => 'p-4',
    ];

    updateSp2NormalizeSetting('display_defaults', $legacyDisplayDefaults);

    $exitCode = Artisan::call('settings:normalize-public-content', ['--apply' => true]);
    $output = Artisan::output();

    clearSp2NormalizeSettingsState();

    $settings = app(PublicContentSettings::class);
    $backups = SettingsBackupVersion::query()->oldest('id')->get();
    $backup = $backups->first(
        fn (SettingsBackupVersion $backup): bool => $backup->package()->payload()['display_defaults'] === $legacyDisplayDefaults,
    );

    expect($backup)->not->toBeNull();

    $backupPayload = $backup->package()->payload();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Created system backup #')
        ->and($output)->toContain('Normalized public content JSON settings saved.')
        ->and($backup->source)->toBe(SettingsBackupSource::System)
        ->and($backupPayload['display_defaults'])->toBe($legacyDisplayDefaults)
        ->and($settings->display_defaults)->toMatchArray([
            'layout' => 'rows',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'image_fit' => 'cover',
            'image_radius' => 'mid_rounded',
            'title_size' => 'base',
            'page_size' => 12,
            'transcription_display' => 'effective_only',
        ])
        ->and($settings->display_defaults)->not->toHaveKey('raw_classes');
});

it('keeps gated transcription policy bytes unchanged during anonymous normalize apply', function (): void {
    updateSp2NormalizeSetting('transcription_policy', [
        'public_mode' => 'forged_mode',
        'count_mode' => 'forged_count',
        'show_multiple_transcriptions_on_item_page' => true,
    ]);
    updateSp2NormalizeSetting('display_defaults', [
        'layout' => 'rows',
        'density' => 'invalid-density',
    ]);

    expect(Artisan::call('settings:normalize-public-content', ['--apply' => true]))->toBe(0);

    clearSp2NormalizeSettingsState();

    expect(app(PublicContentSettings::class)->transcription_policy)->toBe([
        'public_mode' => 'forged_mode',
        'count_mode' => 'forged_count',
        'show_multiple_transcriptions_on_item_page' => true,
    ])
        ->and(app(PublicContentSettings::class)->display_defaults['density'])->toBe('comfortable');
});
