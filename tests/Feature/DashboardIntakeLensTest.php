<?php

use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Widgets\IntakeQueueWidget;
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
