<?php

use App\Enums\EpisodeListScope;
use App\Enums\EpisodePublicState;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\ContentItems\EpisodeListScopeQuery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * Eleven rows, each created a second after the last (travel(), not an
 * explicit updated_at — freshTimestamp() then just follows the moved clock,
 * with no mass-assignment/fillable concerns). travelBack() at the end fully
 * releases the frozen clock — this file never froze time before, and
 * nothing here should leave it frozen after.
 *
 * Without this, all eleven share one mysql DATETIME second (no
 * fractional-second precision), so ListContentItems'
 * `->defaultSort('updated_at', 'desc')` has no secondary tie-break —
 * ordering (SQLite rowid order is not MySQL return order): sqlite happened
 * to preserve insertion order for tied rows, mysql does not promise that —
 * which of the eleven lands on the ten-row first page becomes
 * non-deterministic instead of "the ten most recently created."
 *
 * @return array<string, ContentItem>
 */
function episodeScopeFixtures(): array
{
    $publishedGroup = ContentGroup::factory()->published();
    $draftGroup = ContentGroup::factory();
    $tick = function (): void {
        test()->travel(1)->seconds();
    };

    $fixtures = [
        'draft' => ContentItem::factory()->for($draftGroup, 'contentGroup')->create(),
    ];
    $tick();
    $fixtures['visible'] = ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')->withTranscription()->create();
    $tick();
    $fixtures['scheduled'] = ContentItem::factory()->published(now()->addDays(3))->for($publishedGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create();
    $tick();
    $fixtures['blocked_group'] = ContentItem::factory()->published()->for($draftGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create();
    $tick();
    $fixtures['blocked_transcription'] = ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create();
    $tick();
    $fixtures['blocked_both'] = ContentItem::factory()->published()->for($draftGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create();
    $tick();
    $fixtures['pinned_draft'] = ContentItem::factory()->pinned()->for($draftGroup, 'contentGroup')->create();
    $tick();
    $fixtures['pin_expired'] = ContentItem::factory()->pinned()->for($draftGroup, 'contentGroup')
        ->create(['pinned_until' => now()->subDay()]);
    $tick();
    // Pinned AND visible: without this row, pinned_not_visible and pinned
    // return the same set and a broken predicate passes unnoticed.
    $fixtures['pinned_visible'] = ContentItem::factory()->pinned()->published()->for($publishedGroup, 'contentGroup')
        ->withTranscription()->create();
    $tick();
    // A draft with everything else already out — one switch from live.
    $fixtures['ready_to_publish'] = ContentItem::factory()->for($publishedGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create();
    $tick();
    // Future-dated, and its podcast will still be unpublished on the day.
    $fixtures['will_break_on_air'] = ContentItem::factory()->published(now()->addDays(5))->for($draftGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create();

    test()->travelBack();

    return $fixtures;
}

it('partitions the library exactly across the quick scopes', function (): void {
    $fixtures = episodeScopeFixtures();
    $service = app(EpisodeListScopeQuery::class);

    $ids = fn (EpisodeListScope $scope): array => $service
        ->apply(ContentItem::query(), $scope)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $expected = fn (array $keys): array => collect($fixtures)
        ->only($keys)
        ->map(fn (ContentItem $item): int => $item->getKey())
        ->sort()
        ->values()
        ->all();

    expect($ids(EpisodeListScope::All))->toBe($expected(array_keys($fixtures)))
        ->and($ids(EpisodeListScope::Drafts))->toBe($expected(['draft', 'pinned_draft', 'pin_expired', 'ready_to_publish']))
        ->and($ids(EpisodeListScope::Visible))->toBe($expected(['visible', 'pinned_visible']))
        ->and($ids(EpisodeListScope::Scheduled))->toBe($expected(['scheduled']))
        // Precedence: an episode blocked by BOTH belongs to the podcast
        // scope, because that is the upstream fix — the same rule
        // EpisodePublicState uses, so badge and tab cannot disagree.
        ->and($ids(EpisodeListScope::BlockedGroup))->toBe($expected(['blocked_group', 'blocked_both', 'will_break_on_air']))
        ->and($ids(EpisodeListScope::BlockedTranscription))->toBe($expected(['blocked_transcription']))
        ->and($ids(EpisodeListScope::Pinned))->toBe($expected(['pinned_draft', 'pinned_visible']))
        // Triage scopes overlap the partition on purpose: each answers "which
        // single thing is missing", so a row can appear in one and in its
        // partition scope both.
        // The pinned draft's podcast and transcript are not released, so it is
        // NOT ready to publish — only a draft with both already out qualifies.
        ->and($ids(EpisodeListScope::ReadyToPublish))->toBe($expected(['ready_to_publish']))
        ->and($ids(EpisodeListScope::PinnedNotVisible))->toBe($expected(['pinned_draft']))
        // Scheduled-and-healthy stays out; only the future-dated row whose
        // prerequisites will still be missing on the day belongs here.
        ->and($ids(EpisodeListScope::WillBreakOnAir))->toBe($expected(['will_break_on_air']));

    $counts = $service->counts();

    // The exact partition contract, derived from the enum rather than listed
    // here — so an overlapping scope cannot silently break the sum, and a new
    // partitioning one is forced to declare itself.
    $partition = collect(EpisodeListScope::cases())
        ->filter(fn (EpisodeListScope $scope): bool => $scope->partitionsLibrary())
        ->sum(fn (EpisodeListScope $scope): int => $counts[$scope->value]);

    expect($partition)
        ->toBe($counts['all'])
        ->and($counts['all'])->toBe(count($fixtures))
        ->and($counts['pinned'])->toBe(2);
});

it('renders every scope tab with exact count badges and filters records per tab', function (): void {
    $fixtures = episodeScopeFixtures();

    $component = Livewire::test(ListContentItems::class);

    // Derived from showsAsTab(), not from cases(): «מוצמדים» is still a scope
    // — counted, and leaned on by the two pin triage scopes — but it is no
    // longer a tab, so the page must render exactly the scopes that claim one
    // and must NOT render the one that does not.
    foreach (EpisodeListScope::cases() as $scope) {
        if ($scope->showsAsTab()) {
            $component->assertSeeHtml('data-scope="'.$scope->value.'"');
            $component->assertSee($scope->getLabel());

            continue;
        }

        $component->assertDontSeeHtml('data-scope="'.$scope->value.'"');
    }

    $component
        ->set('activeTab', EpisodeListScope::BlockedGroup->value)
        ->assertCanSeeTableRecords(collect($fixtures)->only(['blocked_group', 'blocked_both']))
        ->assertCanNotSeeTableRecords(collect($fixtures)->only(['draft', 'visible', 'scheduled', 'blocked_transcription']));

    $component
        ->set('activeTab', EpisodeListScope::BlockedTranscription->value)
        ->assertCanSeeTableRecords(collect($fixtures)->only(['blocked_transcription']))
        // The both-blocked row is the podcast's problem, not the transcriber's.
        ->assertCanNotSeeTableRecords(collect($fixtures)->only(['blocked_group', 'blocked_both', 'visible']));

    $component
        ->set('activeTab', EpisodeListScope::Visible->value)
        ->assertCanSeeTableRecords(collect($fixtures)->only(['visible']))
        ->assertCanNotSeeTableRecords(collect($fixtures)->only(['blocked_group', 'draft']));
});

it('narrows a forged tab value to the all scope', function (): void {
    episodeScopeFixtures();

    // Explicit orderBy matching ListContentItemsTable's own
    // ->defaultSort('updated_at', 'desc'): a bare, unordered limit(5) has no
    // guaranteed relationship to what a ten-per-page table renders first.
    // Ordering (SQLite rowid order is not MySQL return order): sqlite
    // happened to return rows in insertion order with no ORDER BY, mysql
    // does not promise that. Ordering the same way the table does
    // guarantees this sample is a subset of whatever its first page shows,
    // independent of page size.
    Livewire::test(ListContentItems::class)
        ->set('activeTab', 'forged-scope')
        ->assertSet('activeTab', EpisodeListScope::All->value)
        ->assertCanSeeTableRecords(ContentItem::query()->orderBy('updated_at', 'desc')->limit(5)->get());
});

it('names a scoped arrival in the heading and keeps the plain list bare', function (): void {
    $podcast = ContentGroup::factory()->published()->create(['title' => 'קול הבוקר']);
    $episode = ContentItem::factory()->published()->for($podcast, 'contentGroup')->withTranscription()->create();

    // Operator ruling 2026-08-05: no breadcrumbs, no subheading — the tabs
    // answer "what is showing". A scoped arrival is page identity, so it
    // rides the heading instead of a second line.
    $plain = Livewire::test(ListContentItems::class);

    expect($plain->instance()->getSubheading())->toBeNull()
        ->and($plain->instance()->getBreadcrumbs())->toBe([])
        ->and($plain->instance()->getHeading())->not->toContain('קול הבוקר');

    $scoped = Livewire::withQueryParams(['filters' => ['content_group_id' => ['value' => $podcast->getKey()]]])
        ->test(ListContentItems::class);

    expect($scoped->instance()->getHeading())->toContain('קול הבוקר');

    $scoped->assertCanSeeTableRecords([$episode]);
});

it('names a transcriber arrival too, and ignores a forged scope key', function (): void {
    $transcriber = Author::factory()->create(['name' => 'רינה כהן']);
    $episode = ContentItem::factory()->published()->withTranscription()->create();
    $episode->transcriptions()->first()->syncTranscribers([$transcriber->getKey()]);

    $scoped = Livewire::withQueryParams(['filters' => ['transcriber_id' => ['value' => $transcriber->getKey()]]])
        ->test(ListContentItems::class);

    expect($scoped->instance()->getHeading())->toContain('רינה כהן');

    // A forged or dangling key announces nothing rather than erroring.
    $forged = Livewire::withQueryParams(['filters' => ['content_group_id' => ['value' => 'not-an-id']]])
        ->test(ListContentItems::class);

    expect($forged->instance()->getHeading())->toBe(ContentItemResource::getPluralModelLabel());
});

it('keeps the row badge in parity with the scope queries for every state', function (): void {
    $fixtures = episodeScopeFixtures();

    // A scheduled episode whose prerequisites are themselves scheduled for
    // the same moment: it really will go live, so it must read as scheduled.
    $coScheduledGroup = ContentGroup::factory()->published(now()->addDays(2));
    $fixtures['co_scheduled'] = ContentItem::factory()
        ->published(now()->addDays(2))
        ->for($coScheduledGroup, 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->addDays(2)])
        ->create();

    // A scheduled episode whose transcript is still a draft: it will NOT go
    // live on its date, so the badge must say so while it is still fixable.
    $fixtures['scheduled_but_blocked'] = ContentItem::factory()
        ->published(now()->addDays(2))
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])
        ->create();

    $service = app(EpisodeListScopeQuery::class);

    // The badge (PHP) and the tabs (SQL) are two derivations of one contract.
    // This is the guard that keeps them from drifting apart.
    $scopeOf = fn (ContentItem $item): EpisodeListScope => collect([
        EpisodeListScope::Drafts,
        EpisodeListScope::Visible,
        EpisodeListScope::Scheduled,
        EpisodeListScope::BlockedGroup,
        EpisodeListScope::BlockedTranscription,
    ])->sole(fn (EpisodeListScope $scope): bool => $service
        ->apply(ContentItem::query(), $scope)
        ->whereKey($item->getKey())
        ->exists());

    $badgeToScope = [
        EpisodePublicState::Draft->value => EpisodeListScope::Drafts,
        EpisodePublicState::Visible->value => EpisodeListScope::Visible,
        EpisodePublicState::Scheduled->value => EpisodeListScope::Scheduled,
        // One-to-one now, where it used to be two badges collapsing into one
        // tab: the split makes badge and scope the same vocabulary.
        EpisodePublicState::BlockedGroup->value => EpisodeListScope::BlockedGroup,
        EpisodePublicState::BlockedTranscription->value => EpisodeListScope::BlockedTranscription,
    ];

    foreach ($fixtures as $name => $item) {
        $badge = EpisodePublicState::for($item->fresh()->load('contentGroup'));

        expect($badgeToScope[$badge->value])->toBe(
            $scopeOf($item),
            "badge {$badge->value} disagrees with the scope query for fixture [{$name}]",
        );
    }

    // And the two cases the parity alone cannot express:
    expect(EpisodePublicState::for($fixtures['co_scheduled']->fresh()->load('contentGroup')))
        ->toBe(EpisodePublicState::Scheduled)
        ->and(EpisodePublicState::for($fixtures['scheduled_but_blocked']->fresh()->load('contentGroup')))
        ->toBe(EpisodePublicState::BlockedTranscription);
});

it('gives every scope en+he labels and descriptions', function (): void {
    foreach (EpisodeListScope::cases() as $scope) {
        foreach (['en', 'he'] as $locale) {
            expect(Lang::has("admin.episode_scopes.{$scope->value}", $locale))
                ->toBeTrue("missing admin.episode_scopes.{$scope->value} in {$locale}")
                ->and(Lang::has("admin.episode_scopes.descriptions.{$scope->value}", $locale))
                ->toBeTrue("missing description for {$scope->value} in {$locale}");
        }
    }
});
