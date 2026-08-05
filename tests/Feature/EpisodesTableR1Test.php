<?php

use App\Enums\EpisodeListScope;
use App\Enums\EpisodePinScope;
use App\Enums\EpisodePublicState;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
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

it('updates status inline, stamps the publish date, and tells the truth about visibility', function (): void {
    Carbon::setTestNow('2026-08-05 15:00:00');

    $episode = ContentItem::factory()
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    Livewire::test(ListContentItems::class)
        ->call('updateTableColumnState', 'status', (string) $episode->getKey(), PublicationStatus::Published->value)
        ->assertNotified(__('admin.notifications.episode_visible'));

    $episode->refresh();

    expect($episode->status)->toBe(PublicationStatus::Published)
        ->and($episode->published_at->toDateTimeString())->toBe('2026-08-05 15:00:00');
});

it('reports the blocker honestly when an inline publish cannot go live', function (): void {
    $episode = ContentItem::factory()
        ->for(ContentGroup::factory(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])
        ->create();

    Livewire::test(ListContentItems::class)
        ->call('updateTableColumnState', 'status', (string) $episode->getKey(), PublicationStatus::Published->value)
        ->assertNotified(__('admin.notifications.episode_blocked_group'));

    expect($episode->refresh()->status)->toBe(PublicationStatus::Published);
});

it('rejects a forged inline status value through the implicit in rule', function (): void {
    $episode = ContentItem::factory()->create();

    Livewire::test(ListContentItems::class)
        ->call('updateTableColumnState', 'status', (string) $episode->getKey(), 'forged-status');

    expect($episode->refresh()->status)->toBe(PublicationStatus::Draft);
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

it('speaks both languages for the public-state vocabulary', function (): void {
    foreach (EpisodePublicState::cases() as $state) {
        foreach (['en', 'he'] as $locale) {
            expect(Lang::has("admin.episode_public_state.{$state->value}", $locale))
                ->toBeTrue("missing admin.episode_public_state.{$state->value} in {$locale}");
        }
    }
});
