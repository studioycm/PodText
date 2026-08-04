<?php

use App\Enums\DashboardLens;
use App\Enums\DashboardRange;
use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Resources\Imports\ImportResource;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\IntakeQueueWidget;
use App\Filament\Widgets\MediaFindingsWidget;
use App\Filament\Widgets\PublicFormTargetWarningsWidget;
use App\Filament\Widgets\SpotifyConnectionWidget;
use App\Models\ImportConnection;
use App\Models\PublicFormSubmission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists new submissions and failed imports with kind chips', function (): void {
    PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);
    failedImport(fileName: 'episodes.csv');

    Livewire::test(IntakeQueueWidget::class)
        ->assertSee('Join us')
        ->assertSee('episodes.csv')
        ->assertSeeHtml('data-testid="intake-row"')
        ->assertSeeHtml('data-testid="widget-tag-stock"')
        ->assertDontSeeHtml('wire:poll')
        ->call('selectKind', StreamEventType::Submission->value)
        ->assertSee('Join us')
        ->assertDontSee('episodes.csv')
        ->call('selectKind', 'nonsense')
        ->assertSet('kind', null);
});

it('shows the honest provider empty state under a connected source filter', function (): void {
    PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);

    Livewire::test(IntakeQueueWidget::class, ['pageFilters' => ['source' => ImportConnectionProvider::Spotify->value]])
        ->assertDontSee('Join us')
        ->assertSeeHtml('data-testid="intake-source-empty"');
});

it('shows the empty state when nothing needs intake attention', function (): void {
    PublicFormSubmission::factory()->reviewed()->create();

    Livewire::test(IntakeQueueWidget::class)
        ->assertSee(__('admin.dashboard.intake.empty_heading'))
        ->assertDontSeeHtml('data-testid="intake-row"');
});

it('echoes the Spotify connection test with a day-first timestamp', function (): void {
    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => ImportConnectionAuthType::ClientCredentials,
        'status' => ImportConnectionStatus::Connected,
        // Stored wall time is UTC (the repo's storage convention — casts do
        // not tz-convert on write); the board renders it in Jerusalem, +3 in
        // summer, exactly like the stream's existing 10:00→13:00 pin.
        'last_tested_at' => Carbon::parse('2026-07-31 07:55'),
    ]);

    Livewire::test(SpotifyConnectionWidget::class)
        ->assertSee('Main Spotify')
        ->assertSee(__('admin.importer.statuses.connected'))
        ->assertSee('31/07/2026 10:55')
        ->assertSeeHtml('data-testid="widget-tag-stock"')
        ->assertDontSee(__('admin.dashboard.connection.reduced_note'))
        ->assertDontSeeHtml('wire:poll');
});

it('shows the reduced-mode empty state without a Spotify connection', function (): void {
    Livewire::test(SpotifyConnectionWidget::class)
        ->assertSee(__('admin.dashboard.connection.none_heading'))
        ->assertSee(__('admin.dashboard.connection.none_description'))
        ->assertSeeHtml(ImporterSettings::getUrl());
});

it('marks a failed connection reduced', function (): void {
    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => ImportConnectionAuthType::ClientCredentials,
        'status' => ImportConnectionStatus::Failed,
        'last_tested_at' => null,
    ]);

    Livewire::test(SpotifyConnectionWidget::class)
        ->assertSee(__('admin.importer.statuses.failed'))
        ->assertSee(__('admin.dashboard.connection.never_tested'))
        ->assertSee(__('admin.dashboard.connection.reduced_note'));
});

it('renders finding bars styled by the enum with gallery doorways', function (): void {
    cleanMedia();
    missingFileMedia();

    Livewire::test(MediaFindingsWidget::class)
        ->assertSee(__('admin.media_library.repair_missing_file'))
        ->assertSeeHtml('data-testid="media-finding-row"')
        ->assertSeeHtml(MediaDiagnosticReason::MissingFile->barClass())
        ->assertSeeHtml('tab='.MediaLibraryTask::NeedsAttention->value)
        ->assertSeeHtml('filters%5Breason%5D%5Bvalue%5D='.MediaDiagnosticReason::MissingFile->value)
        ->assertSee(__('admin.dashboard.media_findings.rate', ['percent' => 50.0]))
        ->assertSeeHtml('data-testid="widget-tag-stock"')
        ->assertDontSeeHtml('wire:poll');
});

it('hides zero-count findings and celebrates a clean library', function (): void {
    cleanMedia();

    Livewire::test(MediaFindingsWidget::class)
        ->assertSee(__('admin.dashboard.media_findings.empty'))
        ->assertDontSeeHtml('data-testid="media-finding-row"');
});

it('renders the intake board in board 3 order', function (): void {
    expect(Dashboard::getWidgetsForLens(DashboardLens::Intake))->toBe([
        DashboardContextWidget::class,
        PublicFormTargetWarningsWidget::class,
        IntakeQueueWidget::class,
        SpotifyConnectionWidget::class,
        MediaFindingsWidget::class,
    ]);
});

it('hides range and podcast on intake and shows the sources filter', function (): void {
    Livewire::test(Dashboard::class)
        ->dispatch('dashboard-filter', key: 'lens', value: DashboardLens::Intake->value)
        ->assertDontSee(__('admin.dashboard.filters.podcast_hint'))
        ->assertSee(__('admin.dashboard.filters.all_sources'))
        ->assertDontSee(DashboardRange::Last7Days->getLabel());
});

it('accepts only command-bar keys for the source filter', function (): void {
    Livewire::test(Dashboard::class)
        ->dispatch('dashboard-filter', key: 'source', value: ImportConnectionProvider::Spotify->value)
        ->assertSet('filters.source', ImportConnectionProvider::Spotify->value);
});

it('echoes the source scope instead of podcast and range on intake', function (): void {
    Livewire::test(DashboardContextWidget::class, [
        'pageFilters' => ['lens' => DashboardLens::Intake->value, 'source' => ImportConnectionProvider::Spotify->value],
    ])
        ->assertSee(ImportConnectionProvider::Spotify->getLabel())
        ->assertDontSee(__('admin.dashboard.filters.all_podcasts'));
});

it('renders declared-provider rows under their source filter', function (): void {
    failedImport(fileName: 'drive.csv')->forceFill(['provider' => 'google_drive'])->save();

    Livewire::test(IntakeQueueWidget::class, ['pageFilters' => ['source' => ImportConnectionProvider::GoogleDrive->value]])
        ->assertSee('drive.csv')
        ->assertDontSeeHtml('data-testid="intake-source-empty"');
});

it('offers both overflow doorways in the cap note', function (): void {
    PublicFormSubmission::factory()->count(11)->create();

    Livewire::test(IntakeQueueWidget::class)
        ->assertSeeHtml('data-testid="intake-cap-note"')
        ->assertSee(__('admin.dashboard.intake.view_new_submissions'))
        ->assertSee(__('admin.dashboard.intake.view_imports'))
        ->assertSeeHtml(ImportResource::getUrl('index'));
});
