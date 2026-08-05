<?php

use App\Enums\NavigationBadge;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\User;
use App\Support\NavigationBadgeCount;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    // Structural, not a hand-list: a new badge joins the clean slate by
    // existing, so it cannot be forgotten here and remembered nowhere.
    foreach (NavigationBadge::cases() as $badge) {
        NavigationBadgeCount::forget($badge);
    }
});

it('counts episodes and media in the sidebar, and stays silent at zero', function (): void {
    // A badge reading "0" is noise, not news.
    expect(ContentItemResource::getNavigationBadge())->toBeNull()
        ->and(MediaResource::getNavigationBadge())->toBeNull();

    ContentItem::factory()->count(3)->create();
    Media::factory()->count(2)->create();
    NavigationBadgeCount::forget(NavigationBadge::Episodes);
    NavigationBadgeCount::forget(NavigationBadge::Media);

    expect(ContentItemResource::getNavigationBadge())->toBe('3')
        ->and(ContentItemResource::getNavigationBadgeColor())->toBe('gray')
        ->and(MediaResource::getNavigationBadge())->toBe('2')
        ->and(MediaResource::getNavigationBadgeColor())->toBe('gray');

    foreach (['admin.resources.content_item.navigation_badge_tooltip', 'admin.curator.navigation_badge_tooltip'] as $key) {
        foreach (['en', 'he'] as $locale) {
            expect(Lang::has($key, $locale))->toBeTrue("missing {$key} in {$locale}");
        }
    }
});

it('counts unhandled submissions only, and wears the queue colour', function (): void {
    PublicFormSubmission::factory()->count(2)->create();
    PublicFormSubmission::factory()->reviewed()->create();

    // A work queue, not an inventory: the reviewed row exists but is handled.
    expect(PublicFormSubmissionResource::getNavigationBadge())->toBe('2')
        ->and(PublicFormSubmissionResource::getNavigationBadgeColor())->toBe('warning');

    foreach (['en', 'he'] as $locale) {
        expect(Lang::has('admin.resources.public_form_submission.navigation_badge_tooltip', $locale))->toBeTrue();
    }
});

it('drops a handled submission from the badge the moment its status changes', function (): void {
    $submissions = PublicFormSubmission::factory()->count(3)->create();

    expect(PublicFormSubmissionResource::getNavigationBadge())->toBe('3');

    // The one freshness path the badge cannot wait out: a status transition
    // writes through the model, so `saved` forgets the key and the next read
    // recomputes. Nothing here touches the cache by hand.
    $submissions->first()->markReviewed();

    expect(PublicFormSubmissionResource::getNavigationBadge())->toBe('2');
});

it('forgets the badge without halting sibling saved listeners', function (): void {
    // The trap this guards (Dispatcher's break-on-false) only bites listeners
    // registered AFTER the model's own, so boot the model first — booted()
    // runs on first use and must already own the earlier slot.
    PublicFormSubmission::factory()->create();

    $sibling = false;
    PublicFormSubmission::saved(function () use (&$sibling): void {
        $sibling = true;
    });

    PublicFormSubmission::factory()->create();

    expect($sibling)->toBeTrue(
        'the badge-forget listener returned false and killed every later eloquent.saved listener',
    );
});

it('caches a zero count instead of re-counting an empty badge', function (): void {
    // Caching the formatted string would cache `null` at zero, and a cached
    // null is indistinguishable from a miss — so the steady state of an empty
    // queue would re-run its COUNT on every render. Caching the int fixes it.
    expect(PublicFormSubmissionResource::getNavigationBadge())->toBeNull();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(PublicFormSubmissionResource::getNavigationBadge())->toBeNull()
        ->and($queries)->toBe(0);
});

it('gives every sidebar badge its own cache key', function (): void {
    $keys = collect(NavigationBadge::cases())
        ->map(fn (NavigationBadge $badge): string => NavigationBadgeCount::cacheKey($badge));

    expect($keys->unique()->count())->toBe($keys->count())
        ->and($keys->all())->each->toStartWith('admin.navigation-badge.');
});

it('keeps every sidebar badge cache call where FilaCheck can see it', function (): void {
    // FilaCheck Pro's navigation-badge-not-cached rule is a syntactic walk of
    // the getNavigationBadge() body — it follows no indirection. Moving the
    // Cache:: call into NavigationBadgeCount would fail the gate at every
    // site, so the helper owns the key/TTL/format and nothing more.
    $resources = [ContentItemResource::class, MediaResource::class, PublicFormSubmissionResource::class];

    foreach ($resources as $resource) {
        $body = (new ReflectionMethod($resource, 'getNavigationBadge'));
        $source = implode("\n", array_slice(
            file($body->getFileName()),
            $body->getStartLine() - 1,
            $body->getEndLine() - $body->getStartLine() + 1,
        ));

        expect(str_contains($source, 'Cache::'))
            ->toBeTrue("{$resource} must cache its badge where FilaCheck can see it");
    }
});

it('caches the sidebar count so browsing does not re-run it', function (): void {
    ContentItem::factory()->count(2)->create();

    expect(ContentItemResource::getNavigationBadge())->toBe('2');

    // Filament has no deferred-badge API for navigation items, so the short
    // cache is the substitute — a new row is not visible until it expires
    // or something forgets the key. Deliberate: episodes is an inventory
    // counter, and nothing waits on it. Contrast the submissions badge,
    // which is a queue and forgets its key on every write.
    ContentItem::factory()->create();

    expect(ContentItemResource::getNavigationBadge())->toBe('2');

    NavigationBadgeCount::forget(NavigationBadge::Episodes);

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

it('keeps import and export labelled in Hebrew away from a table', function (): void {
    app()->setLocale('he');

    // Off a table, these actions fall back to Str::plural() on the singular
    // label — and Filament reads Hebrew as a pluralising locale, so «פרק»
    // became «פרקs» when they moved to the page header.
    foreach (ContentItemsTable::intakeActions() as $action) {
        expect((string) $action->getLabel())->not->toContain('s')
            ->and((string) $action->getLabel())->toContain(__('admin.resources.content_item.plural'))
            ->and((string) $action->getModalHeading())->not->toContain('s');
    }
});

it('keeps app-authored tab containers keyed, because deferred badges need one', function (): void {
    // The key() requirement is a schema-Tabs rule: Filament's own containers
    // (resourceTabs, relationManagerTabs) set their own. But the global
    // Tab::configureUsing(deferBadge()) reaches schema tabs too, so a badge
    // inside an unkeyed container we author would silently never resolve.
    // No such container exists today — this is the tripwire for the first one.
    $files = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

    expect($files)->not->toBeEmpty();

    $unkeyed = $files
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'Tabs::make('))
        ->reject(fn (SplFileInfo $file): bool => str_contains($file->getContents(), '->key('))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($unkeyed)->toBe([]);
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
