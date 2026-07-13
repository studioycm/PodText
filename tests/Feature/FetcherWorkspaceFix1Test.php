<?php

use App\Enums\ImportConnectionStatus;
use App\Enums\PublicationStatus;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ImportConnection;
use App\Models\User;
use App\Support\Media\EpisodeSpotifyLookup;
use Carbon\CarbonImmutable;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class Fix1FakeSpotifyLookup extends EpisodeSpotifyLookup
{
    /**
     * @param  array<string, array<string, mixed>>  $episodes
     * @param  array<string, array<string, mixed>>  $shows
     */
    public function __construct(
        public array $episodes,
        public array $shows = [],
    ) {}

    public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
    {
        $id = basename(parse_url($episodeInput, PHP_URL_PATH) ?: $episodeInput);

        return $this->episodes[$id] ?? throw new RuntimeException("Missing {$id}");
    }

    public function lookupShow(string $showInput, ?ImportConnection $connection = null): array
    {
        $id = basename(parse_url($showInput, PHP_URL_PATH) ?: $showInput);

        return $this->shows[$id] ?? fix1ShowLookup($id, "Show {$id}");
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
});

function fix1ImportRecord(string $importerClass, array $row): Import
{
    $import = Import::query()->create([
        'file_name' => 'fix1.csv',
        'file_path' => 'imports/fix1.csv',
        'importer' => $importerClass,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    $importer = $import->getImporter(
        array_combine(array_keys($row), array_keys($row)),
        [
            'mode' => 'upsert',
            'blank_update_behavior' => 'preserve',
            'relation_mode' => 'replace',
        ],
    );

    $importer($row);

    return $import;
}

it('exports spotify fetch rows with stable keys that round trip through strict importers idempotently', function (): void {
    $connection = ImportConnection::factory()->spotify()->create([
        'status' => ImportConnectionStatus::Connected,
    ]);
    $existingGroup = ContentGroup::factory()->create([
        'title' => 'Existing Show',
        'reference_key' => (string) Str::ulid(),
    ]);
    $existingEpisode = ContentItem::factory()
        ->for($existingGroup)
        ->create([
            'reference_key' => (string) Str::ulid(),
            'external_id' => 'existing11111',
            'media_metadata' => [
                'episode_id' => 'existing11111',
                'show_id' => 'show-existing',
            ],
        ]);

    app()->instance(EpisodeSpotifyLookup::class, new Fix1FakeSpotifyLookup(
        episodes: [
            'new11111111' => fix1EpisodeLookup('new11111111', 'New Show', 'show-new'),
            'linked11111' => fix1EpisodeLookup('linked11111', 'Existing Show', 'show-existing'),
            'existing11111' => fix1EpisodeLookup('existing11111', 'Existing Show', 'show-existing'),
        ],
        shows: [
            'show-new' => fix1ShowLookup('show-new', 'New Show'),
            'show-existing' => fix1ShowLookup('show-existing', 'Existing Show'),
        ],
    ));

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', $connection->id)
        ->set('linksInput', 'spotify:episode:new11111111 spotify:episode:linked11111 spotify:episode:existing11111')
        ->call('fetch');

    $episodeRows = $component->instance()->episodeCsvRows();
    $podcastRows = $component->instance()->podcastCsvRows();

    expect($episodeRows)->toHaveCount(3)
        ->and($podcastRows)->toHaveCount(2)
        ->and($episodeRows[0]['reference_key'])->not->toBe('')
        ->and($episodeRows[0]['content_group_reference_key'])->not->toBe('')
        ->and($episodeRows[1]['content_group_reference_key'])->toBe($existingGroup->reference_key)
        ->and($episodeRows[2]['reference_key'])->toBe($existingEpisode->reference_key);

    foreach ($podcastRows as $row) {
        fix1ImportRecord(ContentGroupImporter::class, $row);
    }

    foreach ($episodeRows as $row) {
        fix1ImportRecord(ContentItemImporter::class, $row);
    }

    expect(ContentGroup::query()->count())->toBe(2)
        ->and(ContentItem::query()->count())->toBe(3);

    foreach ($podcastRows as $row) {
        fix1ImportRecord(ContentGroupImporter::class, $row);
    }

    foreach ($episodeRows as $row) {
        fix1ImportRecord(ContentItemImporter::class, $row);
    }

    expect(ContentGroup::query()->count())->toBe(2)
        ->and(ContentItem::query()->count())->toBe(3)
        ->and($existingEpisode->refresh()->reference_key)->toBe($episodeRows[2]['reference_key']);
});

it('directly imports fetched rows creates drafts links existing podcasts and skips existing episodes', function (): void {
    $connection = ImportConnection::factory()->spotify()->create([
        'status' => ImportConnectionStatus::Connected,
    ]);
    $existingGroup = ContentGroup::factory()->create(['title' => 'Existing Show']);
    ContentItem::factory()
        ->for($existingGroup)
        ->create([
            'external_id' => 'skip1111111',
            'media_metadata' => [
                'episode_id' => 'skip1111111',
                'show_id' => 'show-existing',
            ],
        ]);

    app()->instance(EpisodeSpotifyLookup::class, new Fix1FakeSpotifyLookup(
        episodes: [
            'new11111111' => fix1EpisodeLookup('new11111111', 'New Show', 'show-new'),
            'linked11111' => fix1EpisodeLookup('linked11111', 'Existing Show', 'show-existing'),
            'skip1111111' => fix1EpisodeLookup('skip1111111', 'Existing Show', 'show-existing'),
        ],
        shows: [
            'show-new' => fix1ShowLookup('show-new', 'New Show'),
            'show-existing' => fix1ShowLookup('show-existing', 'Existing Show'),
        ],
    ));

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', $connection->id)
        ->set('linksInput', 'spotify:episode:new11111111 spotify:episode:linked11111 spotify:episode:skip1111111')
        ->call('fetch')
        ->callAction(TestAction::make('directImport'));

    $created = ContentItem::query()->where('external_id', 'new11111111')->firstOrFail();
    $linked = ContentItem::query()->where('external_id', 'linked11111')->firstOrFail();

    expect(ContentGroup::query()->where('title', 'New Show')->exists())->toBeTrue()
        ->and($created->status)->toBe(PublicationStatus::Draft)
        ->and($created->slug)->toBe('episode-new11111111')
        ->and($linked->content_group_id)->toBe($existingGroup->id)
        ->and(ContentItem::query()->where('external_id', 'skip1111111')->count())->toBe(1)
        ->and(collect($component->get('rows'))->pluck('direct_import_status')->all())
        ->toContain('imported_episode', 'skipped_existing_episode');
});

it('direct import keeps row failures isolated and supports reduced rows with sparse show data', function (): void {
    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('rows', [
            fix1DirectRow('reduced11111', 'Reduced Episode', 'Reduced Show', status: 'reduced', showId: ''),
            [
                ...fix1DirectRow('bad11111111', '', 'Bad Show', showId: 'show-bad'),
                'media_url' => '',
            ],
        ])
        ->set('podcastRows', [])
        ->call('directImport');

    expect(ContentGroup::query()->where('title', 'Reduced Show')->exists())->toBeTrue()
        ->and(ContentItem::query()->where('external_id', 'reduced11111')->exists())->toBeTrue()
        ->and(ContentItem::query()->where('external_id', 'bad11111111')->exists())->toBeFalse()
        ->and(collect($component->get('rows'))->pluck('direct_import_status')->all())
        ->toContain('imported_episode', 'failed');
});

it('keeps the spotify fetcher page and direct import action behind admin authentication', function (): void {
    auth()->logout();

    $this->get(SpotifyLinksFetcher::getUrl())
        ->assertRedirect('/admin/login');
});

/**
 * @return array<string, mixed>
 */
function fix1EpisodeLookup(string $episodeId, string $showName, string $showId): array
{
    return [
        'description_markdown' => "Description for {$episodeId}",
        'duration_seconds' => 321,
        'embed_url' => "https://open.spotify.com/embed/episode/{$episodeId}",
        'external_description' => "Plain {$episodeId}",
        'external_id' => $episodeId,
        'external_thumbnail_url' => "https://i.scdn.co/image/{$episodeId}",
        'media_metadata' => [
            'episode_id' => $episodeId,
            'html_description' => "<p>HTML {$episodeId}</p>",
            'show_id' => $showId,
        ],
        'media_url' => "https://open.spotify.com/episode/{$episodeId}",
        'original_published_at' => CarbonImmutable::parse('2026-07-10', 'Asia/Jerusalem')->startOfDay(),
        'title' => "Episode {$episodeId}",
        'title_prefix' => $showName,
    ];
}

/**
 * @return array<string, mixed>
 */
function fix1ShowLookup(string $showId, string $showName): array
{
    return [
        'description' => "Plain {$showName}",
        'description_markdown' => "Description for {$showName}",
        'external_id' => $showId,
        'external_url' => "https://open.spotify.com/show/{$showId}",
        'html_description' => "<p>{$showName}</p>",
        'thumbnail' => "https://i.scdn.co/image/{$showId}",
        'title' => $showName,
    ];
}

/**
 * @return array<string, mixed>
 */
function fix1DirectRow(string $episodeId, string $title, string $showName, string $status = 'fetched', string $showId = 'show-direct'): array
{
    return [
        'description_markdown' => "Description for {$episodeId}",
        'duration_seconds' => 120,
        'embed_url' => "https://open.spotify.com/embed/episode/{$episodeId}",
        'external_description' => "Plain {$episodeId}",
        'external_id' => $episodeId,
        'external_thumbnail_url' => "https://i.scdn.co/image/{$episodeId}",
        'input' => "spotify:episode:{$episodeId}",
        'media_url' => "https://open.spotify.com/episode/{$episodeId}",
        'reduced' => $status === 'reduced',
        'release_date' => '10/07/2026 00:00',
        'show_id' => $showId,
        'show_name' => $showName,
        'source' => $status === 'reduced' ? 'reduced' : 'api',
        'source_label' => $status === 'reduced' ? __('admin.spotify_fetcher.sources.reduced') : __('admin.spotify_fetcher.sources.api'),
        'status' => $status,
        'status_label' => __("admin.spotify_fetcher.statuses.{$status}"),
        'title' => $title,
        'title_prefix' => $showName,
        'type' => 'episode',
    ];
}
