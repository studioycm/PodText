<?php

use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Widgets\BlockersQueueWidget;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->superAdmin()->create());
});

/**
 * Every concrete admin table component that must namespace its own URL
 * pagination key. Resource ListRecords pages are excluded on purpose — their
 * `filters`/`search`/`sort` keys are static #[Url] bindings an identifier
 * cannot rename, they are single-table screens, and ListMedia reads the bare
 * `page` request parameter in mount(). Relation managers and
 * ManageRelatedRecords pages are excluded because Filament already derives
 * their identifier in InteractsWithRelationshipTable (and they cannot mount
 * without an owner record).
 *
 * @return array<int, class-string<HasTable>>
 */
function tableComponentsUnderAdminConvention(): array
{
    return collect(File::allFiles(app_path('Filament')))
        ->map(fn (SplFileInfo $file): string => 'App\\Filament\\'.Str::of($file->getRelativePathname())
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->beforeLast('.php')
            ->toString())
        ->filter(fn (string $class): bool => class_exists($class)
            && is_subclass_of($class, HasTable::class)
            && ! (new ReflectionClass($class))->isAbstract()
            && ! is_subclass_of($class, ListRecords::class)
            && ! is_subclass_of($class, RelationManager::class)
            && ! is_subclass_of($class, ManageRelatedRecords::class))
        ->values()
        ->all();
}

it('reads only its own derived pagination key on the importer connections table', function (): void {
    $page = Livewire::withQueryParams(['importerSettingsPage' => 3, 'page' => 7])
        ->test(ImporterSettings::class)
        ->instance();

    expect($page->getTable()->getQueryStringIdentifier())->toBe('importerSettings')
        ->and($page->getTablePaginationPageName())->toBe('importerSettingsPage')
        // Its own key seeds the paginator; the bare `page=7` is invisible.
        ->and($page->getTablePage())->toBe(3)
        ->and($page->queryStringHandlesPagination())
        ->toHaveKey('paginators.importerSettingsPage')
        ->and($page->queryStringHandlesPagination()['paginators.importerSettingsPage']['as'])
        ->toBe('importerSettingsPage');
});

it('keeps the blockers queue widget off the dashboard page bare query string keys', function (): void {
    $widget = Livewire::withQueryParams(['page' => 7, 'filters' => ['podcast' => '9']])
        ->test(BlockersQueueWidget::class)
        ->instance();

    // The Dashboard page owns the bare `filters` key through `#[Url] $filters`;
    // the widget table's URL surface is its derived pagination key only.
    expect($widget->getTable()->getQueryStringIdentifier())->toBe('blockersQueueWidget')
        ->and($widget->getTablePaginationPageName())->toBe('blockersQueueWidgetPage')
        ->and($widget->getTablePage())->toBe(1)
        ->and((array) $widget->tableFilters)->not->toHaveKey('podcast');
});

it('seeds the blockers queue widget page from its own namespaced key', function (): void {
    $widget = Livewire::withQueryParams(['blockersQueueWidgetPage' => 2])
        ->test(BlockersQueueWidget::class)
        ->instance();

    expect($widget->getTablePage())->toBe(2);
});

it('moves the card template library dormant paginator off the bare page key', function (): void {
    $page = Livewire::test(CardTemplateSettings::class)->instance();

    // The library table is unpaginated custom data, yet rendering still
    // initializes its Livewire paginator and registers the URL binding — so
    // without the convention this page held the bare `page` binding for a
    // paginator it never advances.
    expect($page->getTable()->getQueryStringIdentifier())->toBe('cardTemplateSettings')
        ->and($page->getTablePaginationPageName())->toBe('cardTemplateSettingsPage')
        ->and($page->getTable()->isPaginated())->toBeFalse()
        ->and($page->queryStringHandlesPagination())
        ->toHaveKey('paginators.cardTemplateSettingsPage')
        ->not->toHaveKey('paginators.page');
});

it('keeps resource list pages on Filament bare query string keys so bookmarks survive', function (): void {
    $page = Livewire::withQueryParams(['page' => 2])
        ->test(ListAuthors::class)
        ->instance();

    expect($page->getTable()->getQueryStringIdentifier())->toBeNull()
        ->and($page->getTablePaginationPageName())->toBe('page')
        ->and($page->getTablePage())->toBe(2);
});

it('keeps relation managers on the identifier Filament derives for them', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();

    $manager = Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item,
        'pageClass' => EditContentItem::class,
    ])->instance();

    expect($manager->getTable()->getQueryStringIdentifier())->toBe('transcriptionsRelationManager')
        ->and($manager->getTablePaginationPageName())->toBe('transcriptionsRelationManagerPage');
});

it('namespaces every admin table component outside resource list pages', function (): void {
    $components = tableComponentsUnderAdminConvention();

    expect($components)->toContain(ImporterSettings::class)
        ->toContain(CardTemplateSettings::class)
        ->toContain(BlockersQueueWidget::class);

    $seen = [];

    foreach ($components as $component) {
        $identifier = Livewire::test($component)->instance()->getTable()->getQueryStringIdentifier();

        expect($identifier)->not->toBeNull(
            "{$component} hosts a table without a query string identifier; its pagination"
            .' key stays the bare `page` and can collide with anything else on the screen.',
        );

        expect($seen)->not->toContain(
            $identifier,
            "The table query string identifier '{$identifier}' is used by more than one admin component.",
        );

        $seen[] = $identifier;
    }
});
