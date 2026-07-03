<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Filament\Public\Pages\ShowContributor;
use App\Livewire\Public\ContentItemSearch;
use App\Livewire\Public\ContributorDirectory;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\HomepageSection;
use App\Models\Transcription;
use Database\Seeders\DemoHebrewContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createPrompt11BPublicItem(
    Author $author,
    string $title,
    array $itemAttributes = [],
    array $transcriptionAttributes = [],
    ?ContentGroup $group = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();

    $contentItem = ContentItem::factory()
        ->for($group)
        ->published($itemAttributes['published_at'] ?? now()->subMinute())
        ->create([
            'title' => $title,
            ...$itemAttributes,
        ]);

    $transcription = Transcription::factory()
        ->for($contentItem)
        ->forAuthor($author)
        ->published($transcriptionAttributes['published_at'] ?? now()->subMinute())
        ->create([
            'title' => $title,
            ...$transcriptionAttributes,
        ]);

    $contentItem->update(['featured_transcription_id' => $transcription->id]);

    return $contentItem->refresh();
}

it('renders top transcribers homepage section with public counts and section limit', function (): void {
    $top = Author::factory()->create(['name' => 'Top Contributor', 'slug' => 'top-contributor']);
    $second = Author::factory()->create(['name' => 'Second Contributor', 'slug' => 'second-contributor']);
    $hidden = Author::factory()->create(['name' => 'Draft Only Contributor', 'slug' => 'draft-only-contributor']);

    $publicItem = createPrompt11BPublicItem($top, 'Shared Public Item');
    Transcription::factory()
        ->for($publicItem)
        ->forAuthor($top)
        ->published(now()->subSeconds(30))
        ->create(['title' => 'Alternate Public Transcript']);

    createPrompt11BPublicItem($second, 'Second Public Item');

    $draftItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->create(['title' => 'Draft Hidden Item']);
    Transcription::factory()
        ->for($draftItem)
        ->forAuthor($hidden)
        ->published()
        ->create();

    HomepageSection::factory()->create([
        'name' => 'Top transcribers',
        'type' => HomepageSectionType::TopTranscribers,
        'limit' => 1,
        'sort_order' => 1,
    ]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee('data-section-type="top_transcribers"', false)
        ->assertSee('data-test="top-transcribers-grid"', false)
        ->assertSee($top->name)
        ->assertSee(trans_choice('public.labels.public_transcriptions_count', 2, ['count' => 2]))
        ->assertSee(trans_choice('public.labels.public_content_items_count', 1, ['count' => 1]))
        ->assertDontSee("data-contributor-id=\"{$second->id}\"", false)
        ->assertDontSee("data-contributor-id=\"{$hidden->id}\"", false);
});

it('allows guests to browse contributors and search with url state', function (): void {
    $matching = Author::factory()->create(['name' => 'Searchable Contributor', 'slug' => 'searchable-contributor']);
    $other = Author::factory()->create(['name' => 'Other Contributor', 'slug' => 'other-contributor']);
    $hidden = Author::factory()->create(['name' => 'Hidden Contributor', 'slug' => 'hidden-contributor']);

    createPrompt11BPublicItem($matching, 'Searchable Item');
    createPrompt11BPublicItem($other, 'Other Item');

    $draftItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->create(['title' => 'Hidden Item']);
    Transcription::factory()
        ->for($draftItem)
        ->forAuthor($hidden)
        ->published()
        ->create();

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('public.pages.contributors.title'))
        ->assertSee($matching->name)
        ->assertSee($other->name)
        ->assertDontSee($hidden->name)
        ->assertSee(ShowContributor::getUrl(['authorSlug' => $matching->slug], panel: 'public'), false);

    Livewire::withQueryParams(['q' => 'Searchable'])
        ->test(ContributorDirectory::class)
        ->assertSet('search', 'Searchable')
        ->assertSee($matching->name)
        ->assertDontSee($other->name)
        ->assertDontSee($hidden->name);
});

it('selects a contributor and previews only public related content items by transcription', function (): void {
    $selected = Author::factory()->create(['name' => 'Preview Contributor']);
    $other = Author::factory()->create(['name' => 'Other Preview Contributor']);

    $previewItem = createPrompt11BPublicItem($selected, 'Preview Public Item');
    $authoredOnly = createPrompt11BPublicItem($other, 'Authored But Not Transcribed');
    $authoredOnly->authors()->attach($selected);
    createPrompt11BPublicItem($other, 'Other Public Item');

    Livewire::test(ContributorDirectory::class)
        ->call('selectContributor', $selected->id)
        ->assertSet('selectedContributorId', $selected->id)
        ->assertSee($selected->name)
        ->assertSee($previewItem->title)
        ->assertDontSee($authoredOnly->title)
        ->assertDontSee('Other Public Item');
});

it('shows a full contributor page with only public content item cards', function (): void {
    $author = Author::factory()->create([
        'name' => 'Full Page Contributor',
        'slug' => 'full-page-contributor',
        'bio_markdown' => 'Contributor **bio** <script>alert("x")</script>',
    ]);
    $hidden = Author::factory()->create(['slug' => 'hidden-full-page-contributor']);

    $visibleItem = createPrompt11BPublicItem($author, 'Visible Contributor Item');

    $draftItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->create(['title' => 'Draft Contributor Item']);
    Transcription::factory()
        ->for($draftItem)
        ->forAuthor($author)
        ->published()
        ->create();

    $draftTranscriptionItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->create(['title' => 'Draft Transcription Contributor Item']);
    Transcription::factory()
        ->for($draftTranscriptionItem)
        ->forAuthor($author)
        ->create([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);

    $hiddenItem = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->create(['title' => 'Hidden Contributor Item']);
    Transcription::factory()
        ->for($hiddenItem)
        ->forAuthor($hidden)
        ->published()
        ->create();

    $this->get("/contributors/{$author->slug}")
        ->assertSuccessful()
        ->assertSee($author->name)
        ->assertSee('<strong>bio</strong>', false)
        ->assertDontSee('alert("x")')
        ->assertSee($visibleItem->title)
        ->assertSee('data-test="content-item-card"', false)
        ->assertDontSee($draftItem->title)
        ->assertDontSee($draftTranscriptionItem->title);

    $this->get("/contributors/{$hidden->slug}")
        ->assertNotFound();
});

it('keeps the demo contributor discovery seeder idempotent', function (): void {
    $this->seed(DemoHebrewContentSeeder::class);
    $this->seed(DemoHebrewContentSeeder::class);

    expect(Author::query()->whereIn('reference_key', [
        'demo-author-yael-ben-david',
        'demo-author-noam-levi',
        'demo-author-michal-cohen',
        'demo-author-amir-shalev',
    ])->count())->toBe(4)
        ->and(HomepageSection::query()->where('slug', 'top-transcribers')->count())->toBe(1)
        ->and(HomepageSection::query()->where('slug', 'top-transcribers')->firstOrFail()->type)->toBe(HomepageSectionType::TopTranscribers)
        ->and(Transcription::query()->where('reference_key', 'like', 'demo-item-%-transcription-main')->count())->toBe(8);
});
