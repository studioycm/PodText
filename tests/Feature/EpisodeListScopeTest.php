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
 * @return array<string, ContentItem>
 */
function episodeScopeFixtures(): array
{
    $publishedGroup = ContentGroup::factory()->published();
    $draftGroup = ContentGroup::factory();

    return [
        'draft' => ContentItem::factory()->for($draftGroup, 'contentGroup')->create(),
        'visible' => ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')->withTranscription()->create(),
        'scheduled' => ContentItem::factory()->published(now()->addDays(3))->for($publishedGroup, 'contentGroup')
            ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create(),
        'blocked_group' => ContentItem::factory()->published()->for($draftGroup, 'contentGroup')
            ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()])->create(),
        'blocked_transcription' => ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')
            ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create(),
        'blocked_both' => ContentItem::factory()->published()->for($draftGroup, 'contentGroup')
            ->withTranscription(['status' => PublicationStatus::Draft, 'published_at' => null])->create(),
        'pinned_draft' => ContentItem::factory()->pinned()->for($draftGroup, 'contentGroup')->create(),
        'pin_expired' => ContentItem::factory()->pinned()->for($draftGroup, 'contentGroup')
            ->create(['pinned_until' => now()->subDay()]),
    ];
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
        ->and($ids(EpisodeListScope::Drafts))->toBe($expected(['draft', 'pinned_draft', 'pin_expired']))
        ->and($ids(EpisodeListScope::Visible))->toBe($expected(['visible']))
        ->and($ids(EpisodeListScope::Scheduled))->toBe($expected(['scheduled']))
        // Precedence: an episode blocked by BOTH belongs to the podcast
        // scope, because that is the upstream fix — the same rule
        // EpisodePublicState uses, so badge and tab cannot disagree.
        ->and($ids(EpisodeListScope::BlockedGroup))->toBe($expected(['blocked_group', 'blocked_both']))
        ->and($ids(EpisodeListScope::BlockedTranscription))->toBe($expected(['blocked_transcription']))
        ->and($ids(EpisodeListScope::Pinned))->toBe($expected(['pinned_draft']));

    $counts = $service->counts();

    // The exact partition contract, which the split must not break:
    // drafts + visible + scheduled + the two blocked scopes = all.
    expect($counts['drafts'] + $counts['visible'] + $counts['scheduled'] + $counts['blocked_group'] + $counts['blocked_transcription'])
        ->toBe($counts['all'])
        ->and($counts['all'])->toBe(count($fixtures))
        ->and($counts['pinned'])->toBe(1);
});

it('renders every scope tab with exact count badges and filters records per tab', function (): void {
    $fixtures = episodeScopeFixtures();

    $component = Livewire::test(ListContentItems::class);

    foreach (EpisodeListScope::cases() as $scope) {
        $component->assertSeeHtml('data-scope="'.$scope->value.'"');
        $component->assertSee($scope->getLabel());
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

    Livewire::test(ListContentItems::class)
        ->set('activeTab', 'forged-scope')
        ->assertSet('activeTab', EpisodeListScope::All->value)
        ->assertCanSeeTableRecords(ContentItem::query()->limit(5)->get());
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
