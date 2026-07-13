<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Jobs\SettingsBackupSnapshotJob;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Maintenance\MaintenanceForm;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\Settings\SettingsPageProfiler;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config([
        'settings.cache.enabled' => true,
        'settings.profiling.enabled' => false,
    ]);

    Cache::flush();
    Queue::fake([SettingsBackupSnapshotJob::class]);
    Storage::fake('local');
    stepSp1ForgetSettingsState();

    $this->actingAs(User::factory()->create());
});

function stepSp1ForgetSettingsState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function stepSp1SaveMaintenanceMarkerSettings(): void
{
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = [
        'definitions' => [
            [
                'key' => 'maintenance_contact',
                'name' => 'Maintenance contact',
                'enabled' => true,
                'display_mode_default' => 'modal',
                'fields' => [
                    [
                        'key' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                    ],
                ],
            ],
        ],
    ];
    $settings->maintenance = [
        ...PublicFrontConfigRegistry::defaults()['maintenance'],
        'form_key' => 'maintenance_contact',
        'form_location' => MaintenanceForm::LOCATION_RAW_HTML,
        'raw_html_override' => '<main>'.MaintenanceForm::MARKER.'</main>',
    ];
    $settings->save();

    stepSp1ForgetSettingsState();
}

it('keeps settings profiling disabled by default without resolving the log channel', function (): void {
    config(['settings.profiling.enabled' => false]);

    Log::shouldReceive('channel')->never();

    app(SettingsPageProfiler::class)->record(
        phase: 'test.disabled',
        milliseconds: 1.0,
        requestKind: SettingsPageProfiler::REQUEST_INITIAL_LOAD,
    );
});

it('writes named settings profiling phases when the flag is enabled', function (): void {
    config(['settings.profiling.enabled' => true]);

    $contexts = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger
        ->shouldReceive('info')
        ->zeroOrMoreTimes()
        ->with('Settings page profile', Mockery::type('array'))
        ->andReturnUsing(function (string $message, array $context) use (&$contexts): void {
            $contexts[] = $context;
        });

    Log::shouldReceive('channel')
        ->zeroOrMoreTimes()
        ->with('settings_profiling')
        ->andReturn($logger);

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.homepage_item_limit', 17)
        ->call('save')
        ->assertHasNoFormErrors();

    $phases = collect($contexts)->pluck('phase');

    expect($phases)
        ->toContain('settings.read_hydrate')
        ->toContain('form.total_build')
        ->toContain('schema.tab.homepage')
        ->toContain('save.validation.total')
        ->toContain('save.mutate_normalize')
        ->toContain('save.settings_persist')
        ->toContain('settings_saved.listener.total')
        ->toContain('settings_saved.backup_creation')
        ->and(collect($contexts)->firstWhere('phase', 'payload.load')['payload_bytes'] ?? 0)->toBeGreaterThan(0)
        ->and(collect($contexts)->firstWhere('phase', 'validator.group.maintenance'))->toBeArray()
        ->and(collect($contexts)
            ->where('phase', 'validator.group.maintenance')
            ->pluck('request_kind'))
        ->toContain(SettingsPageProfiler::REQUEST_SAVE);
});

it('renders the maintenance raw html marker and copies the exact marker payload', function (): void {
    stepSp1SaveMaintenanceMarkerSettings();

    $component = Livewire::test(PublicContentSettingsPage::class);

    expect($component->html())->toContain('data-podtext-maintenance-form');

    $markerField = collect($component->instance()->form->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof TextInput
            && $component->getStatePath(isAbsolute: false) === 'maintenance_form_marker');

    expect($markerField)->toBeInstanceOf(TextInput::class);
    expect($markerField->getState())->toBe(MaintenanceForm::MARKER);

    $copyAction = $markerField->getSuffixActions()['copy'] ?? null;
    $expectedPayload = Js::from(MaintenanceForm::MARKER)->toHtml();

    expect($copyAction)->not->toBeNull();
    expect($copyAction->getAlpineClickHandler())
        ->toContain("window.navigator.clipboard.writeText({$expectedPayload})")
        ->not->toContain('null');
});
