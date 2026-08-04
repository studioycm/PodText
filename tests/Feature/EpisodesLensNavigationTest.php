<?php

use App\Enums\TranscriptionMode;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Filament\Support\AdminNavigationOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('puts episodes in the ungrouped front-door block above the labelled groups', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    setTestTranscriptionMode(TranscriptionMode::Multi);

    expect(AdminNavigationOrder::groupKey(ContentItemResource::class))->toBeNull()
        ->and(AdminNavigationOrder::sort(ContentItemResource::class))
        ->toBeGreaterThan(AdminNavigationOrder::episodeWorkspaceCreateSort());

    $navigation = collect(Filament::getPanel('admin')->getNavigation());

    $leadingLabels = collect($navigation->first()->getItems())
        ->map(fn ($item): string => $item->getLabel())
        ->all();

    expect($leadingLabels)->toContain(__('admin.resources.content_item.navigation'))
        // The episodes item sits directly after the «new episode» item.
        ->and(array_search(__('admin.resources.content_item.navigation'), $leadingLabels, true))
        ->toBe(array_search(__('admin.resources.content_item.workspace_navigation'), $leadingLabels, true) + 1);
});

it('hides the transcripts item from plain admins in single mode only', function (
    TranscriptionMode $mode,
    string $role,
    bool $expected,
): void {
    setTestTranscriptionMode($mode);
    $this->actingAs(User::factory()->{$role}()->create());

    expect(TranscriptionResource::shouldRegisterNavigation())->toBe($expected);

    $labels = collect(Filament::getPanel('admin')->getNavigation())
        ->flatMap(fn ($group): array => collect($group->getItems())
            ->map(fn ($item): string => $item->getLabel())
            ->all())
        ->all();

    expect(in_array(TranscriptionResource::getNavigationLabel(), $labels, true))->toBe($expected);
})->with([
    'single mode, plain admin — hidden' => [TranscriptionMode::Single, 'admin', false],
    'single mode, super-admin — visible' => [TranscriptionMode::Single, 'superAdmin', true],
    'multi mode, plain admin — visible' => [TranscriptionMode::Multi, 'admin', true],
    'multi mode, super-admin — visible' => [TranscriptionMode::Multi, 'superAdmin', true],
]);

it('keeps the transcripts resource reachable by URL while its nav item is hidden', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Single);
    $this->actingAs(User::factory()->admin()->create());

    // Hiding is decluttering, not access control (the Filament docs are
    // explicit that shouldRegisterNavigation() only hides the link).
    expect(TranscriptionResource::shouldRegisterNavigation())->toBeFalse()
        ->and(TranscriptionResource::canAccess())->toBeTrue();

    $this->get(TranscriptionResource::getUrl('index'))->assertSuccessful();
});
