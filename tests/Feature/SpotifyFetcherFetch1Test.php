<?php

use App\Enums\ImportConnectionStatus;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Models\ImportConnection;
use App\Models\User;
use App\Support\Importer\Spotify\SpotifyConnector;
use App\Support\Importer\SpotifyLinks\ImporterCsvBuilder;
use App\Support\Importer\SpotifyLinks\SpotifyHtmlToMarkdown;
use App\Support\Importer\SpotifyLinks\SpotifyOpenGraphClient;
use App\Support\Media\EpisodeSpotifyLookup;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class Fetch1FakeSpotifyLookup extends EpisodeSpotifyLookup
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

        return $this->shows[$id] ?? [
            'external_id' => $id,
            'title' => "Show {$id}",
        ];
    }
}

class Fetch1FakeSpotifyConnector extends SpotifyConnector
{
    /**
     * @param  array<string, mixed>  $episode
     */
    public function __construct(
        public array $episode,
    ) {}

    public function fetchEpisode(ImportConnection $connection, string $spotifyId): array
    {
        return $this->episode;
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Cache::flush();
});

function fetch1Fixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/spotify-fetcher/{$name}")) ?: '';
}

it('extracts spotify episode opengraph fixture fields and caches the html request', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/episode/episodefixture1' => Http::response(fetch1Fixture('episode-head.html'), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]),
    ]);

    $client = app(SpotifyOpenGraphClient::class);
    $first = $client->fetch('https://open.spotify.com/episode/episodefixture1');
    $second = $client->fetch('https://open.spotify.com/episode/episodefixture1');

    expect($first)->toBe($second)
        ->and($first['type'])->toBe('episode')
        ->and($first['title'])->toBe('Fixture Episode LD Title')
        ->and($first['description'])->toBe("First LD paragraph.\n\nSecond LD paragraph.")
        ->and($first['image'])->toBe('https://i.scdn.co/image/fixture-episode-og')
        ->and($first['canonical_url'])->toBe('https://open.spotify.com/episode/episodefixture1')
        ->and($first['duration_seconds'])->toBe(3108)
        ->and($first['release_date'])->toBe('2017-03-28')
        ->and($first['show_name'])->toBe('Fixture Show');

    Http::assertSentCount(1);
});

it('extracts spotify show opengraph fixture fields', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/show/showfixture1' => Http::response(fetch1Fixture('show-head.html'), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]),
    ]);

    $result = app(SpotifyOpenGraphClient::class)->fetch('https://open.spotify.com/show/showfixture1');

    expect($result['type'])->toBe('show')
        ->and($result['title'])->toBe('Fixture Show LD Title')
        ->and($result['description'])->toBe("Show LD paragraph one.\n\nShow LD paragraph two.")
        ->and($result['image'])->toBe('https://i.scdn.co/image/fixture-show-ld')
        ->and($result['canonical_url'])->toBe('https://open.spotify.com/show/showfixture1');
});

it('returns null for failed or unsafe opengraph fetches and tolerates malformed json', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/episode/failurefixture1' => Http::response(fetch1Fixture('server-error.txt'), 500),
        'https://open.spotify.com/episode/redirectfixture1' => Http::response(fetch1Fixture('empty.txt'), 302, [
            'Location' => 'https://example.com/not-spotify',
        ]),
        'https://open.spotify.com/episode/malformedfix1' => Http::response(fetch1Fixture('opengraph-malformed.html'), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]),
    ]);

    $client = app(SpotifyOpenGraphClient::class);

    expect($client->fetch('https://open.spotify.com/episode/failurefixture1'))->toBeNull()
        ->and($client->fetch('https://open.spotify.com/episode/redirectfixture1'))->toBeNull()
        ->and($client->fetch('https://open.spotify.com/episode/malformedfix1')['title'])->toBe('Malformed OG');
});

it('merges reduced mode oembed and opengraph data per field and renders image previews', function (): void {
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(fetch1Fixture('oembed-reduced.json'), 200, [
            'Content-Type' => 'application/json',
        ]),
        'https://open.spotify.com/episode/reduced11111' => Http::response(fetch1Fixture('episode-head.html'), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]),
    ]);

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', null)
        ->set('linksInput', 'https://open.spotify.com/episode/reduced11111')
        ->call('fetch')
        ->assertSet('usedReducedMode', true)
        ->assertSet('rows.0.status', 'reduced')
        ->assertSet('rows.0.source_label', __('admin.spotify_fetcher.sources.reduced'))
        ->assertSet('rows.0.title', 'Fixture Episode LD Title')
        ->assertSet('rows.0.description_markdown', "First LD paragraph.\n\nSecond LD paragraph.")
        ->assertSet('rows.0.external_thumbnail_url', 'https://i.scdn.co/image/fixture-episode-og')
        ->assertSet('rows.0.show_name', 'Fixture Show')
        ->assertSet('rows.0.duration_seconds', 3108)
        ->assertSet('rows.0.release_date', '28/03/2017 00:00')
        ->assertSet('rows.0.embed_url', 'https://open.spotify.com/embed/episode/reduced11111')
        ->assertSeeHtml('data-spotify-image-preview')
        ->assertSeeHtml('src="https://i.scdn.co/image/fixture-episode-og"');

    $rows = $component->instance()->episodeCsvRows();
    $headers = app(ImporterCsvBuilder::class)->headersFor(ContentItemImporter::class);

    expect(collect(array_keys($rows[0]))->sort()->values()->all())->toBe(collect($headers)->sort()->values()->all())
        ->and($rows[0]['description_markdown'])->toBe("First LD paragraph.\n\nSecond LD paragraph.")
        ->and($rows[0]['external_thumbnail_url'])->toBe('https://i.scdn.co/image/fixture-episode-og')
        ->and($rows[0]['media_metadata']['source'])->toBe('reduced')
        ->and($rows[0]['direct_media_url'])->toBe('');
});

it('falls back to the oembed thumbnail when opengraph is unavailable', function (): void {
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(fetch1Fixture('oembed-only.json'), 200, [
            'Content-Type' => 'application/json',
        ]),
        'https://open.spotify.com/episode/oembedonly11' => Http::response(fetch1Fixture('server-error.txt'), 500),
    ]);

    Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', null)
        ->set('linksInput', 'https://open.spotify.com/episode/oembedonly11')
        ->call('fetch')
        ->assertSet('rows.0.title', 'OEmbed only title')
        ->assertSet('rows.0.external_thumbnail_url', 'https://i.scdn.co/image/oembed-only-thumb')
        ->assertSeeHtml('data-spotify-image-preview')
        ->assertSeeHtml('src="https://i.scdn.co/image/oembed-only-thumb"');
});

it('keeps api mode source labels image previews and markdown csv content', function (): void {
    $this->actingAs(User::factory()->create());
    $connection = ImportConnection::factory()->spotify()->create([
        'status' => ImportConnectionStatus::Connected,
    ]);

    app()->instance(EpisodeSpotifyLookup::class, new Fetch1FakeSpotifyLookup(
        episodes: [
            'api11111111' => fetch1ApiEpisodeLookup('api11111111'),
        ],
        shows: [
            'show-api' => fetch1ApiShowLookup('show-api'),
        ],
    ));

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', $connection->id)
        ->set('linksInput', 'spotify:episode:api11111111')
        ->call('fetch')
        ->assertSet('rows.0.status', 'fetched')
        ->assertSet('rows.0.source_label', __('admin.spotify_fetcher.sources.api'))
        ->assertSet('rows.0.description_markdown', "API paragraph\nline\n\nSecond API paragraph")
        ->assertSeeHtml('src="https://i.scdn.co/image/api11111111"');

    $rows = $component->instance()->episodeCsvRows();

    $headers = app(ImporterCsvBuilder::class)->headersFor(ContentItemImporter::class);

    expect(collect(array_keys($rows[0]))->sort()->values()->all())->toBe(collect($headers)->sort()->values()->all())
        ->and($rows[0]['description_markdown'])->toBe("API paragraph\nline\n\nSecond API paragraph")
        ->and($rows[0]['external_thumbnail_url'])->toBe('https://i.scdn.co/image/api11111111')
        ->and($rows[0]['media_metadata']['source'])->toBe('api');
});

it('normalizes plain spotify text descriptions without inventing markdown', function (): void {
    $normalized = app(SpotifyHtmlToMarkdown::class)->normalizePlainText("First line\r\nsecond line\n\n\nThird &amp; final&nbsp;line");

    expect($normalized)->toBe("First line\nsecond line\n\nThird & final line");
});

it('adds markdown descriptions to the episode workspace lookup payload', function (): void {
    $connection = ImportConnection::factory()->spotify()->create([
        'status' => ImportConnectionStatus::Connected,
    ]);
    $lookup = new EpisodeSpotifyLookup(new Fetch1FakeSpotifyConnector([
        'description' => "Fallback plain\n\ndescription",
        'duration' => 120,
        'external_id' => 'workspace11111',
        'external_url' => 'https://open.spotify.com/episode/workspace11111',
        'html_description' => '<p>Workspace API<br>description</p><p>Second paragraph</p>',
        'release_date' => '2026-07-10',
        'show' => 'Workspace Show',
        'show_id' => 'workspace-show',
        'thumbnail' => 'https://i.scdn.co/image/workspace11111',
        'title' => 'Workspace Episode',
    ]));

    $data = $lookup->lookup('spotify:episode:workspace11111', $connection);

    expect($data['description_markdown'])->toBe("Workspace API\ndescription\n\nSecond paragraph");
});

it('keeps rich spotify api html descriptions as markdown in results csv and workspace fill', function (): void {
    $this->actingAs(User::factory()->create());
    $connection = ImportConnection::factory()->spotify()->create([
        'status' => ImportConnectionStatus::Connected,
    ]);
    $lookup = new EpisodeSpotifyLookup(new Fetch1FakeSpotifyConnector([
        'description' => 'Fallback plain description',
        'duration' => 120,
        'external_id' => 'richhtml1111',
        'external_url' => 'https://open.spotify.com/episode/richhtml1111',
        'html_description' => '<p><strong>Bold point</strong><br><a href="https://example.com">Linked source</a></p><p>Second paragraph</p>',
        'release_date' => '2026-07-10',
        'show' => 'Rich Show',
        'show_id' => 'rich-show',
        'thumbnail' => 'https://i.scdn.co/image/richhtml1111',
        'title' => 'Rich HTML Episode',
    ]));

    app()->instance(EpisodeSpotifyLookup::class, $lookup);

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', $connection->id)
        ->set('linksInput', 'spotify:episode:richhtml1111')
        ->call('fetch')
        ->assertSet('rows.0.description_markdown', fn (string $markdown): bool => str_contains($markdown, '**Bold point**')
            && str_contains($markdown, '[Linked source](https://example.com)')
            && str_contains($markdown, 'Second paragraph'));

    expect($component->instance()->episodeCsvRows()[0]['description_markdown'])
        ->toContain('**Bold point**')
        ->toContain('[Linked source](https://example.com)')
        ->toContain('Second paragraph');

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.spotify_episode', 'spotify:episode:richhtml1111')
        ->callAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'), data: [
            'fill_slug_when_empty' => true,
            'fill_title_prefix_when_empty' => true,
            'link_matched_podcast' => false,
            'overwrite_non_empty_fields' => false,
        ])
        ->assertSet('data.description_markdown', fn (string $markdown): bool => str_contains($markdown, '**Bold point**')
            && str_contains($markdown, '[Linked source](https://example.com)')
            && str_contains($markdown, 'Second paragraph'));
});

/**
 * @return array<string, mixed>
 */
function fetch1ApiEpisodeLookup(string $episodeId): array
{
    return [
        'duration_seconds' => 123,
        'embed_url' => "https://open.spotify.com/embed/episode/{$episodeId}",
        'external_description' => 'Plain API description',
        'external_id' => $episodeId,
        'external_thumbnail_url' => "https://i.scdn.co/image/{$episodeId}",
        'media_metadata' => [
            'html_description' => '<p>API paragraph<br>line</p><p>Second API paragraph</p>',
            'show_id' => 'show-api',
        ],
        'media_url' => "https://open.spotify.com/episode/{$episodeId}",
        'original_published_at' => CarbonImmutable::parse('2026-07-10', 'Asia/Jerusalem')->startOfDay(),
        'title' => "Episode {$episodeId}",
        'title_prefix' => 'API Show',
    ];
}

/**
 * @return array<string, mixed>
 */
function fetch1ApiShowLookup(string $showId): array
{
    return [
        'description' => 'API show plain description',
        'external_id' => $showId,
        'external_url' => "https://open.spotify.com/show/{$showId}",
        'html_description' => '<p>API show description</p>',
        'thumbnail' => "https://i.scdn.co/image/{$showId}",
        'title' => 'API Show',
    ];
}
