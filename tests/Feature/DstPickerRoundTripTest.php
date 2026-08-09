<?php

use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// Separate file from DstInputEdgeTest.php: this is the only DST-input test
// that touches the database (the rest validate the rule and the picker hook
// directly), so it is the only one that needs RefreshDatabase.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Mirrors the fillForm() shim in AdminResourcesTest.php: EditContentItem
    // combines relation-manager tabs with content, so the state path has to
    // come from getDefaultTestingSchemaName() rather than the bare 'data'
    // Livewire::test()->fillForm() would otherwise assume.
    Testable::macro('fillForm', function (array|Closure $state = [], ?string $form = null): Testable {
        if ($state instanceof Closure) {
            $state = $state([]);
        }

        $schemaStatePath = 'data';

        if (method_exists($this->instance(), 'getDefaultTestingSchemaName')) {
            $form ??= $this->instance()->getDefaultTestingSchemaName();
            $schemaStatePath = $this->instance()->{$form}->getStatePath();
        }

        foreach ($state as $key => $value) {
            $this->set(filled($schemaStatePath) ? "{$schemaStatePath}.{$key}" : $key, $value);
        }

        return $this;
    });

    $this->actingAs(User::factory()->create());
});

it('round-trips a Jerusalem wall time through the content item edit form as the correct UTC instant', function (): void {
    $item = ContentItem::factory()->create();

    // 15 June 2026 is deep in IDT (UTC+3) — nowhere near either 2026 DST
    // edge — so this proves the ordinary-day path, not the gap rule itself
    // (DstInputEdgeTest.php owns the edge cases).
    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['published_at' => '2026-06-15 10:15:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    // published_at is a DATETIME column (not TIMESTAMP) post-alignment, so
    // this literal is exactly what MySQL stores — no session-timezone
    // reinterpretation to account for.
    $this->assertDatabaseHas('content_items', [
        'id' => $item->id,
        'published_at' => '2026-06-15 07:15:00',
    ]);

    // Not assertSchemaStateSet(): that reads getState(), which re-runs
    // dehydrate (Jerusalem -> UTC) over the freshly-hydrated value and would
    // just show UTC again — a hydrate/dehydrate identity, not proof of what
    // the picker widget displays. assertSet() reads the raw Livewire
    // property straight after hydrateState()'s Jerusalem conversion, which
    // is what the human actually sees.
    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSet('data.published_at', '2026-06-15 10:15:00');
});
