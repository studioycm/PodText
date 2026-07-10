<?php

namespace App\Support\Importer\Google;

use App\Enums\ImportConnectionProvider;
use App\Models\ImportConnection;
use App\Support\Importer\Contracts\GoogleDriveClient;
use App\Support\Importer\Contracts\GoogleDriveClientFactory;
use App\Support\Importer\ImporterThrottle;
use Google\Service\Drive;
use Google\Service\Sheets;
use InvalidArgumentException;

class GoogleDriveConnector
{
    public function __construct(
        private readonly GoogleDriveClientFactory $clients,
        private readonly ImporterThrottle $throttle,
    ) {}

    /**
     * @return array<int, string>
     */
    public static function scopes(): array
    {
        return [
            Drive::DRIVE_READONLY,
            Sheets::SPREADSHEETS_READONLY,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function listSpreadsheetTabs(ImportConnection $connection, ?string $spreadsheetId = null): array
    {
        $spreadsheetId = $this->requiredIdentifier($spreadsheetId ?? $connection->setting('spreadsheet_id'), 'spreadsheet_id');

        $this->throttle->wait('google.sheets.tabs');

        return $this->client($connection)->listSpreadsheetTabs($spreadsheetId);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function readSheetRange(ImportConnection $connection, string $tab, string $range, ?string $spreadsheetId = null): array
    {
        $spreadsheetId = $this->requiredIdentifier($spreadsheetId ?? $connection->setting('spreadsheet_id'), 'spreadsheet_id');

        $this->throttle->wait('google.sheets.range');

        return $this->client($connection)->readSheetRange($spreadsheetId, $tab, $range);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listFolderFiles(ImportConnection $connection, ?string $folderId = null, int $limit = 100): array
    {
        $folderId = $this->requiredIdentifier($folderId ?? $connection->setting('folder_id'), 'folder_id');

        $this->throttle->wait('google.drive.folder');

        return $this->client($connection)->listFolderFiles($folderId, $limit);
    }

    public function exportDocMarkdown(ImportConnection $connection, string $documentId): string
    {
        $this->throttle->wait('google.drive.doc_export');

        return $this->client($connection)->exportDocMarkdown($documentId);
    }

    public function downloadFile(ImportConnection $connection, string $fileId): string
    {
        $this->throttle->wait('google.drive.download');

        return $this->client($connection)->downloadFile($fileId);
    }

    private function client(ImportConnection $connection): GoogleDriveClient
    {
        if ($connection->provider !== ImportConnectionProvider::GoogleDrive) {
            throw new InvalidArgumentException('Connection is not a Google Drive connection.');
        }

        $client = $this->clients->make($connection);
        $refreshedCredentials = $client->refreshAccessTokenIfNeeded($connection);

        if ($refreshedCredentials !== null) {
            $connection->forceFill([
                'credentials' => [
                    ...($connection->credentials ?? []),
                    ...$refreshedCredentials,
                ],
            ])->save();
        }

        return $client;
    }

    private function requiredIdentifier(mixed $value, string $key): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException("Google Drive connection setting [{$key}] is missing.");
        }

        return $value;
    }
}
