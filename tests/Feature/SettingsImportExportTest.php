<?php

use App\Enums\SettingsBackupSource;
use App\Enums\SettingsImportMode;
use App\Filament\Pages\ImportPublicSettings;
use App\Filament\Pages\ManageSettingsImportLocks;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Livewire\Admin\SettingsImportLocksManager;
use App\Livewire\Admin\SettingsImportWizard;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsImportLocks;
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
    Storage::fake('local');
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
        ->and($paths)->toContain('card_templates.content_item')
        ->and($paths)->toContain('route_labels.home')
        ->and($paths)->not->toContain('import_locks');

    expect(data_get($payload, '__known_bogus_overlay_path__', '__missing__'))->toBe('__missing__');

    foreach ($schema->overlaySemantics() as $semantic => $semanticPaths) {
        foreach ($semanticPaths as $path) {
            $value = data_get($payload, $path, '__missing__');

            if ($value === '__missing__') {
                expect($schema->unitFor($path, $payload))->not->toBeNull("Missing overlay {$semantic} path [{$path}].");

                continue;
            }

            expect($value)->not->toBe('__missing__', "Missing overlay {$semantic} path [{$path}].");
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
        ->assertActionVisible(TestAction::make('manageImportLocks')->table())
        ->callAction(TestAction::make('exportPublicSettings')->table())
        ->assertFileDownloaded();
});

it('renders and saves maintenance settings from the admin form', function (): void {
    Livewire::test(PublicContentSettingsPage::class)
        ->assertSee(__('admin.tabs.public_content_settings.maintenance'))
        ->assertSee(__('admin.helpers.maintenance_warning'))
        ->set('data.maintenance.enabled', true)
        ->set('data.maintenance.retry_after_hours', 12)
        ->set('data.maintenance.title', 'Admin maintenance title')
        ->set('data.maintenance.rich_html', '<p>Admin rich content</p>')
        ->set('data.maintenance.raw_html_override', '<!doctype html><html><body>Admin raw</body></html>')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(step10S1aSettings()->maintenance)->toMatchArray([
        'enabled' => true,
        'retry_after_hours' => 12,
        'title' => 'Admin maintenance title',
        'rich_html' => '<p>Admin rich content</p>',
        'raw_html_override' => '<!doctype html><html><body>Admin raw</body></html>',
    ])
        ->and(step10S1aSettings()->maintenance['rich_html'])->toBeString();
});

it('preserves maintenance html fields during unrelated settings saves', function (): void {
    $richHtml = '<p data-maintenance-preserve="rich">Stored rich maintenance</p>';
    $rawHtml = '<!doctype html><html><body data-maintenance-preserve="raw">Stored raw maintenance</body></html>';

    $settings = step10S1aSettings();
    $settings->maintenance = [
        'enabled' => false,
        'title' => 'Stored maintenance title',
        'rich_html' => $richHtml,
        'raw_html_override' => $rawHtml,
        'retry_after_hours' => 24,
    ];
    $settings->save();

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.homepage_item_limit', 42)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(step10S1aSettings()->homepage_item_limit)->toBe(42)
        ->and(step10S1aSettings()->maintenance['rich_html'])->toBe($richHtml)
        ->and(step10S1aSettings()->maintenance['raw_html_override'])->toBe($rawHtml);
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

it('round trips public form definitions through exported packages and selected imports', function (): void {
    $publicForms = [
        'definitions' => [
            [
                'key' => 'maintenance_contact',
                'name' => 'Maintenance contact',
                'heading' => 'Contact us',
                'description' => 'Plain maintenance contact form.',
                'submit_label' => 'Send',
                'success_message' => 'Received.',
                'enabled' => true,
                'display_mode_default' => 'modal',
                'fields' => [
                    [
                        'key' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'placeholder' => null,
                        'help_text' => null,
                        'required' => true,
                        'options' => [],
                        'validation_semantics' => 'none',
                    ],
                ],
                'settings' => [
                    'rate_limit_attempts' => 3,
                    'rate_limit_decay_seconds' => 600,
                ],
            ],
        ],
    ];

    $settings = step10S1aSettings();
    $settings->public_forms = $publicForms;
    $settings->save();

    $package = PublicSettingsPackage::fromCurrentSettings()->toArray();

    $settings = step10S1aSettings();
    $settings->public_forms = ['definitions' => []];
    $settings->save();

    $report = app(SettingsBackupManager::class)->import(
        PublicSettingsPackage::fromArray($package),
        ['public_forms.definitions'],
        auth()->user(),
    );

    expect($package['payload']['public_forms'])->toBe($publicForms)
        ->and($report->appliedPaths())->toBe(['public_forms.definitions'])
        ->and(step10S1aSettings()->public_forms)->toBe($publicForms);
});

it('round trips maintenance mode settings through exported packages and selected imports', function (): void {
    $maintenance = [
        'enabled' => true,
        'title' => 'תחזוקה זמנית',
        'rich_html' => '<p data-maintenance-marker="mp1">Maintenance export</p>',
        'raw_html_override' => '<!doctype html><html><body>Raw export</body></html>',
        'form_key' => 'request_transcription',
        'form_location' => 'raw_html',
        'form_position' => 'after_content',
        'retry_after_hours' => 12,
    ];

    $settings = step10S1aSettings();
    $settings->maintenance = $maintenance;
    $settings->save();

    $package = PublicSettingsPackage::fromCurrentSettings()->toArray();
    $maintenancePaths = collect(app(SettingsLifecycleSchema::class)->units($package['payload']))
        ->filter(fn ($unit): bool => $unit->section === 'maintenance')
        ->pluck('path')
        ->values()
        ->all();

    $settings = step10S1aSettings();
    $settings->maintenance = [
        'enabled' => false,
        'form_key' => null,
        'form_location' => 'rendered_page',
        'form_position' => 'before_content',
        'title' => null,
        'rich_html' => null,
        'raw_html_override' => null,
        'retry_after_hours' => 24,
    ];
    $settings->save();

    $report = app(SettingsBackupManager::class)->import(
        PublicSettingsPackage::fromArray($package),
        $maintenancePaths,
        auth()->user(),
    );

    expect($package['payload']['maintenance'])->toBe($maintenance)
        ->and($maintenancePaths)->toEqualCanonicalizing([
            'maintenance.enabled',
            'maintenance.form_key',
            'maintenance.form_location',
            'maintenance.form_position',
            'maintenance.raw_html_override',
            'maintenance.retry_after_hours',
            'maintenance.rich_html',
            'maintenance.title',
        ])
        ->and($report->appliedPaths())->toEqualCanonicalizing($maintenancePaths)
        ->and(step10S1aSettings()->maintenance)->toBe($maintenance);
});

it('persists import locks and derives the front-text preset from lockable units', function (): void {
    $schema = app(SettingsLifecycleSchema::class);
    $payload = $schema->payloadForGroup();

    foreach ($schema->overlaySemantics()['front_text'] as $path) {
        expect($schema->unitPathsForSemanticPath($path, $payload))->toHaveCount(1, "Front-text path [{$path}] must map to exactly one lockable unit.");
    }

    Livewire::test(SettingsImportLocksManager::class)
        ->call('lockAllFrontTexts')
        ->assertSet('selectedPaths', fn (array $paths): bool => $paths !== [] && count($paths) === count(array_unique($paths)))
        ->call('saveLocks')
        ->assertSee(__('admin.messages.settings_import_locks_saved', ['count' => count(app(SettingsImportLocks::class)->frontTextLockPaths())]));

    expect(app(SettingsImportLocks::class)->lockedPaths())
        ->toEqualCanonicalizing(app(SettingsImportLocks::class)->frontTextLockPaths());
});

it('keeps sensitive maintenance units selectable but deselected by default', function (): void {
    $settings = step10S1aSettings();
    $settings->maintenance = [
        'enabled' => false,
        'form_key' => null,
        'form_location' => 'rendered_page',
        'form_position' => 'after_content',
        'title' => null,
        'rich_html' => null,
        'raw_html_override' => null,
        'retry_after_hours' => 24,
    ];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['maintenance'] = [
        'enabled' => true,
        'form_key' => 'request_transcription',
        'form_location' => 'raw_html',
        'form_position' => 'after_content',
        'title' => 'Imported sensitive title',
        'rich_html' => '<p>Imported sensitive rich content</p>',
        'raw_html_override' => '<!doctype html><html><body>Imported sensitive raw</body></html>',
        'retry_after_hours' => 12,
    ];

    $analysis = app(SettingsPackageImportAnalyzer::class)->analyze(
        PublicSettingsPackage::fromArray(step10S1aPackageArray($payload)),
    );
    $rows = $analysis->rowsByPath();

    foreach (['maintenance.enabled', 'maintenance.title', 'maintenance.rich_html', 'maintenance.raw_html_override'] as $path) {
        expect($rows[$path]['semantics'])->toContain('sensitive')
            ->and($rows[$path]['selectable'])->toBeTrue()
            ->and($rows[$path]['selected'])->toBeFalse();
    }

    expect($rows['maintenance.title']['semantics'])
        ->toContain('front_text')
        ->and($rows['maintenance.rich_html']['semantics'])->toContain('front_text')
        ->and($rows['maintenance.raw_html_override']['semantics'])->toContain('front_text')
        ->and($analysis->selectedPaths)->not->toContain('maintenance.enabled');
});

it('unions the front-text preset with manually selected import locks', function (): void {
    $manualPath = 'settings_backups.thumbnail_max_width';
    $expectedPaths = app(SettingsImportLocks::class)->normalize([
        $manualPath,
        ...app(SettingsImportLocks::class)->frontTextLockPaths(),
    ]);

    expect(app(SettingsImportLocks::class)->frontTextLockPaths())->not->toContain($manualPath);

    Livewire::test(SettingsImportLocksManager::class)
        ->set('selectedPaths', [$manualPath])
        ->call('lockAllFrontTexts')
        ->assertSet('selectedPaths', fn (array $paths): bool => $paths === $expectedPaths)
        ->call('saveLocks')
        ->assertSee(__('admin.messages.settings_import_locks_saved', ['count' => count($expectedPaths)]));

    expect(app(SettingsImportLocks::class)->lockedPaths())->toBe($expectedPaths);
});

it('excludes locked units from import even when they are force-selected', function (): void {
    app(SettingsImportLocks::class)->save(['homepage_item_limit']);

    $settings = step10S1aSettings();
    $settings->homepage_item_limit = 10;
    $settings->show_latest_section = false;
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 99;
    $payload['show_latest_section'] = true;
    $package = PublicSettingsPackage::fromArray(step10S1aPackageArray($payload));

    $analysis = app(SettingsPackageImportAnalyzer::class)->analyze($package);

    expect($analysis->rowsByPath()['homepage_item_limit'])
        ->toMatchArray([
            'locked' => true,
            'outcome' => 'skip_locked',
            'selectable' => false,
        ]);

    $report = app(SettingsBackupManager::class)->import(
        $package,
        ['homepage_item_limit', 'show_latest_section'],
        auth()->user(),
    );

    $settings = step10S1aSettings();

    expect($report->appliedPaths())->toBe(['show_latest_section'])
        ->and($settings->homepage_item_limit)->toBe(10)
        ->and($settings->show_latest_section)->toBeTrue();
});

it('preserves import row selections when switching import modes', function (): void {
    $settings = step10S1aSettings();
    $settings->route_labels = [];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['route_labels'] = [
        [
            'route_key' => 'search',
            'label' => 'Imported search',
        ],
        [
            'route_key' => 'about',
            'label' => 'Imported about',
        ],
    ];

    Livewire::test(SettingsImportWizard::class)
        ->set('packageFile', step10S1aUploadedPackage(step10S1aPackageArray($payload)))
        ->call('loadUploadedPackage')
        ->assertSet('selectedPaths', fn (array $paths): bool => in_array('route_labels.search', $paths, true)
            && in_array('route_labels.about', $paths, true))
        ->set('selectedPaths', ['route_labels.about'])
        ->set('importMode', SettingsImportMode::AddOnly->value)
        ->assertSet('selectedPaths', ['route_labels.about'])
        ->set('importMode', SettingsImportMode::Replace->value)
        ->assertSet('selectedPaths', ['route_labels.about']);
});

it('restores import locks verbatim and never exposes import_locks as a selectable unit', function (): void {
    app(SettingsImportLocks::class)->save(['homepage_item_limit']);

    $backup = app(SettingsBackupManager::class)->createManual('Lock restore source', auth()->user());

    app(SettingsImportLocks::class)->save(['show_latest_section']);

    app(SettingsBackupManager::class)->restore($backup, auth()->user());

    expect(app(SettingsImportLocks::class)->lockedPaths())->toBe(['homepage_item_limit'])
        ->and(app(SettingsLifecycleSchema::class)->unitPaths())->not->toContain('import_locks')
        ->and(collect(app(SettingsLifecycleSchema::class)->unitPaths())->filter(fn (string $path): bool => str_starts_with($path, 'import_locks.'))->values()->all())->toBe([]);
});

it('keeps the first duplicate card template during analysis and import', function (): void {
    $settings = step10S1aSettings();
    $settings->card_templates = [];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['card_templates'] = [
        [
            'key' => 'duplicate_card',
            'family' => 'content_item',
            'label' => 'First template',
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => [],
        ],
        [
            'key' => 'duplicate_card',
            'family' => 'content_item',
            'label' => 'Second template',
            'layout' => 'rows',
            'density' => 'compact',
            'image_size' => 'small',
            'title_size' => 'sm',
            'parts' => [],
        ],
    ];

    $package = PublicSettingsPackage::fromArray(step10S1aPackageArray($payload));
    $analysis = app(SettingsPackageImportAnalyzer::class)->analyze($package);

    expect(implode("\n", $analysis->warnings))->toContain('duplicate_template_key')
        ->and($analysis->rowsByPath()['card_templates.content_item']['imported_preview'])->toContain('First template')
        ->and($analysis->rowsByPath()['card_templates.content_item']['imported_preview'])->not->toContain('Second template');

    $report = app(SettingsBackupManager::class)->import(
        $package,
        ['card_templates.content_item'],
        auth()->user(),
    );

    $templates = collect(step10S1aSettings()->card_templates)->keyBy('key');

    expect($report->appliedPaths())->toBe(['card_templates.content_item'])
        ->and($templates['duplicate_card']['label'])->toBe('First template')
        ->and($templates['duplicate_card']['layout'])->toBe('cards');
});

it('adds new card-template keys in add-only mode while preserving existing keys', function (): void {
    $settings = step10S1aSettings();
    $settings->card_templates = [
        [
            'key' => 'existing_card',
            'family' => 'content_item',
            'label' => 'Existing',
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => [],
        ],
    ];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['card_templates'] = [
        [
            'key' => 'existing_card',
            'family' => 'content_item',
            'label' => 'Imported collision',
            'layout' => 'rows',
            'density' => 'compact',
            'image_size' => 'small',
            'title_size' => 'sm',
            'parts' => [],
        ],
        [
            'key' => 'new_card',
            'family' => 'content_item',
            'label' => 'New',
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => [],
        ],
    ];

    $report = app(SettingsBackupManager::class)->import(
        PublicSettingsPackage::fromArray(step10S1aPackageArray($payload)),
        ['card_templates.content_item'],
        auth()->user(),
        SettingsImportMode::AddOnly,
    );

    $templates = collect(step10S1aSettings()->card_templates)->keyBy('key');

    expect($report->appliedPaths())->toBe(['card_templates.content_item'])
        ->and($templates)->toHaveKeys(['existing_card', 'new_card'])
        ->and($templates['existing_card']['label'])->toBe('Existing')
        ->and($templates['new_card']['label'])->toBe('New');
});

it('fills empty values and skips populated values in add-only mode', function (): void {
    $settings = step10S1aSettings();
    $settings->homepage_group_title_separator = '';
    $settings->route_labels = [
        [
            'route_key' => 'home',
            'label' => 'Current home',
        ],
    ];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_group_title_separator'] = ' / ';
    $payload['route_labels'] = [
        [
            'route_key' => 'home',
            'label' => 'Imported home',
        ],
        [
            'route_key' => 'search',
            'label' => 'Imported search',
        ],
    ];

    $report = app(SettingsBackupManager::class)->import(
        PublicSettingsPackage::fromArray(step10S1aPackageArray($payload)),
        ['homepage_group_title_separator', 'route_labels.home', 'route_labels.search'],
        auth()->user(),
        SettingsImportMode::AddOnly,
    );

    $settings = step10S1aSettings();
    $labels = collect($settings->route_labels)->keyBy('route_key');

    expect($report->appliedPaths())->toEqualCanonicalizing(['homepage_group_title_separator', 'route_labels.search'])
        ->and($settings->homepage_group_title_separator)->toBe(' / ')
        ->and($labels['home']['label'])->toBe('Current home')
        ->and($labels['search']['label'])->toBe('Imported search');
});

it('lets locks beat add-only mode and reports applied paths after server-side filtering', function (): void {
    app(SettingsImportLocks::class)->save(['route_labels.search']);

    $settings = step10S1aSettings();
    $settings->route_labels = [];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['route_labels'] = [
        [
            'route_key' => 'search',
            'label' => 'Imported search',
        ],
    ];

    $report = app(SettingsBackupManager::class)->import(
        PublicSettingsPackage::fromArray(step10S1aPackageArray($payload)),
        ['route_labels.search'],
        auth()->user(),
        SettingsImportMode::AddOnly,
    );

    expect($report->appliedPaths())->toBe([])
        ->and(step10S1aSettings()->route_labels)->toBe([]);
});

it('computes add-only and lock outcome chips', function (): void {
    app(SettingsImportLocks::class)->save(['route_labels.contributors']);

    $settings = step10S1aSettings();
    $settings->route_labels = [
        [
            'route_key' => 'home',
            'label' => 'Current home',
        ],
    ];
    $settings->save();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['route_labels'] = [
        [
            'route_key' => 'home',
            'label' => 'Imported home',
        ],
        [
            'route_key' => 'search',
            'label' => 'Imported search',
        ],
        [
            'route_key' => 'contributors',
            'label' => 'Imported contributors',
        ],
    ];
    $payload['homepage_item_limit'] = 'invalid';

    $analysis = app(SettingsPackageImportAnalyzer::class)
        ->analyze(PublicSettingsPackage::fromArray(step10S1aPackageArray($payload)), SettingsImportMode::AddOnly);
    $rows = $analysis->rowsByPath();

    expect($rows['route_labels.search']['outcome'])->toBe('add_new')
        ->and($rows['route_labels.home']['outcome'])->toBe('skip_exists')
        ->and($rows['route_labels.contributors']['outcome'])->toBe('skip_locked')
        ->and($rows['route_labels.about']['outcome'])->toBe('skip_unchanged')
        ->and($rows['homepage_item_limit']['outcome'])->toBe('error');
});

it('persists and renders a grouped import report from a mixed add-only import', function (): void {
    app(SettingsImportLocks::class)->save(['route_labels.contributors']);

    $settings = step10S1aSettings();
    $settings->homepage_item_limit = 11;
    $settings->route_labels = [
        [
            'route_key' => 'home',
            'label' => 'Current home',
        ],
    ];
    $settings->save();

    SettingsBackupVersion::query()->delete();

    $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
    $payload['homepage_item_limit'] = 'bad scalar';
    $payload['route_labels'] = [
        [
            'route_key' => 'home',
            'label' => 'Imported home collision',
        ],
        [
            'route_key' => 'search',
            'label' => 'Imported search label',
        ],
        [
            'route_key' => 'contributors',
            'label' => 'Imported locked contributors',
        ],
    ];
    $package = step10S1aPackageArray($payload);

    $component = Livewire::test(SettingsImportWizard::class)
        ->set('importMode', SettingsImportMode::AddOnly->value)
        ->set('packageFile', step10S1aUploadedPackage($package))
        ->call('loadUploadedPackage')
        ->assertSet('step', 'dry-run')
        ->assertSee(__('admin.settings_import.summary.selected', ['count' => 1]))
        ->assertSee(__('admin.settings_import.summary.added', ['count' => 1]))
        ->assertSee(__('admin.settings_import.summary.locked', ['count' => 1]))
        ->assertSee(__('admin.settings_import.summary.errors', ['count' => 1]))
        ->assertSee(__('admin.settings_import.summary.skip_exists', ['count' => 1]))
        ->set('filter', 'locked')
        ->assertSee('Imported locked contributors')
        ->assertDontSee('Imported search label')
        ->set('filter', 'all')
        ->call('applyImport')
        ->assertSet('step', 'complete')
        ->assertSee('data-test="settings-import-completion-report"', false)
        ->assertSee(__('admin.settings_import_report.outcomes.applied'))
        ->assertSee(__('admin.settings_import_report.outcomes.skipped_locked'))
        ->assertSee(__('admin.settings_import_report.outcomes.skipped_exists'))
        ->assertSee(__('admin.settings_import_report.outcomes.errors'));

    $report = $component->instance()->structuredImportReport();
    $beforeImport = SettingsBackupVersion::query()
        ->where('source', SettingsBackupSource::BeforeImport->value)
        ->latest('id')
        ->firstOrFail();

    expect($report->beforeImportBackupId)->toBe($beforeImport->getKey())
        ->and($report->appliedPaths())->toBe(['route_labels.search'])
        ->and($report->outcomeRows('skipped_locked'))->sequence(
            fn ($row) => $row->toMatchArray([
                'path' => 'route_labels.contributors',
                'lock' => [
                    'label' => __('admin.settings_import_report.lock_import_locks'),
                    'path' => 'route_labels.contributors',
                ],
            ]),
        )
        ->and($report->outcomeRows('skipped_exists'))->sequence(
            fn ($row) => $row->toMatchArray(['path' => 'route_labels.home']),
        )
        ->and($report->outcomeRows('errors'))->sequence(
            fn ($row) => $row->toMatchArray(['path' => 'homepage_item_limit']),
        )
        ->and($report->outcomeRows('errors')[0]['reason'])->toContain('homepage_item_limit')
        ->and($beforeImport->import_report['before_import_backup_id'])->toBe($beforeImport->getKey());

    $manualWithoutReport = app(SettingsBackupManager::class)->createManual('No import report', auth()->user());

    Livewire::test(ListSettingsBackups::class)
        ->assertActionVisible(TestAction::make('importReport')->table($beforeImport))
        ->assertActionHidden(TestAction::make('importReport')->table($manualWithoutReport))
        ->mountAction(TestAction::make('importReport')->table($beforeImport))
        ->assertMountedActionModalSee(__('admin.settings_import_report.outcomes.applied'))
        ->assertMountedActionModalSee('route_labels.search')
        ->assertMountedActionModalSee('homepage_item_limit');

    auth()->logout();

    $this->get(SettingsBackupResource::getUrl('index'))
        ->assertRedirect('/admin/login');
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

it('toggles import locks from inline section and deep field actions', function (): void {
    $homepageSectionPath = 'public-content-settings-tabs.public-settings-tab-homepage.public-settings-lock-section-homepage-settings';
    $itemPageDatesFieldPath = 'public-content-settings-tabs.public-settings-tab-item-page.public-settings-lock-section-public-front-item-page-dates.item_page.dates.site_published.label_override';
    $scalarPaths = collect(app(SettingsLifecycleSchema::class)->units())
        ->filter(fn ($unit): bool => $unit->section === '_scalars')
        ->pluck('path')
        ->values()
        ->all();

    $component = Livewire::test(PublicContentSettingsPage::class)
        ->assertActionVisible(TestAction::make('manageImportLocks'))
        ->assertActionHasUrl(TestAction::make('manageImportLocks'), ManageSettingsImportLocks::getUrl())
        ->assertActionExists(TestAction::make('toggleImportLockGroup_homepage_settings')->schemaComponent($homepageSectionPath, 'form'))
        ->assertActionExists(TestAction::make('toggleImportLockUnit_item_page_dates')->schemaComponent($itemPageDatesFieldPath, 'form'));

    expect(str_ends_with(ManageSettingsImportLocks::getUrl(), '/admin/settings-import-locks'))->toBeTrue();

    $component->callAction(TestAction::make('toggleImportLockGroup_homepage_settings')->schemaComponent($homepageSectionPath, 'form'));

    expect(app(SettingsImportLocks::class)->lockedPaths())->toBe($scalarPaths);

    $component->callAction(TestAction::make('toggleImportLockGroup_homepage_settings')->schemaComponent($homepageSectionPath, 'form'));

    expect(app(SettingsImportLocks::class)->lockedPaths())->toBe([]);

    $component->callAction(TestAction::make('toggleImportLockUnit_item_page_dates')->schemaComponent($itemPageDatesFieldPath, 'form'));

    expect(app(SettingsImportLocks::class)->lockedPaths())->toBe(['item_page.dates']);
});

it('lets locked settings remain editable and save normally', function (): void {
    app(SettingsImportLocks::class)->save(['homepage_item_limit']);

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.homepage_item_limit', 41)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(step10S1aSettings()->homepage_item_limit)->toBe(41)
        ->and(app(SettingsImportLocks::class)->lockedPaths())->toBe(['homepage_item_limit']);
});

it('renders the settings header action and rtl lock hints', function (): void {
    $homepageLimitFieldPath = 'public-content-settings-tabs.public-settings-tab-homepage.public-settings-lock-section-homepage-settings.homepage_item_limit';

    app()->setLocale('he');
    app(SettingsImportLocks::class)->save(['homepage_item_limit']);

    Livewire::test(PublicContentSettingsPage::class)
        ->assertActionVisible(TestAction::make('manageImportLocks'))
        ->assertActionHasLabel(TestAction::make('manageImportLocks'), __('admin.actions.manage_import_locks'))
        ->assertActionExists(TestAction::make('toggleImportLockUnit_homepage_item_limit')->schemaComponent($homepageLimitFieldPath, 'form'));

    $this->get(PublicContentSettingsPage::getUrl())
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('admin.actions.manage_import_locks'))
        ->assertSee(__('admin.settings_import_locks.inline_field_tooltip.locked', [
            'unit' => __('admin.fields.homepage_item_limit'),
            'path' => 'homepage_item_limit',
        ]));
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
