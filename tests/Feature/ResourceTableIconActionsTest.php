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
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\RecordActionsPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
});

it('keeps exactly 28 general Resource table record actions icon only with authoritative labels and tooltips', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();

    $surfaces = [
        'authors' => [Livewire::test(ListAuthors::class)->instance()->getTable()->getRecordActions(), 1],
        'categories' => [Livewire::test(ListCategories::class)->instance()->getTable()->getRecordActions(), 1],
        'podcasts' => [Livewire::test(ListContentGroups::class)->instance()->getTable()->getRecordActions(), 3],
        // R1 (P-EL4 two-daily-actions): both episode surfaces keep the two
        // daily icons plus the two contextual remedy doors, and moved the
        // occasional actions into an ActionGroup asserted below.
        'episodes' => [Livewire::test(ListContentItems::class)->instance()->getTable()->getRecordActions(), 5],
        'podcast episodes' => [Livewire::test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])->instance()->getTable()->getRecordActions(), 5],
        'episode transcriptions' => [Livewire::test(TranscriptionsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditContentItem::class,
        ])->instance()->getTable()->getRecordActions(), 4],
        'content tags' => [Livewire::test(ListContentTags::class)->instance()->getTable()->getRecordActions(), 1],
        'homepage sections' => [Livewire::test(ListHomepageSections::class)->instance()->getTable()->getRecordActions(), 1],
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
            // Grouped actions render labelled inside their dropdown, so the
            // icon-only contract covers the ungrouped ones (the same shape
            // the Media table already uses).
            if ($action instanceof ActionGroup) {
                expect($action->isIconButton())->toBeTrue();

                $icons[] = 'action-group';

                continue;
            }

            expect($action)->toBeInstanceOf(Action::class)
                ->and($action->isIconButton())->toBeTrue()
                ->and($action->isLabelHidden())->toBeTrue()
                ->and($action->getIcon())->toBeInstanceOf(Heroicon::class)
                ->and((string) $action->getTooltip())->toBe((string) $action->getLabel());

            $icons[] = $action->getIcon()->value;
        }

        expect(array_unique($icons))->toHaveCount($expectedCount, "{$surface} duplicate icons");
    }

    expect($actionCount)->toBe(28);
});

it('gives Media one visible details action one quiet grouped action menu and a status chip', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());
    $table = Livewire::test(ListMedia::class)->instance()->getTable();
    $recordActions = array_values($table->getRecordActions());

    expect($recordActions)->toHaveCount(4)
        ->and($recordActions[0]->getName())->toBe('mediaDetails')
        ->and($recordActions[1])->toBeInstanceOf(EditAction::class)
        ->and($recordActions[1]->getName())->toBe('edit')
        ->and($recordActions[1]->isIconButton())->toBeTrue()
        ->and((string) $recordActions[1]->getLabel())->toBe(__('admin.media_library.open_edit_page'))
        ->and((string) $recordActions[1]->getTooltip())->toBe(__('admin.media_library.open_edit_page'))
        ->and($recordActions[1]->getIcon())->toBe(Heroicon::OutlinedPencilSquare)
        ->and($recordActions[2])->toBeInstanceOf(ActionGroup::class)
        ->and($recordActions[2]->isIconButton())->toBeTrue()
        ->and((string) $recordActions[2]->getLabel())->toBe(__('admin.media_library.more_actions'))
        ->and((string) $recordActions[2]->getTooltip())->toBe(__('admin.media_library.more_actions'))
        ->and($recordActions[2]->getColor())->toBe('gray')
        ->and(array_keys($recordActions[2]->getFlatActions()))->toBe([
            'reviewIssues',
            'view',
            'download',
            'titleByOwner',
            'rename',
            'swap',
            'delete',
        ])
        ->and($recordActions[3]->getName())->toBe('cardStatus')
        ->and(array_keys($table->getFlatRecordActions()))->toBe([
            'mediaDetails',
            'edit',
            'reviewIssues',
            'view',
            'download',
            'titleByOwner',
            'rename',
            'swap',
            'delete',
            'cardStatus',
        ])
        ->and($table->getRecordActionsPosition())->toBe(RecordActionsPosition::AfterContent);
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
