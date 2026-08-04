<?php

use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('scopes episode authority through a real policy', function (): void {
    $episode = ContentItem::factory()->create();

    // EQ-6 (2026-08-05): daily editorial authority stays uniform for every
    // panel admin — which is exactly what arms the inline status column's
    // disabled() gate, since Gate::allows('update') was false while no
    // policy existed. Deleting an episode destroys its transcript history,
    // so it is reserved for super-admins, like settings backups.
    expect(Gate::getPolicyFor(ContentItem::class))->not->toBeNull()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('update', $episode))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('create', ContentItem::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('viewAny', ContentItem::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('delete', $episode))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('deleteAny', ContentItem::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('delete', $episode))->toBeTrue()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('deleteAny', ContentItem::class))->toBeTrue();
});

it('hides the workspace delete action from plain admins and keeps it for super-admins', function (): void {
    $episode = ContentItem::factory()->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $episode->getKey()])
        ->assertActionHidden('delete');

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $episode->getKey()])
        ->assertActionVisible('delete');
});

it('withholds the bulk delete from plain admins and lets super-admins bulk delete', function (): void {
    $episodes = ContentItem::factory()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListContentItems::class)
        ->assertActionHidden(TestAction::make('delete')->table()->bulk());

    expect(ContentItem::query()->count())->toBe(2);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ListContentItems::class)
        ->selectTableRecords($episodes->modelKeys())
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(ContentItem::query()->count())->toBe(0);
});
