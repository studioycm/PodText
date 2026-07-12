<?php

use App\Enums\ImportConnectionStatus;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Pages\AdminTools;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Models\ContentGroup;
use App\Models\ImportConnection;
use App\Models\User;
use App\Support\Importer\ImporterThrottle;
use App\Support\Importer\SpotifyLinks\ImporterCsvBuilder;
use App\Support\Importer\SpotifyLinks\SpotifyEntityMode;
use App\Support\Importer\SpotifyLinks\SpotifyHtmlToMarkdown;
use App\Support\Importer\SpotifyLinks\SpotifyLinkParser;
use App\Support\Media\EpisodeSpotifyLookup;
use App\Support\Spreadsheet\SpreadsheetCellClipboard;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class Tools1FakeSpotifyLookup extends EpisodeSpotifyLookup
{
    /**
     * @param  array<string, array<string, mixed>>  $episodes
     * @param  array<string, array<string, mixed>>  $shows
     * @param  array<int, string>  $failingEpisodes
     */
    public function __construct(
        public array $episodes,
        public array $shows,
        public array $failingEpisodes = [],
    ) {}

    public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
    {
        $id = basename(parse_url($episodeInput, PHP_URL_PATH) ?: $episodeInput);

        if (in_array($id, $this->failingEpisodes, true)) {
            throw new RuntimeException("Failed {$id}");
        }

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

class Tools1RecordingImporterThrottle extends ImporterThrottle
{
    public array $operations = [];

    public function wait(string $operation, int $attempt = 1): void
    {
        $this->operations[] = "{$operation}:{$attempt}";
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('formats markdown editors as one spreadsheet column with quoted cells', function (): void {
    $payload = SpreadsheetCellClipboard::oneColumnPayload([
        'First',
        "Line 1\nLine 2",
        'He said "yes"',
    ]);

    expect($payload)->toBe("\"First\"\n\"Line 1\nLine 2\"\n\"He said \"\"yes\"\"\"");

    $selected = SpreadsheetCellClipboard::oneColumnPayload([
        'Second',
        'Fourth',
    ]);

    expect($selected)->toBe("\"Second\"\n\"Fourth\"");
});

it('parses spotify links from text and csv with dedupe warnings and caps', function (): void {
    $csv = UploadedFile::fake()->createWithContent('spotify.csv', "url\nspotify:episode:csv11111111\n");
    $input = implode(' ', [
        'https://open.spotify.com/episode/episode11111',
        'spotify:episode:episode22222',
        'episode11111',
        'junk-value',
        'https://open.spotify.com/show/show11111111',
        'https://open.spotify.com/episode/episode33333',
    ]);

    $result = app(SpotifyLinkParser::class)->parse($input, SpotifyEntityMode::Episodes, 3, $csv);

    expect($result['items'])
        ->toHaveCount(3)
        ->sequence(
            fn ($item) => $item->id->toBe('episode11111'),
            fn ($item) => $item->id->toBe('episode22222'),
            fn ($item) => $item->id->toBe('episode33333'),
        )
        ->and(implode(' ', $result['warnings']))->toContain('junk-value')
        ->and(implode(' ', $result['warnings']))->toContain('show11111111')
        ->and(implode(' ', $result['warnings']))->toContain('3');
});

it('converts spotify html descriptions to markdown for breaks links lists and entities', function (): void {
    $markdown = app(SpotifyHtmlToMarkdown::class)->convert(
        '<p>Line&nbsp;one<br>Line two &amp; more</p><ul><li>First</li><li><a href="https://example.com">Link</a></li></ul><ol><li>One</li></ol>',
    );

    expect($markdown)->toContain('Line one')
        ->and($markdown)->toContain('Line two & more')
        ->and($markdown)->toContain('- First')
        ->and($markdown)->toContain('- [Link](https://example.com)')
        ->and($markdown)->toContain('1. One');
});

it('renders tools pages with translated tabs and blocks guests', function (): void {
    $this->get(AdminTools::getUrl())
        ->assertRedirect('/admin/login');

    $this->get(SpotifyLinksFetcher::getUrl())
        ->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create());

    Livewire::test(AdminTools::class)
        ->assertOk()
        ->assertSee(__('admin.tools.tabs.markdown'))
        ->assertSeeHtml('data-tools1-tabs')
        ->assertSeeHtml('data-tools1-rtl="rtl"');

    Livewire::test(SpotifyLinksFetcher::class)
        ->assertOk()
        ->assertSee(__('admin.spotify_fetcher.pages.title'))
        ->assertSeeHtml('data-tools1-spotify-fetcher');
});

it('fetches spotify episode rows with a fake lookup service and exports importer shaped csv rows', function (): void {
    $this->actingAs(User::factory()->create());
    $connection = ImportConnection::factory()->spotify()->create([
        'name' => 'Spotify connected',
        'status' => ImportConnectionStatus::Connected,
    ]);
    $group = ContentGroup::factory()->create(['title' => 'Existing Show']);
    $throttle = new Tools1RecordingImporterThrottle;

    app()->instance(ImporterThrottle::class, $throttle);
    app()->instance(EpisodeSpotifyLookup::class, new Tools1FakeSpotifyLookup(
        episodes: [
            'episode11111' => tools1EpisodeLookup('episode11111', 'Existing Show', 'show-existing'),
            'episode22222' => tools1EpisodeLookup('episode22222', 'Missing Show', 'show-missing'),
        ],
        shows: [
            'show-existing' => tools1ShowLookup('show-existing', 'Existing Show'),
            'show-missing' => tools1ShowLookup('show-missing', 'Missing Show'),
        ],
    ));

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', $connection->id)
        ->set('linksInput', 'spotify:episode:episode11111 spotify:episode:episode22222')
        ->call('fetch')
        ->assertSet('rows.0.status', 'fetched')
        ->assertSet('rows.0.content_group_reference_key', $group->reference_key)
        ->assertSet('rows.1.show_id', 'show-missing')
        ->assertSet('podcastRows.0.title', 'Missing Show')
        ->set('rows.0.title', 'Edited Episode');

    expect($throttle->operations)->toBe([
        'spotify.links_fetcher:1',
        'spotify.links_fetcher:2',
    ]);

    $episodeRows = $component->instance()->episodeCsvRows();
    $podcastRows = $component->instance()->podcastCsvRows();
    $builder = app(ImporterCsvBuilder::class);

    expect($episodeRows[0]['title'])->toBe('Edited Episode')
        ->and($episodeRows[0]['reference_key'])->toBe('')
        ->and($episodeRows[0]['content_group_reference_key'])->toBe($group->reference_key)
        ->and($podcastRows)->toHaveCount(1)
        ->and($podcastRows[0]['title'])->toBe('Missing Show');

    $episodeCsv = $builder->csv($builder->headersFor(ContentItemImporter::class), $episodeRows);
    $podcastCsv = $builder->csv($builder->headersFor(ContentGroupImporter::class), $podcastRows);

    expect(str_getcsv(strtok($episodeCsv, "\n")))->toBe($builder->headersFor(ContentItemImporter::class))
        ->and(str_getcsv(strtok($podcastCsv, "\n")))->toBe($builder->headersFor(ContentGroupImporter::class));
});

it('uses spotify oembed reduced fallback when no credentials are selected', function (): void {
    $this->actingAs(User::factory()->create());
    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response([
            'thumbnail_url' => 'https://i.scdn.co/image/reduced',
            'title' => 'Reduced episode title',
        ]),
    ]);

    Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', null)
        ->set('linksInput', 'https://open.spotify.com/episode/reduced11111')
        ->call('fetch')
        ->assertSet('usedReducedMode', true)
        ->assertSet('rows.0.status', 'reduced')
        ->assertSet('rows.0.title', 'Reduced episode title')
        ->assertSee(__('admin.spotify_fetcher.reduced_mode_label'));
});

it('isolates per row spotify errors and continues fetching later rows', function (): void {
    $this->actingAs(User::factory()->create());
    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response([], 500),
    ]);
    $connection = ImportConnection::factory()->spotify()->create([
        'status' => ImportConnectionStatus::Connected,
    ]);

    app()->instance(EpisodeSpotifyLookup::class, new Tools1FakeSpotifyLookup(
        episodes: [
            'good1111111' => tools1EpisodeLookup('good1111111', 'Good Show', 'show-good'),
        ],
        shows: [
            'show-good' => tools1ShowLookup('show-good', 'Good Show'),
        ],
        failingEpisodes: ['bad11111111'],
    ));

    Livewire::test(SpotifyLinksFetcher::class)
        ->set('connectionId', $connection->id)
        ->set('linksInput', 'spotify:episode:bad11111111 spotify:episode:good1111111')
        ->call('fetch')
        ->assertSet('rows.0.status', 'error')
        ->assertSet('rows.1.status', 'fetched')
        ->assertSet('rows.1.title', 'Episode good1111111');
});

it('enforces a maximum fetch batch of one hundred links', function (): void {
    $this->actingAs(User::factory()->create());

    $links = collect(range(1, 101))
        ->map(fn (int $index): string => 'episode'.str_pad((string) $index, 8, '0', STR_PAD_LEFT))
        ->implode(' ');

    $component = Livewire::test(SpotifyLinksFetcher::class)
        ->set('batchCap', 150)
        ->set('linksInput', $links)
        ->call('parseLinks')
        ->assertSet('batchCap', 100);

    expect($component->get('parsedLinks'))->toHaveCount(100);
});

/**
 * @return array<string, mixed>
 */
function tools1EpisodeLookup(string $episodeId, string $showName, string $showId): array
{
    return [
        'duration_seconds' => 321,
        'embed_url' => "https://open.spotify.com/embed/episode/{$episodeId}",
        'external_description' => 'Plain description',
        'external_id' => $episodeId,
        'external_thumbnail_url' => "https://i.scdn.co/image/{$episodeId}",
        'media_metadata' => [
            'html_description' => '<p>Hello<br>World</p>',
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
function tools1ShowLookup(string $showId, string $title): array
{
    return [
        'description' => "{$title} plain description",
        'external_id' => $showId,
        'external_url' => "https://open.spotify.com/show/{$showId}",
        'html_description' => "<p>{$title} <a href=\"https://example.com\">site</a></p>",
        'thumbnail' => "https://i.scdn.co/image/{$showId}",
        'title' => $title,
    ];
}
