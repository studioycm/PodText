<?php

use App\Enums\PublicationStatus;
use App\Filament\Public\Pages\BrowseContributors;
use App\Filament\Public\Pages\BrowsePublicContentGroups;
use App\Filament\Public\Pages\SearchContentItems;
use App\Livewire\Public\ContentItemTranscriptViewer;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function saveStep10M4PublicFrontSettings(array $settings): void
{
    foreach ($settings as $name => $payload) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $name,
            ],
            [
                'locked' => false,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicTranscriptionPolicy::class);
    app()->forgetInstance(PublicTranscriptionSelector::class);
    app()->forgetInstance(PublicTranscriptionAggregates::class);
    app(SettingsContainer::class)->clearCache();
}

function step10M4ContentItemTemplate(): array
{
    return [
        'key' => 'default_content_item',
        'label' => 'M4 default content item',
        'family' => 'content_item',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => [
            ['type' => 'title', 'source' => 'content_item', 'attribute' => 'title', 'visible' => true, 'order' => 10, 'url_target' => 'self'],
            ['type' => 'metadata_row', 'source' => 'transcription', 'attribute' => 'title', 'visible' => true, 'order' => 20],
            ['type' => 'transcriber_line', 'source' => 'transcription', 'attribute' => 'transcribers', 'visible' => true, 'order' => 30],
            ['type' => 'metadata_row', 'source' => 'content_item', 'attribute' => 'transcription_count', 'visible' => true, 'order' => 40],
            ['type' => 'metadata_row', 'source' => 'transcription', 'attribute' => 'reading_time', 'visible' => true, 'order' => 50],
        ],
    ];
}

function step10M4ContentGroupTemplate(): array
{
    return [
        'key' => 'default_content_group',
        'label' => 'M4 default content group',
        'family' => 'content_group',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => [
            ['type' => 'title', 'source' => 'content_group', 'attribute' => 'title', 'visible' => true, 'order' => 10, 'url_target' => 'self'],
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'public_episode_count', 'visible' => true, 'order' => 20],
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'total_reading_time', 'visible' => true, 'order' => 30],
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'transcription_count', 'visible' => true, 'order' => 40],
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'transcriber_count', 'visible' => true, 'order' => 50],
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'latest_transcription_date', 'visible' => true, 'order' => 60],
        ],
    ];
}

/**
 * @return array{
 *     group: ContentGroup,
 *     item: ContentItem,
 *     featured: Transcription,
 *     alternate: Transcription,
 *     primary: Author,
 *     secondary: Author,
 *     alternateAuthor: Author
 * }
 */
function createStep10M4MultiTranscriptionFixture(): array
{
    $group = ContentGroup::factory()->published()->create([
        'title' => 'M4 Technology Podcast',
        'slug' => 'm4-technology-podcast',
    ]);
    $item = ContentItem::factory()
        ->for($group)
        ->published(now()->subDays(5))
        ->create([
            'title' => 'M4 Multi Transcription Episode',
            'slug' => 'm4-multi-transcription-episode',
        ]);

    $primary = Author::factory()->create(['name' => 'Alice Primary', 'slug' => 'alice-primary']);
    $secondary = Author::factory()->create(['name' => 'Yarden Secondary', 'slug' => 'yarden-secondary']);
    $alternateAuthor = Author::factory()->create(['name' => 'Bob Alternate', 'slug' => 'bob-alternate']);

    $featured = Transcription::factory()
        ->for($item)
        ->forAuthor($primary)
        ->published(now()->subDays(4))
        ->create([
            'title' => 'Featured Team Transcript',
            'transcript_markdown' => '[00:00:01] Host: Featured team body',
            'word_count' => 400,
        ]);
    $featured->syncTranscribers([$primary, $secondary]);
    $item->update(['featured_transcription_id' => $featured->id]);

    $alternate = Transcription::factory()
        ->for($item)
        ->forAuthor($alternateAuthor)
        ->published(now()->subDays(2))
        ->create([
            'title' => 'Bob Alternate Transcript',
            'transcript_markdown' => '[00:00:02] Guest: Bob alternate body',
            'word_count' => 600,
        ]);

    Transcription::factory()
        ->for($item)
        ->forAuthor(Author::factory()->create(['name' => 'Draft Hidden']))
        ->create([
            'title' => 'Draft Hidden Transcript',
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);
    Transcription::factory()
        ->for($item)
        ->forAuthor(Author::factory()->create(['name' => 'Future Hidden']))
        ->published(now()->addDay())
        ->create([
            'title' => 'Future Hidden Transcript',
        ]);

    return [
        'group' => $group->refresh(),
        'item' => $item->refresh(),
        'featured' => $featured->refresh(),
        'alternate' => $alternate->refresh(),
        'primary' => $primary,
        'secondary' => $secondary,
        'alternateAuthor' => $alternateAuthor,
    ];
}

function enableStep10M4AllPublishedDisplay(array $extraSettings = []): void
{
    saveStep10M4PublicFrontSettings([
        'transcription_policy' => [
            'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
            'count_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
            'show_multiple_transcriptions_on_item_page' => true,
        ],
        'display_defaults' => [
            'transcription_display' => 'effective_plus_count',
        ],
        'card_templates' => [
            step10M4ContentItemTemplate(),
            step10M4ContentGroupTemplate(),
        ],
        ...$extraSettings,
    ]);
}

function countStep10M4Queries(Closure $callback): int
{
    $count = 0;

    DB::listen(function () use (&$count): void {
        $count++;
    });

    $callback();

    return $count;
}

it('renders effective transcription transcribers and optional counts on episode cards in both policy modes', function (string $mode, bool $expectCount): void {
    $fixture = createStep10M4MultiTranscriptionFixture();

    saveStep10M4PublicFrontSettings([
        'transcription_policy' => [
            'public_mode' => $mode,
            'count_mode' => $mode,
            'show_multiple_transcriptions_on_item_page' => $mode === PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        ],
        'display_defaults' => [
            'transcription_display' => 'effective_plus_count',
        ],
        'card_templates' => [
            step10M4ContentItemTemplate(),
        ],
    ]);

    $response = $this->get("/podcasts/{$fixture['group']->slug}");

    $response
        ->assertSuccessful()
        ->assertSee('Featured Team Transcript')
        ->assertSee('Alice Primary')
        ->assertSee('Yarden Secondary')
        ->assertDontSee('Draft Hidden Transcript')
        ->assertDontSee('Future Hidden Transcript')
        ->assertDontSee('Future Hidden');

    if ($expectCount) {
        $response->assertSee('data-card-part-attribute="transcription_count"', false);
    } else {
        $response->assertDontSee('data-card-part-attribute="transcription_count"', false);
    }
})->with([
    'featured only' => [PublicTranscriptionPolicy::MODE_FEATURED_ONLY, false],
    'all published' => [PublicTranscriptionPolicy::MODE_ALL_PUBLISHED, true],
]);

it('uses contributor-specific transcriptions for contributor context episode cards', function (): void {
    $fixture = createStep10M4MultiTranscriptionFixture();
    enableStep10M4AllPublishedDisplay();

    $this->get("/contributors/{$fixture['alternateAuthor']->slug}")
        ->assertSuccessful()
        ->assertSee('M4 Multi Transcription Episode')
        ->assertSee('Bob Alternate Transcript')
        ->assertSee('Bob Alternate')
        ->assertDontSee('Featured Team Transcript')
        ->assertDontSee('Alice Primary')
        ->assertDontSee('Yarden Secondary');
});

it('renders item page effective header metadata and per-transcription viewer tabs when enabled', function (): void {
    $fixture = createStep10M4MultiTranscriptionFixture();
    enableStep10M4AllPublishedDisplay();

    $this->get("/items/{$fixture['group']->slug}/{$fixture['item']->slug}")
        ->assertSuccessful()
        ->assertSee('Alice Primary')
        ->assertSee('Yarden Secondary')
        ->assertSee('data-test="item-transcription-count"', false)
        ->assertSee('data-test="transcript-tabs"', false)
        ->assertSee('Featured Team Transcript')
        ->assertSee('Bob Alternate Transcript')
        ->assertSee('data-test="transcript-tab-transcribers"', false)
        ->assertSee('Featured team body')
        ->assertDontSee('Draft Hidden Transcript')
        ->assertDontSee('Future Hidden Transcript');

    Livewire::withQueryParams(['transcription' => $fixture['alternate']->reference_key])
        ->test(ContentItemTranscriptViewer::class, ['contentItem' => $fixture['item']->refresh()])
        ->assertSet('selectedTranscription', $fixture['alternate']->reference_key)
        ->assertSee('Bob alternate body')
        ->assertDontSee('Featured team body');
});

it('renders content group aggregate attributes on podcast cards and detail headers', function (): void {
    $fixture = createStep10M4MultiTranscriptionFixture();
    enableStep10M4AllPublishedDisplay();

    $this->get(BrowsePublicContentGroups::getUrl(panel: 'public'))
        ->assertSuccessful()
        ->assertSee('M4 Technology Podcast')
        ->assertSee('data-card-part-attribute="public_episode_count"', false)
        ->assertSee('data-card-part-attribute="total_reading_time"', false)
        ->assertSee('data-card-part-attribute="transcription_count"', false)
        ->assertSee('data-card-part-attribute="transcriber_count"', false);

    $this->get("/podcasts/{$fixture['group']->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="content-group-public-stats"', false)
        ->assertSee('data-test="content-group-total-reading-time"', false)
        ->assertSee('data-test="content-group-transcription-count"', false)
        ->assertSee('data-test="content-group-transcriber-count"', false);
});

it('does not sync the transcriber pivot on a no-op transcription save', function (): void {
    $fixture = createStep10M4MultiTranscriptionFixture();
    $transcription = Transcription::query()->findOrFail($fixture['featured']->getKey());
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'author_transcription')) {
            $queries[] = $query->sql;
        }
    });

    $transcription->save();

    expect($queries)->toBeEmpty();
});

it('keeps public rendering query counts bounded for multi-transcription surfaces', function (): void {
    $fixture = createStep10M4MultiTranscriptionFixture();
    enableStep10M4AllPublishedDisplay();

    HomepageSection::factory()->create([
        'name' => 'M4 Latest',
        'type' => 'latest',
        'limit' => 3,
        'sort_order' => 1,
        'is_visible' => true,
    ]);

    $paths = [
        'homepage' => ['/', 80],
        'search' => [SearchContentItems::getUrl(panel: 'public'), 90],
        'podcast detail' => ["/podcasts/{$fixture['group']->slug}", 85],
        'contributors' => [BrowseContributors::getUrl(panel: 'public'), 90],
        'item page' => ["/items/{$fixture['group']->slug}/{$fixture['item']->slug}", 110],
    ];

    foreach ($paths as $label => [$path, $maxQueries]) {
        $queries = countStep10M4Queries(function () use ($path): void {
            $this->get($path)->assertSuccessful();
        });

        expect($queries, "{$label} query count")->toBeLessThanOrEqual($maxQueries);
    }
});
