<?php

use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\ContentTags\Pages\ListContentTags;
use App\Filament\Resources\HomepageSections\Pages\ListHomepageSections;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\PublicFormSubmissions\Pages\ListPublicFormSubmissions;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
});

it('keeps exactly 43 Resource table record actions icon only with authoritative labels and tooltips', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();

    $surfaces = [
        'authors' => [Livewire::test(ListAuthors::class)->instance()->getTable()->getRecordActions(), 1],
        'categories' => [Livewire::test(ListCategories::class)->instance()->getTable()->getRecordActions(), 1],
        'podcasts' => [Livewire::test(ListContentGroups::class)->instance()->getTable()->getRecordActions(), 4],
        'episodes' => [Livewire::test(ListContentItems::class)->instance()->getTable()->getRecordActions(), 8],
        'podcast episodes' => [Livewire::test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])->instance()->getTable()->getRecordActions(), 10],
        'episode transcriptions' => [Livewire::test(TranscriptionsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditContentItem::class,
        ])->instance()->getTable()->getRecordActions(), 4],
        'content tags' => [Livewire::test(ListContentTags::class)->instance()->getTable()->getRecordActions(), 1],
        'homepage sections' => [Livewire::test(ListHomepageSections::class)->instance()->getTable()->getRecordActions(), 1],
        'media' => [Livewire::test(ListMedia::class)->instance()->getTable()->getRecordActions(), 6],
        'public form submissions' => [Livewire::test(ListPublicFormSubmissions::class)->instance()->getTable()->getRecordActions(), 4],
        'transcriptions' => [Livewire::test(ListTranscriptions::class)->instance()->getTable()->getRecordActions(), 2],
        'users' => [Livewire::test(ListUsers::class)->instance()->getTable()->getRecordActions(), 1],
    ];

    $actionCount = 0;

    foreach ($surfaces as $surface => [$actions, $expectedCount]) {
        expect($actions)->toHaveCount($expectedCount, $surface);
        $actionCount += count($actions);
        $icons = [];

        foreach ($actions as $action) {
            expect($action)->toBeInstanceOf(Action::class)
                ->and($action->isIconButton())->toBeTrue()
                ->and($action->isLabelHidden())->toBeTrue()
                ->and($action->getIcon())->toBeInstanceOf(Heroicon::class)
                ->and((string) $action->getTooltip())->toBe((string) $action->getLabel());

            $icons[] = $action->getIcon()->value;
        }

        expect(array_unique($icons))->toHaveCount($expectedCount, "{$surface} duplicate icons");
    }

    expect($actionCount)->toBe(43);
});

it('leaves the Settings Backups group and custom Page tables outside the rider', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());
    $recordActions = Livewire::test(ListSettingsBackups::class)
        ->instance()
        ->getTable()
        ->getRecordActions();

    expect($recordActions)->toHaveCount(1)
        ->and($recordActions[0])->toBeInstanceOf(ActionGroup::class);

    foreach ($recordActions[0]->getFlatActions() as $action) {
        expect($action->isLabelHidden())->toBeFalse();
    }

    foreach ([CardTemplateSettings::class, ImporterSettings::class] as $pageClass) {
        $source = file_get_contents((new ReflectionClass($pageClass))->getFileName());

        expect($source)->not->toContain('ResourceTableActions');
    }
});
