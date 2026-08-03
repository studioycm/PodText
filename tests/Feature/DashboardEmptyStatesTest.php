<?php

use App\Filament\Widgets\ActivityStreamWidget;
use App\Filament\Widgets\LibraryCompositionWidget;
use App\Filament\Widgets\PublicationGapWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
});

it('renders the shared empty state as a dashed panel with heading, description and icon', function (): void {
    $html = view('filament.widgets.partials.empty-state', [
        'heading' => 'Nothing here yet',
        'description' => 'Rows appear later.',
        'icon' => Heroicon::OutlinedBolt,
        'testid' => 'empty-under-test',
    ])->render();

    expect($html)->toContain('border-dashed')
        ->toContain('data-testid="empty-under-test"')
        ->toContain('Nothing here yet')
        ->toContain('Rows appear later.')
        ->toContain('<svg');
});

it('shows a dashed empty state when the stream has no activity in range', function (): void {
    // Both locales must carry the new description alongside the old heading.
    expect(trans()->has('admin.dashboard.stream.empty_description', 'en'))->toBeTrue()
        ->and(trans()->has('admin.dashboard.stream.empty_description', 'he'))->toBeTrue();

    Livewire::test(ActivityStreamWidget::class)
        ->assertSeeHtml('data-testid="stream-empty"')
        ->assertSeeHtml('border-dashed')
        ->assertSee(__('admin.dashboard.stream.empty'))
        ->assertSee(__('admin.dashboard.stream.empty_description'));
});

it('shows dashed empty states for podcast health and the transcriber board', function (): void {
    expect(trans()->has('admin.dashboard.composition.health_empty_description', 'en'))->toBeTrue()
        ->and(trans()->has('admin.dashboard.composition.health_empty_description', 'he'))->toBeTrue()
        ->and(trans()->has('admin.dashboard.composition.transcribers_empty_description', 'en'))->toBeTrue()
        ->and(trans()->has('admin.dashboard.composition.transcribers_empty_description', 'he'))->toBeTrue();

    Livewire::test(LibraryCompositionWidget::class)
        ->assertSeeHtml('data-testid="composition-health-empty"')
        ->assertSeeHtml('data-testid="composition-transcribers-empty"')
        ->assertSeeHtml('border-dashed')
        ->assertSee(__('admin.dashboard.composition.health_empty'))
        ->assertSee(__('admin.dashboard.composition.transcribers_empty'));
});

it('replaces the gap bar with a dashed empty state when nothing is published', function (): void {
    expect(trans()->has('admin.dashboard.gap.nothing_published_description', 'en'))->toBeTrue()
        ->and(trans()->has('admin.dashboard.gap.nothing_published_description', 'he'))->toBeTrue();

    Livewire::test(PublicationGapWidget::class)
        ->assertSeeHtml('data-testid="gap-empty"')
        ->assertSeeHtml('border-dashed')
        ->assertSee(__('admin.dashboard.gap.nothing_published'))
        ->assertDontSeeHtml('data-testid="gap-bar"');
});
