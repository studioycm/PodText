<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContentItem;
use App\Filament\Public\Pages\ShowContributor;
use App\Livewire\Public\ContributorContentItems;
use App\Livewire\Public\ContributorDirectory;
use App\Livewire\Public\TopTranscribersSection;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContributorProfile;
use App\Models\Episode;
use App\Models\Podcast;
use App\Models\PublicMenu;
use App\Models\PublicMenuItem;
use App\Models\Transcription;
use App\Models\VolunteerProfile;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContributorDiscovery;
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

function saveStep10PublicFrontSettings(array $settings): void
{
    foreach ($settings as $key => $value) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $key,
            ],
            [
                'locked' => false,
                'payload' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function createStep10PublicContributorItem(
    Author $author,
    string $title,
    array $itemAttributes = [],
    array $transcriptionAttributes = [],
    ?ContentGroup $group = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();
    $publishedAt = $itemAttributes['published_at'] ?? now()->subMinute();

    $contentItem = ContentItem::factory()
        ->for($group)
        ->published($publishedAt)
        ->create([
            'title' => $title,
            ...$itemAttributes,
        ]);

    $transcription = Transcription::factory()
        ->for($contentItem)
        ->forAuthor($author)
        ->published($transcriptionAttributes['published_at'] ?? $publishedAt)
        ->create([
            'title' => "{$title} Transcript",
            'transcript_markdown' => "Transcript for {$title}.",
            ...$transcriptionAttributes,
        ]);

    $contentItem->update(['featured_transcription_id' => $transcription->id]);

    return $contentItem->refresh();
}

it('normalizes contributor settings to safe finite values', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'contributors_page' => [
            'title' => 'Transcribers',
            'directory' => [
                'per_page_options' => [10, 12, 15, 20, 30],
                'default_per_page' => 15,
                'sort_options' => ['name_asc', 'unknown_sort', 'count_desc'],
                'default_sort' => 'unknown_sort',
                'preview_grid_columns' => 8,
            ],
            'top_transcribers' => [
                'layout' => 'vertical',
                'preview_page_size_options' => [5, 8, 10, 15],
                'preview_default_page_size' => 10,
            ],
            'cards' => [
                'compact_count_icon' => 'script',
            ],
            'page' => [
                'items_per_page' => 7,
                'page_size_options' => [7, 12, 99],
                'sort_options' => ['latest_transcription', 'bad_sort'],
                'default_sort' => 'bad_sort',
                'grid_gap' => 'unsafe',
            ],
        ],
    ]);

    $contributorsPage = $result->group('contributors_page');

    expect($contributorsPage['title'])->toBe('Transcribers')
        ->and($contributorsPage['directory']['per_page_options'])->toBe([10, 15, 20])
        ->and($contributorsPage['directory']['default_per_page'])->toBe(15)
        ->and($contributorsPage['directory']['sort_options'])->toBe(['name_asc', 'count_desc'])
        ->and($contributorsPage['directory']['default_sort'])->toBe('count_desc')
        ->and($contributorsPage['directory']['preview_grid_columns'])->toBe(3)
        ->and($contributorsPage['top_transcribers']['layout'])->toBe('horizontal')
        ->and($contributorsPage['top_transcribers']['preview_page_size_options'])->toBe([5, 10, 15])
        ->and($contributorsPage['cards']['compact_count_icon'])->toBe('OutlinedDocumentText')
        ->and($contributorsPage['page']['page_size_options'])->toBe([7, 12])
        ->and($contributorsPage['page']['default_sort'])->toBe('latest_transcription')
        ->and($contributorsPage['page']['grid_gap'])->toBe('comfortable')
        ->and($result->hasInvalidConfig())->toBeTrue();
});

it('renders the contributor directory with configured controls compact cards and preview search', function (): void {
    saveStep10PublicFrontSettings([
        'contributors_page' => [
            'directory' => [
                'per_page_options' => [10, 15, 20],
                'default_per_page' => 15,
                'default_sort' => 'name_asc',
                'sort_options' => ['name_asc', 'name_desc', 'count_desc', 'count_asc'],
                'preview_items_per_page' => 4,
                'preview_grid_columns' => 2,
                'preview_search_enabled' => true,
            ],
            'cards' => [
                'preview_show_bio' => false,
                'preview_show_counts' => false,
            ],
        ],
    ]);

    $alpha = Author::factory()->create(['name' => 'Alpha Transcriber', 'slug' => 'alpha-transcriber']);
    $beta = Author::factory()->create(['name' => 'Beta Transcriber', 'slug' => 'beta-transcriber']);

    createStep10PublicContributorItem($alpha, 'Needle Public Item');
    createStep10PublicContributorItem($alpha, 'Other Alpha Item');
    createStep10PublicContributorItem($beta, 'Beta Public Item');

    Livewire::test(ContributorDirectory::class)
        ->assertSet('perPage', 15)
        ->assertSet('sort', 'name_asc')
        ->assertSee('data-test="contributor-page-size"', false)
        ->assertSee('value="10"', false)
        ->assertSee('value="15"', false)
        ->assertSee('value="20"', false)
        ->assertSee('data-sort="name_asc"', false)
        ->assertSee('data-sort="name_desc"', false)
        ->assertSee('data-sort="count_desc"', false)
        ->assertSee('data-sort="count_asc"', false)
        ->assertSee('data-test="contributor-card"', false)
        ->assertSee($alpha->name)
        ->assertSee($beta->name)
        ->assertSee('title="'.trans_choice('public.labels.public_transcriptions_count', 2, ['count' => 2]).'"', false)
        ->assertDontSee('data-test="contributor-link"', false)
        ->call('selectContributor', $alpha->id)
        ->assertSet('selectedContributorId', $alpha->id)
        ->assertSee('data-test="selected-contributor-link"', false)
        ->assertDontSee('data-test="contributor-preview-counts"', false)
        ->assertDontSee('data-test="contributor-preview-bio"', false)
        ->assertSee('data-test="contributor-preview-search"', false)
        ->assertSee('data-test="contributor-preview-items-grid"', false)
        ->assertSee('data-grid-columns="2"', false)
        ->assertSee('Needle Public Item')
        ->set('previewSearch', 'Needle')
        ->assertSee('Needle Public Item')
        ->assertDontSee('Other Alpha Item');
});

it('renders a horizontal top transcribers selector with a selected preview and no overflow transcript title list', function (): void {
    saveStep10PublicFrontSettings([
        'contributors_page' => [
            'top_transcribers' => [
                'enabled' => true,
                'limit' => 2,
                'layout' => 'horizontal',
                'preview_default_page_size' => 5,
                'preview_page_size_options' => [5, 10, 15],
                'preview_grid_columns' => 3,
                'show_full_page_link' => true,
                'show_count_badge' => true,
            ],
        ],
    ]);

    $top = Author::factory()->create(['name' => 'Alpha Top Transcriber', 'slug' => 'top-transcriber']);
    $second = Author::factory()->create(['name' => 'Second Transcriber', 'slug' => 'second-transcriber']);
    $hidden = Author::factory()->create(['name' => 'Hidden Transcriber', 'slug' => 'hidden-transcriber']);

    $sharedItem = createStep10PublicContributorItem($top, 'Shared Top Item', [], ['title' => 'First Top Transcript']);
    Transcription::factory()
        ->for($sharedItem)
        ->forAuthor($top)
        ->published(now()->subSeconds(30))
        ->create([
            'title' => 'Second Top Transcript',
            'transcript_markdown' => 'Second top transcript.',
        ]);

    createStep10PublicContributorItem($second, 'Second Contributor Item');

    $draftItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->create(['title' => 'Draft Hidden Item']);
    Transcription::factory()
        ->for($draftItem)
        ->forAuthor($hidden)
        ->published()
        ->create();

    $previewItems = PublicContributorDiscovery::contentItemsForContributor($top)->get();

    expect($top->refresh()->public_transcriptions_count)->toBeNull()
        ->and(PublicContributorDiscovery::topContributors(2)->first()->public_transcriptions_count)->toBe(1)
        ->and($previewItems)->toHaveCount(1);

    $component = Livewire::test(TopTranscribersSection::class, [
        'contributorIds' => [$top->id, $second->id],
    ])
        ->assertSee('data-test="top-transcribers-section"', false)
        ->assertSee('data-test="top-transcribers-selector"', false)
        ->assertSee('data-layout="horizontal"', false)
        ->assertSee($top->name)
        ->assertSee($second->name)
        ->assertDontSee($hidden->name)
        ->assertSee('data-test="top-transcriber-preview"', false)
        ->assertSee('data-test="top-transcriber-preview-counts"', false)
        ->assertSee(trans_choice('public.labels.public_transcriptions_count', 1, ['count' => 1]))
        ->assertSee(trans_choice('public.labels.public_content_items_count', 1, ['count' => 1]))
        ->assertSee('data-test="top-transcriber-preview-page-size"', false)
        ->assertSee('value="5"', false)
        ->assertSee('value="10"', false)
        ->assertSee('value="15"', false)
        ->assertSee(ShowContributor::getUrl(['authorSlug' => $top->slug], panel: 'public'), false)
        ->assertSee('Shared Top Item')
        ->assertDontSee('First Top Transcript')
        ->assertDontSee('Second Top Transcript');

    expect(substr_count($component->html(), 'data-test="contributor-item-card-group"'))->toBe(1);
    $sharedItem->load('contentGroup');

    expect($component->html())
        ->toContain(ShowContentItem::getUrl([
            'contentGroupSlug' => $sharedItem->contentGroup->slug,
            'contentItemSlug' => $sharedItem->slug,
        ], panel: 'public'))
        ->toContain(ShowContentGroup::getUrl([
            'contentGroupSlug' => $sharedItem->contentGroup->slug,
        ], panel: 'public'));

    $component
        ->call('selectContributor', $second->id)
        ->assertSet('selectedContributorId', $second->id)
        ->assertSee($second->name)
        ->assertSee('Second Contributor Item')
        ->assertDontSee('Shared Top Item')
        ->set('previewPerPage', 10)
        ->assertSet('previewPerPage', 10);
});

it('adds search sort page-size controls without an overflow transcript title list on the full contributor item list', function (): void {
    saveStep10PublicFrontSettings([
        'contributors_page' => [
            'page' => [
                'items_per_page' => 6,
                'page_size_options' => [6, 12, 24],
                'default_sort' => 'title_asc',
                'sort_options' => ['latest_transcription', 'oldest_transcription', 'title_asc', 'title_desc'],
                'search_enabled' => true,
                'grid_columns' => 4,
                'grid_gap' => 'spacious',
            ],
        ],
    ]);

    $author = Author::factory()->create([
        'name' => 'Full Page Step Ten',
        'slug' => 'full-page-step-ten',
        'bio_markdown' => 'Contributor **bio** <script>alert("x")</script>',
    ]);

    $needle = createStep10PublicContributorItem($author, 'Needle Contributor Item', [], ['title' => 'Needle Transcript']);
    Transcription::factory()
        ->for($needle)
        ->forAuthor($author)
        ->published(now()->subSeconds(30))
        ->create([
            'title' => 'Needle Alternate Transcript',
            'transcript_markdown' => 'Alternate transcript.',
        ]);
    createStep10PublicContributorItem($author, 'Alpha Contributor Item');

    $draftTranscriptionItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->create(['title' => 'Hidden Draft Transcript Item']);
    Transcription::factory()
        ->for($draftTranscriptionItem)
        ->forAuthor($author)
        ->create([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);

    $this->get("/contributors/{$author->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="contributor-page-name"', false)
        ->assertSee('Full Page Step Ten')
        ->assertSee('<strong>bio</strong>', false)
        ->assertDontSee('alert("x")')
        ->assertSee('data-test="contributor-page-item-controls"', false)
        ->assertSee('data-test="contributor-page-item-search"', false)
        ->assertSee('data-test="contributor-page-item-sort"', false)
        ->assertSee('data-test="contributor-page-item-page-size"', false)
        ->assertSee('data-grid-columns="4"', false)
        ->assertSee('data-grid-gap="spacious"', false)
        ->assertDontSee('Needle Transcript')
        ->assertDontSee('Needle Alternate Transcript')
        ->assertDontSee('Hidden Draft Transcript Item')
        ->assertDontSee('fi-ta-table', false);

    Livewire::test(ContributorContentItems::class, ['author' => $author])
        ->assertSet('sort', 'title_asc')
        ->assertSet('perPage', 6)
        ->assertSee('Needle Contributor Item')
        ->assertSee('Alpha Contributor Item')
        ->set('search', 'Needle')
        ->assertSee('Needle Contributor Item')
        ->assertDontSee('Alpha Contributor Item')
        ->set('sort', 'latest_transcription')
        ->assertSet('sort', 'latest_transcription')
        ->set('perPage', 12)
        ->assertSet('perPage', 12)
        ->call('clearItemSearch')
        ->assertSet('search', '')
        ->assertSet('sort', 'title_asc')
        ->assertSet('perPage', 6);
});

it('hides contributor pages and top-transcriber sections when contributor discovery is disabled', function (): void {
    saveStep10PublicFrontSettings([
        'contributors_page' => [
            'enabled' => false,
            'top_transcribers' => [
                'enabled' => false,
            ],
        ],
    ]);

    $author = Author::factory()->create(['slug' => 'disabled-contributor']);
    createStep10PublicContributorItem($author, 'Disabled Contributor Item');

    $this->get('/contributors')->assertNotFound();
    $this->get("/contributors/{$author->slug}")->assertNotFound();

    Livewire::test(TopTranscribersSection::class, [
        'contributorIds' => [$author->id],
    ])
        ->assertSee('data-test="homepage-section-empty"', false)
        ->assertDontSee($author->name);
});

it('does not create public contributor profile or settings-only menu models', function (): void {
    expect(class_exists(ContributorProfile::class))->toBeFalse()
        ->and(class_exists(VolunteerProfile::class))->toBeFalse()
        ->and(class_exists(PublicMenu::class))->toBeFalse()
        ->and(class_exists(PublicMenuItem::class))->toBeFalse()
        ->and(class_exists(Podcast::class))->toBeFalse()
        ->and(class_exists(Episode::class))->toBeFalse();
});
