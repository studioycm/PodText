<?php

use App\Enums\ImportConnectionProvider;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Filament\Widgets\IntakeQueueWidget;
use App\Models\PublicFormSubmission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
