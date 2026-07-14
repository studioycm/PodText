<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Livewire\Public\ContentItemSearch;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
});

function saveStep10M3TranscriptionPolicy(array $policy): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'transcription_policy',
        ],
        [
            'locked' => false,
            'payload' => json_encode($policy),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicTranscriptionPolicy::class);
    app()->forgetInstance(PublicTranscriptionSelector::class);
    app()->forgetInstance(PublicTranscriptionAggregates::class);
    app(SettingsContainer::class)->clearCache();
}

function createStep10M3PublishedItem(?ContentGroup $group = null, ?Author $author = null, int $wordCount = 200): array
{
    $group ??= ContentGroup::factory()->published()->create();
    $author ??= Author::factory()->create();

    $item = ContentItem::factory()
        ->for($group)
        ->published(now()->subHour())
        ->create();

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published(now()->subHour())
        ->create([
            'title' => "{$item->title} main",
            'word_count' => $wordCount,
        ]);

    return [$item->refresh(), $transcription->refresh(), $author];
}

it('normalizes public transcription policy settings to finite modes', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'transcription_policy' => [
            'public_mode' => 'all_published',
            'count_mode' => 'raw_sql',
            'show_multiple_transcriptions_on_item_page' => true,
            'unsafe' => '<script>alert(1)</script>',
        ],
    ]);

    $policy = $result->group('transcription_policy');
    $invalidPaths = collect($result->invalidConfigArray())->pluck('path')->all();

    expect($policy)->toBe([
        'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'count_mode' => PublicTranscriptionPolicy::MODE_FEATURED_ONLY,
        'show_multiple_transcriptions_on_item_page' => true,
    ])->and($invalidPaths)->toContain(
        'transcription_policy.count_mode',
        'transcription_policy.unsafe',
    );
});

it('defaults to the effective featured transcription and keeps the first transcription auto-featured', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $primary = Author::factory()->create(['name' => 'Primary Transcriber']);
    $alternate = Author::factory()->create(['name' => 'Alternate Transcriber']);

    $item = ContentItem::factory()
        ->for($group)
        ->published(now()->subHour())
        ->create();

    $first = Transcription::factory()
        ->for($item)
        ->forAuthor($primary)
        ->published(now()->subHour())
        ->create(['word_count' => 400]);
    $second = Transcription::factory()
        ->for($item)
        ->forAuthor($alternate)
        ->published(now()->subMinutes(30))
        ->create(['word_count' => 1200]);

    expect($item->refresh()->featured_transcription_id)->toBe($first->id)
        ->and(app(PublicTranscriptionSelector::class)->publicTranscriptionsForItem($item)->pluck('id')->all())->toBe([$first->id])
        ->and(app(PublicTranscriptionAggregates::class)->publicTranscriptionsCountForItem($item))->toBe(1)
        ->and($item->effectiveTranscription()?->is($first))->toBeTrue()
        ->and($item->effectiveTranscription()?->is($second))->toBeFalse();
});

it('counts all published transcriptions when the policy enables all published mode', function (): void {
    saveStep10M3TranscriptionPolicy([
        'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'count_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'show_multiple_transcriptions_on_item_page' => true,
    ]);

    [$item] = createStep10M3PublishedItem(wordCount: 200);
    $second = Transcription::factory()
        ->for($item)
        ->forAuthor(Author::factory()->create())
        ->published(now()->subMinutes(30))
        ->create(['word_count' => 600]);
    Transcription::factory()
        ->for($item)
        ->forAuthor(Author::factory()->create())
        ->create([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'word_count' => 900,
        ]);

    $publicTranscriptions = app(PublicTranscriptionSelector::class)->publicTranscriptionsForItem($item->refresh());

    expect($publicTranscriptions)->toHaveCount(2)
        ->and($publicTranscriptions->pluck('id')->all())->toContain($second->id)
        ->and(app(PublicTranscriptionAggregates::class)->publicTranscriptionsCountForItem($item))->toBe(2);
});

it('aggregates podcast counts reading time latest dates and transcribers by policy', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $firstAuthor = Author::factory()->create();
    $secondAuthor = Author::factory()->create();
    $thirdAuthor = Author::factory()->create();

    [$firstItem, $firstTranscription] = createStep10M3PublishedItem($group, $firstAuthor, 400);
    Transcription::factory()
        ->for($firstItem)
        ->forAuthor($secondAuthor)
        ->published(now()->subMinutes(20))
        ->create(['word_count' => 1200]);
    createStep10M3PublishedItem($group, $thirdAuthor, 200);

    $featuredOnlyGroup = PublicContentGroupQueries::base()
        ->whereKey($group)
        ->firstOrFail();
    $featuredOnlySummary = app(PublicTranscriptionAggregates::class)->contentGroupSummary($group);

    expect((int) $featuredOnlyGroup->public_content_items_count)->toBe(2)
        ->and((int) $featuredOnlyGroup->public_transcriptions_count)->toBe(2)
        ->and((int) $featuredOnlyGroup->public_total_word_count)->toBe(600)
        ->and((int) $featuredOnlyGroup->public_transcriber_count)->toBe(2)
        ->and($featuredOnlyGroup->public_latest_transcription_published_at)->not->toBeNull()
        ->and($featuredOnlySummary['total_reading_minutes'])->toBe(3);

    saveStep10M3TranscriptionPolicy([
        'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'count_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'show_multiple_transcriptions_on_item_page' => false,
    ]);

    $allPublishedGroup = PublicContentGroupQueries::base()
        ->whereKey($group)
        ->firstOrFail();
    $allPublishedSummary = app(PublicTranscriptionAggregates::class)->contentGroupSummary($group);

    expect($firstItem->refresh()->effectiveTranscription()?->is($firstTranscription))->toBeTrue()
        ->and((int) $allPublishedGroup->public_transcriptions_count)->toBe(3)
        ->and((int) $allPublishedGroup->public_total_word_count)->toBe(1800)
        ->and((int) $allPublishedGroup->public_transcriber_count)->toBe(3)
        ->and($allPublishedSummary['total_reading_minutes'])->toBe(9);
});

it('uses policy-aware pivot-backed contributors and public transcriber filters', function (): void {
    $featuredAuthor = Author::factory()->create(['name' => 'Alpha Featured']);
    $alternateAuthor = Author::factory()->create(['name' => 'Beta Alternate']);
    $draftOnlyAuthor = Author::factory()->create(['name' => 'Draft Only']);

    [$item] = createStep10M3PublishedItem(author: $featuredAuthor);
    Transcription::factory()
        ->for($item)
        ->forAuthor($alternateAuthor)
        ->published(now()->subMinutes(30))
        ->create(['title' => 'Alternate public transcript']);

    $draftItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->create(['title' => 'Draft only item']);
    Transcription::factory()
        ->for($draftItem)
        ->forAuthor($draftOnlyAuthor)
        ->create([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);

    $featuredOnlyIds = PublicContributorDiscovery::topContributors(10)->pluck('id')->all();

    expect($featuredOnlyIds)->toContain($featuredAuthor->id)
        ->and($featuredOnlyIds)->not->toContain($alternateAuthor->id, $draftOnlyAuthor->id)
        ->and(PublicContributorDiscovery::contentItemsForContributor($alternateAuthor)->count())->toBe(0);

    Livewire::test(ContentItemSearch::class)
        ->set('filterTranscriberId', $alternateAuthor->id)
        ->assertDontSee($item->title);

    saveStep10M3TranscriptionPolicy([
        'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'count_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'show_multiple_transcriptions_on_item_page' => true,
    ]);

    $allPublishedContributors = PublicContributorDiscovery::topContributors(10);
    $allPublishedIds = $allPublishedContributors->pluck('id')->all();

    expect($allPublishedIds)->toContain($featuredAuthor->id, $alternateAuthor->id)
        ->and($allPublishedIds)->not->toContain($draftOnlyAuthor->id)
        ->and((int) $allPublishedContributors->firstWhere('id', $alternateAuthor->id)->public_transcriptions_count)->toBe(1)
        ->and((int) $allPublishedContributors->firstWhere('id', $alternateAuthor->id)->public_content_items_count)->toBe(1)
        ->and(PublicContributorDiscovery::contentItemsForContributor($alternateAuthor)->count())->toBe(1);

    Livewire::test(ContentItemSearch::class)
        ->set('filterTranscriberId', $alternateAuthor->id)
        ->assertSee($item->title);
});
