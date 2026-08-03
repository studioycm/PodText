<?php

use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Page-tier guard: a resource's static can*() overrides gate its PAGES and
 * navigation (EditRecord::authorizeAccess and friends call them), but the
 * built-in mutating table/page actions authorize through
 * Page::getDefaultActionAuthorizationResponse() straight into the model's
 * POLICY — and with no policy registered, non-strict mode allows every panel
 * user. So authorization intent parked in can*() overrides protects nothing
 * the moment a policy-authorized mutator appears. This loop holds every
 * panel resource to: overrides are backed by a policy, or the resource
 * exposes no policy-authorized direct mutators, or it carries an explicit
 * allow-list entry saying why. Create/Edit actions count as direct mutators
 * only without a corresponding registered page, because with one they
 * navigate into the page the overrides do guard (the v5 default wiring; an
 * action re-pointed away from its page would evade this classifier).
 */
it('backs can-override resources with a policy or keeps their mutation surface page-tier', function (): void {
    // Every entry must name its one-line why, and must still trip the
    // detector below — a stale entry fails the test.
    $allowList = [
        SettingsBackupResource::class => 'Backup rows are shared panel-maintenance artifacts: the table DeleteAction runs behind the panel role gate alone today (no per-record authority distinction has been designed), and canCreate(false) only removes the meaningless create form. Flagged in the post-B1 report for an explicit SettingsBackupPolicy decision.',
    ];

    $this->actingAs(User::factory()->superAdmin()->create());

    $alwaysDirectMutators = [
        DeleteAction::class,
        DeleteBulkAction::class,
        ForceDeleteAction::class,
        ForceDeleteBulkAction::class,
        RestoreAction::class,
        RestoreBulkAction::class,
        ReplicateAction::class,
    ];

    $resourcesWithOverrides = [];
    $trippedAllowListEntries = [];

    foreach (Filament::getPanels() as $panel) {
        Filament::setCurrentPanel($panel);

        foreach ($panel->getResources() as $resource) {
            $overrides = collect(get_class_methods($resource))
                ->filter(fn (string $method): bool => str_starts_with($method, 'can'))
                ->filter(fn (string $method): bool => ! str_starts_with(
                    (new ReflectionMethod($resource, $method))->getDeclaringClass()->getName(),
                    'Filament\\',
                ))
                ->values();

            if ($overrides->isEmpty()) {
                continue;
            }

            $resourcesWithOverrides[] = $resource;

            if (Gate::getPolicyFor($resource::getModel()) !== null) {
                continue;
            }

            $listPage = collect($resource::getPages())
                ->map(fn ($registration): string => $registration->getPage())
                ->first(fn (string $page): bool => is_subclass_of($page, ListRecords::class));

            if ($listPage === null) {
                continue;
            }

            $instance = Livewire::test($listPage)->instance();

            $directMutators = collect($instance->getTable()->getFlatActions())
                ->merge($instance->getTable()->getFlatBulkActions())
                ->merge(collect($instance->getCachedHeaderActions())->keyBy(fn (Action $action): string => $action->getName()))
                ->filter(function (Action $action) use ($alwaysDirectMutators, $resource): bool {
                    foreach ($alwaysDirectMutators as $class) {
                        if ($action instanceof $class) {
                            return true;
                        }
                    }

                    if ($action instanceof EditAction) {
                        return ! $resource::hasPage('edit');
                    }

                    if ($action instanceof CreateAction) {
                        return ! $resource::hasPage('create');
                    }

                    return false;
                })
                ->keys()
                ->sort()
                ->values()
                ->all();

            if (array_key_exists($resource, $allowList)) {
                expect($directMutators)->not->toBe(
                    [],
                    "{$resource} is allow-listed but exposes no policy-authorized mutator any more — the entry is stale, remove it.",
                );

                $trippedAllowListEntries[] = $resource;

                continue;
            }

            expect($directMutators)->toBe(
                [],
                "{$resource} parks authorization in can*() overrides with no policy registered for {$resource::getModel()},"
                .' while these actions authorize against the missing policy and are open to every panel user: '
                .implode(', ', $directMutators)
                .'. Register a policy, remove the mutators, or allow-list the resource with a why.',
            );
        }
    }

    expect($resourcesWithOverrides)->not->toBe([], 'The override detector found nothing — the reflection filter broke.')
        ->and($trippedAllowListEntries)->toBe(array_keys($allowList), 'Every allow-list entry must belong to a checked, policy-less resource that still trips the detector.');
});
