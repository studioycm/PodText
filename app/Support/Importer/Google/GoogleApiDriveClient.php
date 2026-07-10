<?php

namespace App\Support\Importer\Google;

use App\Enums\ImportConnectionAuthType;
use App\Models\ImportConnection;
use App\Support\Importer\Contracts\GoogleDriveClient;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Sheets;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class GoogleApiDriveClient implements GoogleDriveClient
{
    public function __construct(
        private readonly GoogleClient $client,
    ) {}

    public function listSpreadsheetTabs(string $spreadsheetId): array
    {
        $spreadsheet = (new Sheets($this->client))->spreadsheets->get($spreadsheetId, [
            'fields' => 'sheets.properties.title',
        ]);

        return collect($spreadsheet->getSheets())
            ->map(fn ($sheet): ?string => $sheet->getProperties()?->getTitle())
            ->filter()
            ->values()
            ->all();
    }

    public function readSheetRange(string $spreadsheetId, string $tab, string $range): array
    {
        $valueRange = (new Sheets($this->client))->spreadsheets_values->get(
            $spreadsheetId,
            $this->sheetRange($tab, $range),
        );

        return $valueRange->getValues() ?? [];
    }

    public function listFolderFiles(string $folderId, int $limit = 100): array
    {
        $files = (new Drive($this->client))->files->listFiles([
            'fields' => 'files(id,name,mimeType,modifiedTime,webViewLink)',
            'pageSize' => $limit,
            'q' => "'{$folderId}' in parents and trashed = false",
        ]);

        return collect($files->getFiles())
            ->map(fn ($file): array => [
                'id' => $file->getId(),
                'mime_type' => $file->getMimeType(),
                'modified_time' => $file->getModifiedTime(),
                'name' => $file->getName(),
                'web_view_link' => $file->getWebViewLink(),
            ])
            ->all();
    }

    public function exportDocMarkdown(string $documentId): string
    {
        $response = (new Drive($this->client))->files->export($documentId, 'text/markdown', [
            'alt' => 'media',
        ]);

        return $this->responseContents($response);
    }

    public function downloadFile(string $fileId): string
    {
        $response = (new Drive($this->client))->files->get($fileId, [
            'alt' => 'media',
        ]);

        return $this->responseContents($response);
    }

    public function refreshAccessTokenIfNeeded(ImportConnection $connection): ?array
    {
        if ($connection->auth_type !== ImportConnectionAuthType::OAuth || ! $this->client->isAccessTokenExpired()) {
            return null;
        }

        $refreshToken = (string) data_get($connection->credentials, 'refresh_token');

        if ($refreshToken === '') {
            throw new RuntimeException('Google OAuth refresh token is missing.');
        }

        $token = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new RuntimeException('Google OAuth token refresh failed.');
        }

        $expiresIn = (int) ($token['expires_in'] ?? 3600);

        return [
            'access_token' => $token['access_token'] ?? data_get($connection->credentials, 'access_token'),
            'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
            'expires_in' => $expiresIn,
            'refresh_token' => $token['refresh_token'] ?? $refreshToken,
            'scope' => $token['scope'] ?? implode(' ', GoogleDriveConnector::scopes()),
            'token_type' => $token['token_type'] ?? 'Bearer',
        ];
    }

    private function sheetRange(string $tab, string $range): string
    {
        $escapedTab = str_replace("'", "''", $tab);

        return "'{$escapedTab}'!{$range}";
    }

    private function responseContents(mixed $response): string
    {
        if ($response instanceof ResponseInterface) {
            return $response->getBody()->getContents();
        }

        if (is_string($response)) {
            return $response;
        }

        if (method_exists($response, 'getBody')) {
            return (string) $response->getBody();
        }

        throw new RuntimeException('Google Drive response did not contain downloadable content.');
    }
}
