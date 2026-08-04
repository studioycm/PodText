<?php

use App\Enums\EpisodeListScope;
use App\Enums\EpisodePublicState;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
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
        ->and($ids(EpisodeListScope::Blocked))->toBe($expected(['blocked_group', 'blocked_transcription', 'blocked_both']))
        ->and($ids(EpisodeListScope::Pinned))->toBe($expected(['pinned_draft']));

    $counts = $service->counts();

    // The exact partition contract: drafts + visible + scheduled + blocked = all.
    expect($counts['drafts'] + $counts['visible'] + $counts['scheduled'] + $counts['blocked'])
        ->toBe($counts['all'])
        ->and($counts['all'])->toBe(count($fixtures))
        ->and($counts['pinned'])->toBe(1);
});

it('renders six scope tabs with exact count badges and filters records per tab', function (): void {
    $fixtures = episodeScopeFixtures();

    $component = Livewire::test(ListContentItems::class);

    foreach (EpisodeListScope::cases() as $scope) {
        $component->assertSeeHtml('data-scope="'.$scope->value.'"');
        $component->assertSee($scope->getLabel());
    }

    $component
        ->set('activeTab', EpisodeListScope::Blocked->value)
        ->assertCanSeeTableRecords(collect($fixtures)->only(['blocked_group', 'blocked_transcription', 'blocked_both']))
        ->assertCanNotSeeTableRecords(collect($fixtures)->only(['draft', 'visible', 'scheduled']));

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

it('describes the active scope in the subheading and names the scoped podcast', function (): void {
    $podcast = ContentGroup::factory()->published()->create(['title' => 'קול הבוקר']);
    ContentItem::factory()->published()->for($podcast, 'contentGroup')->withTranscription()->create();

    Livewire::test(ListContentItems::class)
        ->assertSee(EpisodeListScope::All->description())
        ->filterTable('content_group_id', $podcast->getKey())
        ->assertSee('קול הבוקר');
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
        EpisodeListScope::Blocked,
    ])->sole(fn (EpisodeListScope $scope): bool => $service
        ->apply(ContentItem::query(), $scope)
        ->whereKey($item->getKey())
        ->exists());

    $badgeToScope = [
        EpisodePublicState::Draft->value => EpisodeListScope::Drafts,
        EpisodePublicState::Visible->value => EpisodeListScope::Visible,
        EpisodePublicState::Scheduled->value => EpisodeListScope::Scheduled,
        EpisodePublicState::BlockedGroup->value => EpisodeListScope::Blocked,
        EpisodePublicState::BlockedTranscription->value => EpisodeListScope::Blocked,
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
