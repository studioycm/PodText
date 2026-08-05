<?php

use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Support\NavigationBadgeCount;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
    NavigationBadgeCount::forget('episodes');
    NavigationBadgeCount::forget('media');
});

it('counts episodes and media in the sidebar, and stays silent at zero', function (): void {
    // A badge reading "0" is noise, not news.
    expect(ContentItemResource::getNavigationBadge())->toBeNull()
        ->and(MediaResource::getNavigationBadge())->toBeNull();

    ContentItem::factory()->count(3)->create();
    NavigationBadgeCount::forget('episodes');

    expect(ContentItemResource::getNavigationBadge())->toBe('3')
        ->and(ContentItemResource::getNavigationBadgeColor())->toBe('gray');

    foreach (['admin.resources.content_item.navigation_badge_tooltip', 'admin.curator.navigation_badge_tooltip'] as $key) {
        foreach (['en', 'he'] as $locale) {
            expect(Lang::has($key, $locale))->toBeTrue("missing {$key} in {$locale}");
        }
    }
});

it('caches the sidebar count so browsing does not re-run it', function (): void {
    ContentItem::factory()->count(2)->create();

    expect(ContentItemResource::getNavigationBadge())->toBe('2');

    // Filament has no deferred-badge API for navigation items, so the short
    // cache is the substitute — a new row is not visible until it expires
    // or something forgets the key.
    ContentItem::factory()->create();

    expect(ContentItemResource::getNavigationBadge())->toBe('2');

    NavigationBadgeCount::forget('episodes');

    expect(ContentItemResource::getNavigationBadge())->toBe('3');
});

it('defers every scope tab badge so the first paint is not blocked', function (): void {
    ContentItem::factory()->count(2)->create();

    $tabs = Livewire::test(ListContentItems::class)->instance()->getTabs();

    expect($tabs)->not->toBeEmpty();

    foreach ($tabs as $key => $tab) {
        expect($tab->isBadgeDeferred())->toBeTrue("tab [{$key}] must defer its badge");
    }
});

it('defers relation manager tab badges too', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group, 'contentGroup')->create();

    // Filament sets deferral inside getTabComponent() from $isBadgeDeferred,
    // which outranks the global Tab default — so each relation manager has
    // to opt in on its own class, and both of ours do.
    expect(ContentItemsRelationManager::getTabComponent($group, EditContentGroup::class)->isBadgeDeferred())->toBeTrue()
        ->and(TranscriptionsRelationManager::getTabComponent($item, EditContentItem::class)->isBadgeDeferred())->toBeTrue();
});

it('computes the six scope counts in one query however many badges ask', function (): void {
    ContentItem::factory()->count(4)->published()->withTranscription()->create();

    $page = Livewire::test(ListContentItems::class)->instance();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    // Six deferred badges resolve off one shared, memoized service.
    $badges = collect($page->getTabs())
        ->map(fn ($tab): mixed => $tab->getBadge())
        ->all();

    expect($queries)->toBe(1)
        ->and((int) $badges['all'])->toBe(4);
});
