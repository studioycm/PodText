<?php

use App\Enums\EpisodeListScope;
use App\Enums\EpisodePinScope;
use App\Enums\EpisodePublicState;
use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Filament\Actions\EditEffectiveTranscriptionAction;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\ContentItems\EpisodeListScopeQuery;
use App\Support\PublicContent\PublicTranscriptionSelector;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
});

it('resolves the public-state badge in parity with the scope queries', function (): void {
    $publishedGroup = ContentGroup::factory()->published();
    $draftGroup = ContentGroup::factory();

    $matrix = [
        [ContentItem::factory()->for($draftGroup, 'contentGroup')->create(), EpisodePublicState::Draft],
        [ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')->withTranscription()->create(), EpisodePublicState::Visible],
        [ContentItem::factory()->published(now()->addDays(2))->for($publishedGroup, 'contentGroup')->withTranscription()->create(), EpisodePublicState::Scheduled],
        [ContentItem::factory()->published()->for($draftGroup, 'contentGroup')->withTranscription()->create(), EpisodePublicState::BlockedGroup],
        [ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')
            ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create(), EpisodePublicState::BlockedTranscription],
        // Both blockers: the podcast wins the label — it is the upstream fix.
        [ContentItem::factory()->published()->for($draftGroup, 'contentGroup')
            ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create(), EpisodePublicState::BlockedGroup],
    ];

    foreach ($matrix as [$episode, $expected]) {
        expect(EpisodePublicState::for($episode->fresh()->load('contentGroup')))->toBe($expected);
    }
});

it('renders the list with a fixed query budget regardless of row count', function (): void {
    $countQueries = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        Livewire::test(ListContentItems::class)->assertSuccessful();

        return $queries;
    };

    ContentItem::factory()->count(3)->published()->withTranscription()->create();

    // Warm request-transcending caches (the provider-options 60s cache) so
    // both measured renders run the same cached-vs-fresh mix.
    Livewire::test(ListContentItems::class)->assertSuccessful();

    $baseline = $countQueries();

    ContentItem::factory()->count(12)->published()->withTranscription()->create();
    $wide = $countQueries();

    expect($wide)->toBe($baseline);
});

it('shows how many rows the current view matched, at the top and for free', function (): void {
    ContentItem::factory()->count(7)->published()->withTranscription()->create();
    ContentItem::factory()->count(3)->create(['title' => 'ZZZ needle']);

    // Render hooks only fire on the real panel render, not under
    // Livewire::test — so this half has to go over HTTP or it proves nothing.
    $this->get(ContentItemResource::getUrl('index'))
        ->assertOk()
        ->assertSee('episodes-toolbar-record-count', escape: false)
        ->assertSee(trans_choice('admin.episodes.toolbar_record_count', 10, ['count' => '10']), escape: false);

    // Arrive scoped, and the number must follow the view rather than the
    // library — ten rows exist, the drafts tab shows three. Without this the
    // count could be a plain model count and nothing would notice.
    $this->get(ContentItemResource::getUrl('index').'?tab='.EpisodeListScope::Drafts->value)
        ->assertOk()
        ->assertSee(trans_choice('admin.episodes.toolbar_record_count', 3, ['count' => '3']), escape: false)
        ->assertDontSee(trans_choice('admin.episodes.toolbar_record_count', 10, ['count' => '10']), escape: false);

    // The number the hook prints is the table's own, so it follows search,
    // filters and the active tab — and costs nothing once rows have resolved.
    $page = Livewire::test(ListContentItems::class)->set('tableSearch', 'ZZZ needle');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($page->instance()->getAllTableRecordsCount())->toBe(3)
        ->and($queries)->toBe(0);

    foreach (['en', 'he'] as $locale) {
        expect(Lang::has('admin.episodes.toolbar_record_count', $locale))->toBeTrue("missing count label in {$locale}");
    }
});

it('keeps the transcriber column flat in queries however many rows it renders', function (): void {
    // This path used to cost one query PER ROW and say nothing: it reached
    // relations by method call, which preventLazyLoading cannot see, so 25
    // rows quietly became 51 queries. The column now declares its own eager
    // loading through Filament's per-visible-column hook, so the cost is flat.
    $showTranscribers = function (): int {
        $page = Livewire::test(ListContentItems::class);
        $state = collect($page->instance()->getDefaultTableColumnState())
            ->map(function (array $column): array {
                if ($column['name'] === 'effective_transcribers') {
                    $column['isToggled'] = true;
                }

                return $column;
            })
            ->all();
        $page->call('applyTableColumnManager', $state);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $page->call('$refresh')->assertSuccessful();

        return $queries;
    };

    ContentItem::factory()->count(3)->published()->withTranscription()->create();
    Livewire::test(ListContentItems::class)->assertSuccessful();
    $narrow = $showTranscribers();

    ContentItem::factory()->count(12)->published()->withTranscription()->create();

    expect($showTranscribers())->toBe($narrow);
});

it('loads transcription relations only when a column actually reads one', function (): void {
    ContentItem::factory()->count(4)->published()->withTranscription()->create();

    $page = Livewire::test(ListContentItems::class)->assertSuccessful();

    // The default view showed none of the columns that read a transcription
    // model, yet primed four relations for all of them. The public-state
    // verdict does not need them — it reads the withExists flags — so the
    // default page can go without.
    expect($page->instance()->getTableRecords()->first()->relationLoaded('latestPublishedTranscription'))->toBeFalse()
        ->and($page->instance()->getTableRecords()->first()->relationLoaded('featuredTranscription'))->toBeFalse();

    $withTranscribersOn = collect($page->instance()->getDefaultTableColumnState())
        ->map(function (array $column): array {
            if ($column['name'] === 'effective_transcribers') {
                $column['isToggled'] = true;
            }

            return $column;
        })
        ->all();

    // And the moment one is switched on it must come back, or the column
    // would lazy-load per row — which preventLazyLoading turns into a crash.
    $page->call('applyTableColumnManager', $withTranscribersOn)->assertSuccessful();

    expect($page->instance()->getTableRecords()->first()->relationLoaded('latestPublishedTranscription'))->toBeTrue();
});

it('lets a dotted column load its own relation, and only that one', function (): void {
    ContentItem::factory()->count(3)->published()->withTranscription()->create();

    $page = Livewire::test(ListContentItems::class);

    $withFeaturedTitleOn = collect($page->instance()->getDefaultTableColumnState())
        ->map(function (array $column): array {
            if ($column['name'] === 'featuredTranscription.title') {
                $column['isToggled'] = true;
            }

            return $column;
        })
        ->all();

    $page->call('applyTableColumnManager', $withFeaturedTitleOn)->assertSuccessful();

    $record = $page->instance()->getTableRecords()->first();

    // Filament eager-loads for visible DOTTED columns by itself, so this one
    // needs no help from the prime — and must not get it. Naming it in
    // TRANSCRIPTION_DEPENDENT_COLUMNS would drag in three relations and both
    // author sets that nothing on screen reads.
    expect($record->relationLoaded('featuredTranscription'))->toBeTrue()
        ->and($record->relationLoaded('latestPublishedTranscription'))->toBeFalse();
});

it('adds transcript columns that stay off by default', function (): void {
    $table = Livewire::test(ListContentItems::class)->instance()->getTable();

    foreach (['transcriptions_sum_word_count', 'transcript_reading_minutes', 'latestPublishedTranscription.published_at'] as $name) {
        $column = $table->getColumn($name);

        expect($column)->not->toBeNull("column [{$name}] must exist")
            // Filament applies aggregates and relation eager loads only for
            // VISIBLE columns, so off-by-default is what makes them free.
            ->and($column->isToggledHiddenByDefault())->toBeTrue("column [{$name}] must stay off by default");
    }
});

it('keeps the transcript word sum at one query however many episodes there are', function (): void {
    // The real risk in a derived column is per-row cost. An aggregate is one
    // subquery at any row count; a state closure reaching a relation would be
    // one query per row — the shape that produced the recorded authors N+1.
    $countSumQueries = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        ContentItemsTable::primeEpisodeQuery(ContentItem::query())
            ->withSum('transcriptions', 'word_count')
            ->get()
            ->each(fn (ContentItem $item): mixed => $item->transcriptions_sum_word_count);

        return $queries;
    };

    ContentItem::factory()->count(3)->published()->withTranscription()->create();
    $narrow = $countSumQueries();

    ContentItem::factory()->count(12)->published()->withTranscription()->create();

    expect($countSumQueries())->toBe($narrow);
});

it('sums transcript words and turns them into reading minutes', function (): void {
    $episode = ContentItem::factory()
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription([
            'status' => PublicationStatus::Published,
            'published_at' => '2026-07-04 09:00:00',
            'transcript_markdown' => str_repeat('מילה ', 450),
        ])
        ->published('2026-08-01 06:00:00')
        ->create();

    $row = ContentItemsTable::primeEpisodeQuery(ContentItem::query())
        ->withSum('transcriptions', 'word_count')
        ->with('latestPublishedTranscription')
        ->findOrFail($episode->getKey());

    $words = (int) $row->transcriptions_sum_word_count;

    expect($words)->toBe(450)
        // 450 words at 200 wpm rounds up to three minutes.
        ->and(app(PublicTranscriptionSelector::class)->readingMinutes($words))->toBe(3)
        // The transcript's own date, which is not the episode's.
        ->and($row->latestPublishedTranscription->published_at->toDateTimeString())->toBe('2026-07-04 09:00:00')
        ->and($row->published_at->toDateTimeString())->toBe('2026-08-01 06:00:00');
});

it('publishes from one click on the status cell, stamping the date', function (): void {
    Carbon::setTestNow('2026-08-05 15:00:00');

    $episode = ContentItem::factory()
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    expect($episode->status)->toBe(PublicationStatus::Draft);

    Livewire::test(ListContentItems::class)
        ->callAction(TestAction::make('togglePublication')->table($episode))
        ->assertNotified(__('admin.notifications.episode_visible'));

    $episode->refresh();

    expect($episode->status)->toBe(PublicationStatus::Published)
        ->and($episode->published_at->toDateTimeString())->toBe('2026-08-05 15:00:00');
});

it('unpublishes on the next click, and keeps the stamp the form would keep', function (): void {
    $episode = ContentItem::factory()->published('2026-08-01 06:00:00')->create();

    Livewire::test(ListContentItems::class)
        ->callAction(TestAction::make('togglePublication')->table($episode))
        ->assertNotified(__('admin.notifications.episode_unpublished'));

    $episode->refresh();

    // PublicationDateAutofill never clears the date on the way down, so the
    // cell and the form agree about what unpublishing means.
    expect($episode->status)->toBe(PublicationStatus::Draft)
        ->and($episode->published_at?->toDateTimeString())->toBe('2026-08-01 06:00:00');
});

it('reports the blocker honestly when a one-click publish cannot go live', function (): void {
    $episode = ContentItem::factory()
        ->for(ContentGroup::factory(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    Livewire::test(ListContentItems::class)
        ->callAction(TestAction::make('togglePublication')->table($episode))
        ->assertNotified(__('admin.notifications.episode_blocked_group'));

    expect($episode->refresh()->status)->toBe(PublicationStatus::Published);
});

it('restores the previous status when the notification undo is used', function (): void {
    $episode = ContentItem::factory()
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    $page = Livewire::test(ListContentItems::class)
        ->callAction(TestAction::make('togglePublication')->table($episode));

    expect($episode->refresh()->status)->toBe(PublicationStatus::Published);

    $page->dispatch('undoPublicationToggle', $episode->getKey(), PublicationStatus::Draft->value);

    expect($episode->refresh()->status)->toBe(PublicationStatus::Draft);
});

it('ignores an undo carrying a status that is not a real case', function (): void {
    // The undo payload is browser-writable, so it is narrowed at the door:
    // this is where the old forged-inline-status guard now lives.
    $episode = ContentItem::factory()->published('2026-08-01 06:00:00')->create();

    Livewire::test(ListContentItems::class)
        ->dispatch('undoPublicationToggle', $episode->getKey(), 'forged-status');

    expect($episode->refresh()->status)->toBe(PublicationStatus::Published);
});

it('gives the podcast episodes table the same one-click status cell, undo included', function (): void {
    // The two tables share statusToggleColumn(), so a rename breaks the
    // relation manager silently until a page renders — which is exactly how
    // this guard came to exist. Undo is a Livewire listener, so sharing the
    // column is not enough; the component has to listen too.
    $group = ContentGroup::factory()->published()->create();
    $episode = ContentItem::factory()
        ->for($group, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    $manager = Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])->callAction(TestAction::make('togglePublication')->table($episode));

    expect($episode->refresh()->status)->toBe(PublicationStatus::Published);

    $manager->dispatch('undoPublicationToggle', $episode->getKey(), PublicationStatus::Draft->value);

    expect($episode->refresh()->status)->toBe(PublicationStatus::Draft);
});

it('keeps the whole episodes list away from anyone who cannot update one', function (): void {
    // The real gate for one-click publishing is page access, because the
    // policy answers viewAny and update with the same predicate. The
    // listener's own Gate check is defence for the day those diverge; it is
    // deliberately not asserted here, because no role can currently reach
    // the page without also passing it, and a test that cannot fail is
    // worse than an honest gap.
    $this->actingAs(User::factory()->create(['role' => UserRole::Transcriber->value]));

    $this->get(ContentItemResource::getUrl('index'))->assertForbidden();
});

it('reschedules from the date cell modal in the Jerusalem timezone', function (): void {
    $episode = ContentItem::factory()->published('2026-08-01 06:00:00')->create();

    Livewire::test(ListContentItems::class)
        ->callAction(
            TestAction::make('changePublishedAt')->table($episode),
            ['published_at' => '2026-08-10 09:30'],
        )
        ->assertNotified();

    // 09:30 entered in Asia/Jerusalem (UTC+3 in August) stores as 06:30 UTC.
    expect($episode->refresh()->published_at->toDateTimeString())->toBe('2026-08-10 06:30:00');
});

it('offers the publish-podcast remedy only on group-blocked rows and stamps the podcast date', function (): void {
    Carbon::setTestNow('2026-08-05 16:00:00');

    $draftGroup = ContentGroup::factory()->create(['published_at' => null]);
    $blocked = ContentItem::factory()->published()->for($draftGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create();
    $visible = ContentItem::factory()->published()->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create();

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('publishBlockingPodcast')->table($blocked))
        ->assertActionHidden(TestAction::make('publishBlockingPodcast')->table($visible))
        ->callAction(TestAction::make('publishBlockingPodcast')->table($blocked))
        ->assertNotified(__('admin.notifications.episode_visible'));

    $draftGroup->refresh();

    expect($draftGroup->status)->toBe(PublicationStatus::Published)
        ->and($draftGroup->published_at->toDateTimeString())->toBe('2026-08-05 16:00:00');
});

it('points transcript-blocked rows at the workspace', function (): void {
    $blocked = ContentItem::factory()->published()
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create();
    $draft = ContentItem::factory()->create();

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('openBlockedTranscript')->table($blocked))
        ->assertActionHidden(TestAction::make('openBlockedTranscript')->table($draft));
});

it('keeps every column toggleable except the title, with the column manager on', function (): void {
    ContentItem::factory()->create();

    $table = Livewire::test(ListContentItems::class)->instance()->getTable();

    expect($table->hasReorderableColumns())->toBeTrue()
        ->and($table->getDefaultSortColumn())->toBe('updated_at')
        ->and($table->getDefaultSortDirection())->toBe('desc');

    foreach ($table->getColumns() as $column) {
        if ($column->getName() === 'title') {
            expect($column->isToggleable())->toBeFalse();

            continue;
        }

        expect($column->isToggleable())
            ->toBeTrue("column {$column->getName()} must be toggleable (P-EL8)");
    }

    expect(array_keys($table->getGroups()))->toBe(['contentGroup.title', 'status']);
});

it('filters by the pinned toggle group and the Jerusalem-walled date range', function (): void {
    $pinned = ContentItem::factory()->pinned()->create();
    $plain = ContentItem::factory()->create();

    Livewire::test(ListContentItems::class)
        ->filterTable('is_pinned', ['value' => 'pinned'])
        ->assertCanSeeTableRecords([$pinned])
        ->assertCanNotSeeTableRecords([$plain])
        ->resetTableFilters();

    // 22:30 UTC on Aug 4 is already Aug 5, 01:30 in Jerusalem — a UTC-day
    // implementation would wrongly exclude it from "published from Aug 5".
    $nearMidnight = ContentItem::factory()->published(Carbon::parse('2026-08-04 22:30:00', 'UTC'))->create();
    $earlier = ContentItem::factory()->published(Carbon::parse('2026-08-01 10:00:00', 'UTC'))->create();

    Livewire::test(ListContentItems::class)
        ->filterTable('published_between', ['published_from' => '2026-08-05'])
        ->assertCanSeeTableRecords([$nearMidnight])
        ->assertCanNotSeeTableRecords([$earlier]);
});

it('narrows forged filter dates instead of crashing the list', function (mixed $forged): void {
    $episode = ContentItem::factory()->published('2026-08-01 06:00:00')->create();

    // Table filter state is raw browser input — reachable straight from the
    // query string — so garbage must narrow, never reach Carbon.
    Livewire::test(ListContentItems::class)
        ->set('tableFilters.published_between.published_from', $forged)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$episode]);
})->with([
    'unparsable string' => ['forged-date'],
    'array' => [['x']],
    'integer' => [42],
    'boolean' => [true],
]);

it('narrows a forged tab arriving through the query string', function (): void {
    $episode = ContentItem::factory()->create();

    // `$activeTab` is #[Url]-bound, and Livewire does not fire updated*
    // hooks for URL hydration — the mount door is the one that matters.
    Livewire::withQueryParams(['tab' => 'forged-scope'])
        ->test(ListContentItems::class)
        ->assertSet('activeTab', EpisodeListScope::All->value)
        ->assertCanSeeTableRecords([$episode]);
});

it('publishes a podcast that blocks by a future date, not just a draft one', function (): void {
    Carbon::setTestNow('2026-08-05 16:00:00');

    $futureDatedGroup = ContentGroup::factory()->published('2026-12-01 00:00:00')->create();
    $blocked = ContentItem::factory()->published('2026-08-01 08:00:00')
        ->for($futureDatedGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => '2026-08-01 08:00:00'])
        ->create();

    expect(EpisodePublicState::for($blocked->fresh()->load('contentGroup')))
        ->toBe(EpisodePublicState::BlockedGroup);

    Livewire::test(ListContentItems::class)
        ->callAction(TestAction::make('publishBlockingPodcast')->table($blocked))
        ->assertNotified(__('admin.notifications.episode_visible'));

    // The remedy must actually unblock — a confirmation that changes nothing
    // is worse than no remedy at all.
    expect(EpisodePublicState::for($blocked->fresh()->load('contentGroup')))
        ->toBe(EpisodePublicState::Visible)
        ->and($futureDatedGroup->refresh()->published_at->toDateTimeString())
        ->toBe('2026-08-05 16:00:00');
});

it('filters unpinned episodes and returns to all when the pin filter is reset', function (): void {
    $pinned = ContentItem::factory()->pinned()->create();
    $plain = ContentItem::factory()->create();

    Livewire::test(ListContentItems::class)
        ->filterTable('is_pinned', ['value' => EpisodePinScope::Unpinned->value])
        ->assertCanSeeTableRecords([$plain])
        ->assertCanNotSeeTableRecords([$pinned])
        ->removeTableFilter('is_pinned')
        // Resetting returns the toggle to «הכל» rather than leaving it blank.
        ->assertSet('tableFilters.is_pinned.value', EpisodePinScope::All->value)
        ->assertCanSeeTableRecords([$pinned, $plain]);
});

it('keeps the dashboard status doorways working through the toggle filter', function (): void {
    $draft = ContentItem::factory()->create();
    $published = ContentItem::factory()->published()->create();

    // The funnel and stats widgets link with filters[status][value]; the
    // toggle filter keeps that exact state path, so the doorways must still
    // land on a scoped list rather than silently showing everything.
    // Driven through the query string, because that is how a doorway
    // actually arrives — DashboardOverviewLensTest pins the URL those
    // widgets emit, and this pins that the list honours it.
    Livewire::withQueryParams(['filters' => ['status' => ['value' => PublicationStatus::Draft->value]]])
        ->test(ListContentItems::class)
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$published]);

    Livewire::withQueryParams(['filters' => ['status' => ['value' => PublicationStatus::Published->value]]])
        ->test(ListContentItems::class)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$draft]);

    // «All» is the resting state, not a filter.
    Livewire::test(ListContentItems::class)
        ->filterTable('status', ['value' => 'all'])
        ->assertCanSeeTableRecords([$draft, $published]);
});

it('labels the filter and column-manager triggers in both languages', function (): void {
    ContentItem::factory()->create();

    $table = Livewire::test(ListContentItems::class)->instance()->getTable();

    // Among search and grouping, a bare icon reads as decoration — these two
    // carry their names, and the names exist in both locales.
    expect((string) $table->getFiltersTriggerAction()->getLabel())->toBe(__('admin.tables.filters_trigger'))
        ->and((string) $table->getColumnManagerTriggerAction()->getLabel())->toBe(__('admin.tables.columns_trigger'));

    foreach (['filters_trigger', 'columns_trigger'] as $key) {
        foreach (['en', 'he'] as $locale) {
            expect(Lang::has("admin.tables.{$key}", $locale))
                ->toBeTrue("missing admin.tables.{$key} in {$locale}");
        }
    }
});

it('puts the filters trigger on the toolbar row and leaves the native one for the CSS to hide', function (): void {
    ContentItem::factory()->count(2)->published()->withTranscription()->create();

    // Render hooks do not fire under Livewire::test — only a real panel
    // request runs them. Written the Livewire way this test would pass while
    // asserting nothing at all, so it goes over HTTP.
    $html = $this->get(ContentItemResource::getUrl('index'))->assertOk()->getContent();

    expect($html)->toContain('podtext-toolbar-filters-trigger');

    // Anchored to the re-homed button, not to the page: the native trigger
    // carries the same word, so a loose assertion on the label alone would
    // still pass with the hook gone entirely.
    $rehomedTrigger = Str::before(Str::after($html, 'podtext-toolbar-filters-trigger'), '</button>');

    expect($rehomedTrigger)->toContain(__('admin.tables.filters_trigger'));

    // Re-homed means inside the toolbar row, beside the column manager it is
    // registered before — not merely present somewhere on the page.
    expect(strpos($html, 'fi-ta-header-toolbar'))
        ->toBeLessThan(strpos($html, 'podtext-toolbar-filters-trigger'));

    // The native trigger is not removed, only hidden — by a theme rule keyed
    // on the container Filament wraps it in. If Filament renames that
    // container the rule stops matching and the page grows a second,
    // duplicate trigger, which no other test would notice.
    expect($html)->toContain('fi-ta-filters-above-content-ctn');
});

it('speaks both languages for the public-state vocabulary', function (): void {
    foreach (EpisodePublicState::cases() as $state) {
        foreach (['en', 'he'] as $locale) {
            expect(Lang::has("admin.episode_public_state.{$state->value}", $locale))
                ->toBeTrue("missing admin.episode_public_state.{$state->value} in {$locale}");
        }
    }
});

it('raises rather than blanks when the transcript context relations are missing', function (): void {
    // A blank badge and "this episode has no effective transcript" are
    // opposite editorial facts, and the guards used to answer the first with
    // the second — to an admin deciding whether to publish. The relations are
    // a contract between primeEpisodeQuery and the columns that read them, so
    // a breach must reach the app's lazy-loading policy, not the screen.
    ContentItem::factory()->count(2)
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    // Two rows, because Laravel arms preventLazyLoading only when it hydrates
    // more than one — a single-row fetch is never flagged.
    $unprimed = ContentItem::query()->get();

    expect(fn (): ?string => EditEffectiveTranscriptionAction::contextStateFor($unprimed->first()))
        ->toThrow(LazyLoadingViolationException::class);

    // And with the contract honoured it answers, so the fix is not "throw
    // always" — that mutation would fail here.
    $primed = ContentItemsTable::primeEpisodeQuery(ContentItem::query())
        ->with(['featuredTranscription', 'latestPublishedTranscription'])
        ->get();

    expect(EditEffectiveTranscriptionAction::contextStateFor($primed->first()))->toBeString();
});

it('explains the difference between published and visible wherever both words appear', function (): void {
    // «פורסם» (the episode's own switch) and «גלוי» (what the public can
    // actually reach) read as synonyms and are not. All three surfaces that
    // show one of the two words point at the same explanation, so they cannot
    // drift into three slightly different stories.
    $hint = __('admin.helpers.status_versus_visible');
    $episode = ContentItem::factory()->create();
    $table = Livewire::test(ListContentItems::class)->instance()->getTable();

    expect($table->getColumn('public_state')->record($episode)->getTooltip())->toBe($hint)
        ->and($table->getColumn('status')->record($episode)->getTooltip())->not->toBe($hint);

    // The status cell's own tooltip is the click affordance while the user
    // may act; it falls back to the explanation when they may not.
    $this->actingAs(User::factory()->create(['role' => UserRole::Transcriber->value]));

    expect($table->getColumn('status')->record($episode)->getTooltip())->toBe($hint);

    foreach (['en', 'he'] as $locale) {
        expect(Lang::has('admin.helpers.status_versus_visible', $locale))->toBeTrue("missing hint in {$locale}");
    }
});

it('retires the pinned tab in favour of the tri-state pin filter', function (): void {
    // Pinning is an attribute an episode carries, not a stage it passes
    // through, and the filter answers a question the tab could not: «לא
    // מוצמדים». Two doors onto one question, and this keeps the narrower one.
    $tabs = array_keys(Livewire::test(ListContentItems::class)->instance()->getTabs());

    expect($tabs)->not->toContain(EpisodeListScope::Pinned->value)
        ->and($tabs)->toContain(EpisodeListScope::All->value);

    // Structural, not a hand-list: every scope the enum says is a tab must be
    // one, so a new scope cannot be added and silently left out of the page.
    $expected = collect(EpisodeListScope::cases())
        ->filter(fn (EpisodeListScope $scope): bool => $scope->showsAsTab())
        ->map(fn (EpisodeListScope $scope): string => $scope->value)
        ->values()
        ->all();

    expect($tabs)->toEqualCanonicalizing($expected);
});

it('lands a stale ?tab=pinned bookmark on a tab that exists', function (): void {
    // `pinned` is still a valid EpisodeListScope, so enum narrowing alone lets
    // it through — and it would select a tab the page no longer renders,
    // showing an empty table under no visible tab.
    $page = Livewire::withQueryParams(['tab' => EpisodeListScope::Pinned->value])->test(ListContentItems::class);

    expect($page->instance()->activeTab)->toBe(EpisodeListScope::All->value);
});

it('still counts the pinned scope even though it has no tab', function (): void {
    // The scope did not go away — the doorways and the two triage scopes still
    // lean on the same pin window. Only the tab did.
    ContentItem::factory()->create(['is_pinned' => true, 'pinned_at' => now()->subDay(), 'pinned_until' => null]);

    expect(app(EpisodeListScopeQuery::class)->counts())
        ->toHaveKey(EpisodeListScope::Pinned->value)
        ->and(app(EpisodeListScopeQuery::class)->counts()[EpisodeListScope::Pinned->value])->toBe(1);
});
