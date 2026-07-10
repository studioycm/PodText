<?php

use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Filament\Pages\ImporterSettings;
use App\Models\ImportConnection;
use App\Models\User;
use App\Support\Importer\ConnectionTester;
use App\Support\Importer\ConnectionTestResult;
use App\Support\Importer\Contracts\GoogleDriveClient;
use App\Support\Importer\Contracts\GoogleDriveClientFactory;
use App\Support\Importer\Contracts\SpotifyClient;
use App\Support\Importer\Contracts\SpotifyClientFactory;
use App\Support\Importer\Google\GoogleDriveConnector;
use App\Support\Importer\ImporterThrottle;
use App\Support\Importer\Spotify\SpotifyConnector;
use App\Support\Importer\TranscriptFormatProbePaths;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class FakeImporterGoogleClient implements GoogleDriveClient
{
    /**
     * @param  array<string, mixed>|null  $refreshedCredentials
     */
    public function __construct(
        public ?array $refreshedCredentials = null,
    ) {}

    public array $calls = [];

    public function listSpreadsheetTabs(string $spreadsheetId): array
    {
        $this->calls[] = "tabs:{$spreadsheetId}";

        return ['פרקים שעלו לאוויר', 'Archive'];
    }

    public function readSheetRange(string $spreadsheetId, string $tab, string $range): array
    {
        $this->calls[] = "range:{$spreadsheetId}:{$tab}:{$range}";

        return [
            ['Title', 'Spotify'],
            ['Episode 1', 'spotify:episode:123'],
        ];
    }

    public function listFolderFiles(string $folderId, int $limit = 100): array
    {
        $this->calls[] = "folder:{$folderId}:{$limit}";

        return [
            ['id' => 'file-1', 'name' => 'Image 1.png'],
        ];
    }

    public function exportDocMarkdown(string $documentId): string
    {
        $this->calls[] = "export:{$documentId}";

        return "# {$documentId}\n\n00:01 Speaker: Hello\n\n**Bold closer**";
    }

    public function downloadFile(string $fileId): string
    {
        $this->calls[] = "download:{$fileId}";

        return 'file-bytes';
    }

    public function refreshAccessTokenIfNeeded(ImportConnection $connection): ?array
    {
        $this->calls[] = 'refresh';

        return $this->refreshedCredentials;
    }
}

class FakeImporterGoogleClientFactory implements GoogleDriveClientFactory
{
    public function __construct(
        public FakeImporterGoogleClient $client,
    ) {}

    public function make(ImportConnection $connection): GoogleDriveClient
    {
        return $this->client;
    }
}

class RecordingImporterThrottle extends ImporterThrottle
{
    public array $operations = [];

    public function wait(string $operation, int $attempt = 1): void
    {
        $this->operations[] = $operation;
    }
}

class FakeImporterSpotifyClient implements SpotifyClient
{
    public function fetchEpisode(string $spotifyId): array
    {
        return [
            'duration' => 1234,
            'release_date' => '2026-07-10',
            'show' => 'PodText Show',
            'thumbnail' => 'https://i.scdn.co/image/fake',
            'title' => "Episode {$spotifyId}",
        ];
    }

    public function ping(): array
    {
        return [
            'profile' => 'client_credentials',
        ];
    }
}

class FakeImporterSpotifyClientFactory implements SpotifyClientFactory
{
    public function make(ImportConnection $connection): SpotifyClient
    {
        return new FakeImporterSpotifyClient;
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
});

it('encrypts credentials and validates provider auth combinations while normalizing settings', function (): void {
    $connection = ImportConnection::factory()->googleServiceAccount()->create([
        'credentials' => [
            'service_account' => [
                'client_email' => 'fake@example.iam.gserviceaccount.com',
                'private_key' => 'plain-secret-key',
            ],
        ],
        'settings' => [
            'folder_id' => '',
            'nested' => [
                'blank' => '',
                'value' => ' kept ',
            ],
            'spreadsheet_id' => ' sheet-id ',
        ],
    ]);

    $rawCredentials = DB::table('import_connections')
        ->where('id', $connection->id)
        ->value('credentials');

    expect($rawCredentials)->not->toContain('plain-secret-key')
        ->and($connection->refresh()->credentials['service_account']['private_key'])->toBe('plain-secret-key')
        ->and($connection->settings)->toBe([
            'nested' => [
                'value' => 'kept',
            ],
            'spreadsheet_id' => 'sheet-id',
        ]);

    expect(fn () => ImportConnection::factory()->create([
        'auth_type' => ImportConnectionAuthType::OAuth,
        'provider' => ImportConnectionProvider::Spotify,
    ]))->toThrow(ValidationException::class);
});

it('uses fake google clients for tab listing range reading doc export downloads token refresh and throttling', function (): void {
    $connection = ImportConnection::factory()->googleOAuth()->create([
        'credentials' => [
            'access_token' => 'old-token',
            'expires_at' => now()->subMinute()->toIso8601String(),
            'refresh_token' => 'refresh-token',
        ],
    ]);
    $fakeClient = new FakeImporterGoogleClient([
        'access_token' => 'new-token',
        'expires_at' => now()->addHour()->toIso8601String(),
        'refresh_token' => 'refresh-token',
    ]);
    $throttle = new RecordingImporterThrottle;
    $connector = new GoogleDriveConnector(new FakeImporterGoogleClientFactory($fakeClient), $throttle);

    expect($connector->listSpreadsheetTabs($connection))->toBe(['פרקים שעלו לאוויר', 'Archive'])
        ->and($connector->readSheetRange($connection, 'פרקים שעלו לאוויר', 'A2:B3'))->toBe([
            ['Title', 'Spotify'],
            ['Episode 1', 'spotify:episode:123'],
        ])
        ->and($connector->listFolderFiles($connection, 'folder-1', 5))->toHaveCount(1)
        ->and($connector->exportDocMarkdown($connection, 'doc-1'))->toContain('Speaker: Hello')
        ->and($connector->downloadFile($connection, 'file-1'))->toBe('file-bytes');

    expect($connection->refresh()->credentials['access_token'])->toBe('new-token')
        ->and($fakeClient->calls)->toContain('tabs:fake-sheet')
        ->and($fakeClient->calls)->toContain('range:fake-sheet:פרקים שעלו לאוויר:A2:B3')
        ->and($fakeClient->calls)->toContain('export:doc-1')
        ->and($throttle->operations)->toContain('google.sheets.tabs')
        ->and($throttle->operations)->toContain('google.drive.doc_export');
});

it('uses a fake spotify client for episode metadata and profile ping', function (): void {
    $connection = ImportConnection::factory()->spotify()->create();
    $connector = new SpotifyConnector(new FakeImporterSpotifyClientFactory);

    expect($connector->fetchEpisode($connection, 'spotify-episode-id'))->toMatchArray([
        'duration' => 1234,
        'release_date' => '2026-07-10',
        'show' => 'PodText Show',
        'thumbnail' => 'https://i.scdn.co/image/fake',
        'title' => 'Episode spotify-episode-id',
    ])
        ->and($connector->ping($connection))->toBe(['profile' => 'client_credentials']);
});

it('renders importer settings progressive disclosure and wires create and test actions', function (): void {
    app()->instance(ConnectionTester::class, new class
    {
        public function test(ImportConnection $connection): ConnectionTestResult
        {
            return new ConnectionTestResult(
                successful: true,
                title: 'Fake connection ok',
                details: ['Tabs: פרקים שעלו לאוויר'],
            );
        }
    });

    Livewire::test(ImporterSettings::class)
        ->assertOk()
        ->mountAction(TestAction::make('createConnection')->table())
        ->assertSchemaComponentHidden('auth_type')
        ->assertSchemaComponentHidden('service_account_json')
        ->set('mountedActions.0.data.provider', ImportConnectionProvider::GoogleDrive->value)
        ->assertSchemaComponentVisible('auth_type')
        ->set('mountedActions.0.data.auth_type', ImportConnectionAuthType::ServiceAccount->value)
        ->assertSchemaComponentVisible('service_account_json')
        ->assertSchemaComponentHidden('spotify_client_secret')
        ->assertSchemaComponentVisible('settings.spreadsheet_id')
        ->set('mountedActions.0.data.name', 'Google Sheet')
        ->set('mountedActions.0.data.service_account_json', json_encode([
            'client_email' => 'fake@example.iam.gserviceaccount.com',
            'private_key' => 'fake-key',
            'type' => 'service_account',
        ], JSON_THROW_ON_ERROR))
        ->set('mountedActions.0.data.settings.spreadsheet_id', 'sheet-123')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $connection = ImportConnection::query()->where('name', 'Google Sheet')->firstOrFail();

    Livewire::test(ImporterSettings::class)
        ->assertCanSeeTableRecords([$connection])
        ->assertActionVisible(TestAction::make('testConnection')->table($connection))
        ->callAction(TestAction::make('testConnection')->table($connection));

    expect($connection->refresh()->status)->toBe(ImportConnectionStatus::Connected)
        ->and($connection->last_tested_at)->not->toBeNull();
});

it('probes google document formats through the app connector and writes resumable samples and findings', function (): void {
    $sampleDirectory = storage_path('framework/testing/importer-probe/samples');
    $findingsPath = storage_path('framework/testing/importer-probe/01-transcript-format-probe.md');

    File::deleteDirectory(dirname($sampleDirectory));

    app()->instance(TranscriptFormatProbePaths::class, new TranscriptFormatProbePaths(
        sampleDirectory: $sampleDirectory,
        findingsPath: $findingsPath,
    ));
    app()->instance(GoogleDriveClientFactory::class, new FakeImporterGoogleClientFactory(new FakeImporterGoogleClient));

    $connection = ImportConnection::factory()->googleServiceAccount()->create();

    $this->artisan('importer:probe-formats', [
        '--ids' => 'doc123456789,doc456789012',
        '--limit' => 1,
        'connection' => $connection->getKey(),
    ])->assertSuccessful();

    expect(File::exists($sampleDirectory.'/doc123456789.md'))->toBeTrue()
        ->and(File::get($sampleDirectory.'/doc123456789.md'))->toContain('Speaker: Hello')
        ->and(File::get($findingsPath))->toContain('timestamped_dialogue')
        ->and(File::get($findingsPath))->toContain('doc123456789');
});
