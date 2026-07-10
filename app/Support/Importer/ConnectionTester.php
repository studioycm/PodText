<?php

namespace App\Support\Importer;

use App\Enums\ImportConnectionProvider;
use App\Models\ImportConnection;
use App\Support\Importer\Google\GoogleDriveConnector;
use App\Support\Importer\Spotify\SpotifyConnector;

class ConnectionTester
{
    public function __construct(
        private readonly GoogleDriveConnector $googleDrive,
        private readonly SpotifyConnector $spotify,
    ) {}

    public function test(ImportConnection $connection): ConnectionTestResult
    {
        return match ($connection->provider) {
            ImportConnectionProvider::GoogleDrive => $this->testGoogleDrive($connection),
            ImportConnectionProvider::Spotify => $this->testSpotify($connection),
            ImportConnectionProvider::Manual => new ConnectionTestResult(
                successful: true,
                title: __('admin.importer.test.manual_ready'),
            ),
        };
    }

    private function testGoogleDrive(ImportConnection $connection): ConnectionTestResult
    {
        $spreadsheetId = $connection->setting('spreadsheet_id');

        if (filled($spreadsheetId)) {
            $tabs = $this->googleDrive->listSpreadsheetTabs($connection, (string) $spreadsheetId);

            return new ConnectionTestResult(
                successful: true,
                title: __('admin.importer.test.google_sheets_connected'),
                details: [__('admin.importer.test.tabs', ['tabs' => implode(', ', $tabs)])],
            );
        }

        $folderId = $connection->setting('folder_id');

        if (filled($folderId)) {
            $files = $this->googleDrive->listFolderFiles($connection, (string) $folderId, 10);
            $names = collect($files)->pluck('name')->filter()->take(10)->implode(', ');

            return new ConnectionTestResult(
                successful: true,
                title: __('admin.importer.test.google_drive_connected'),
                details: [__('admin.importer.test.files', ['files' => $names])],
            );
        }

        return new ConnectionTestResult(
            successful: false,
            title: __('admin.importer.test.google_missing_defaults'),
        );
    }

    private function testSpotify(ImportConnection $connection): ConnectionTestResult
    {
        $ping = $this->spotify->ping($connection);

        return new ConnectionTestResult(
            successful: true,
            title: __('admin.importer.test.spotify_connected'),
            details: [__('admin.importer.test.spotify_profile', ['profile' => (string) data_get($ping, 'profile', 'client_credentials')])],
        );
    }
}
