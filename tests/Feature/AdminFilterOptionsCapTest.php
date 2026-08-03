<?php

use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create());
});

/**
 * The filter's Select exactly as the browser drives it: the mounted
 * filters-form field whose `getOptionsForJs()` / `getSearchResultsForJs()`
 * endpoints the JS select calls. A static `->options()` closure ships its
 * whole result through `getOptionsForJs()` on every render, which is what
 * these tests pin against: growing sets must serve nothing up front and
 * answer searches through a capped server query.
 */
function cappedFilterSelect(Testable $component, string $filterKey): Select
{
    $fields = $component->instance()
        ->getTableFiltersForm()
        ->getComponentByStatePath($filterKey)
        ?->getChildSchema()
        ->getFlatFields() ?? [];

    $select = $fields['value'] ?? null;

    expect($select)->toBeInstanceOf(Select::class);

    return $select;
}

it('serves items-list transcriber filter options only through bounded search', function (): void {
    Author::factory()
        ->count(55)
        ->sequence(fn ($sequence) => ['name' => sprintf('Cap Author %03d', $sequence->index)])
        ->create();

    $select = cappedFilterSelect(Livewire::test(ListContentItems::class), 'transcriber_id');

    expect($select->getOptionsForJs())->toBe([])
        ->and($select->getSearchResultsForJs('Cap Author'))->toHaveCount(50)
        ->and(collect($select->getSearchResultsForJs('Cap Author 054'))->pluck('label')->all())->toBe(['Cap Author 054']);
});

it('serves transcriptions-list transcriber filter options only through bounded search', function (): void {
    Author::factory()
        ->count(55)
        ->sequence(fn ($sequence) => ['name' => sprintf('Cap Author %03d', $sequence->index)])
        ->create();

    $select = cappedFilterSelect(Livewire::test(ListTranscriptions::class), 'transcriber_id');

    expect($select->getOptionsForJs())->toBe([])
        ->and($select->getSearchResultsForJs('Cap Author'))->toHaveCount(50)
        ->and(collect($select->getSearchResultsForJs('Cap Author 054'))->pluck('label')->all())->toBe(['Cap Author 054']);
});

it('serves transcriptions-list group filter options only through bounded search', function (): void {
    ContentGroup::factory()
        ->count(55)
        ->sequence(fn ($sequence) => ['title' => sprintf('Cap Group %03d', $sequence->index)])
        ->create();

    $select = cappedFilterSelect(Livewire::test(ListTranscriptions::class), 'content_group_id');

    expect($select->getOptionsForJs())->toBe([])
        ->and($select->getSearchResultsForJs('Cap Group'))->toHaveCount(50)
        ->and(collect($select->getSearchResultsForJs('Cap Group 054'))->pluck('label')->all())->toBe(['Cap Group 054']);
});

it('keeps narrowing the transcriptions list by transcriber and by group under the capped filters', function (): void {
    $groupA = ContentGroup::factory()->create(['title' => 'Group A']);
    $groupB = ContentGroup::factory()->create(['title' => 'Group B']);

    $itemA = ContentItem::factory()->for($groupA)->create();
    $itemB = ContentItem::factory()->for($groupB)->create();

    $authorA = Author::factory()->create(['name' => 'Author A']);
    $authorB = Author::factory()->create(['name' => 'Author B']);

    $transcriptionA = Transcription::factory()->for($itemA)->forAuthor($authorA)->create();
    $transcriptionB = Transcription::factory()->for($itemB)->forAuthor($authorB)->create();

    $component = Livewire::test(ListTranscriptions::class)
        ->filterTable('transcriber_id', $authorA->id)
        ->assertCanSeeTableRecords([$transcriptionA])
        ->assertCanNotSeeTableRecords([$transcriptionB]);

    // The chosen value resolves its label without a full option list — the
    // material the active-filter indicator is built from.
    expect(cappedFilterSelect($component, 'transcriber_id')->getOptionLabel())->toBe('Author A');

    Livewire::test(ListTranscriptions::class)
        ->filterTable('content_group_id', $groupA->id)
        ->assertCanSeeTableRecords([$transcriptionA])
        ->assertCanNotSeeTableRecords([$transcriptionB]);
});
