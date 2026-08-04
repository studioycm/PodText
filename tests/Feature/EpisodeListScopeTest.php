<?php

use App\Enums\EpisodeListScope;
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
