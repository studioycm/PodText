<?php

namespace App\Support\Importer\Google;

use App\Enums\ImportConnectionAuthType;
use App\Models\ImportConnection;
use App\Support\Importer\Contracts\GoogleDriveClient;
use App\Support\Importer\Contracts\GoogleDriveClientFactory;
use Carbon\CarbonImmutable;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Sheets;
use RuntimeException;

class GoogleApiDriveClientFactory implements GoogleDriveClientFactory
{
    public function make(ImportConnection $connection): GoogleDriveClient
    {
        $client = new GoogleClient;
        $client->setApplicationName(config('app.name').' Importer Workbench');
        $client->setScopes([
            Drive::DRIVE_READONLY,
            Sheets::SPREADSHEETS_READONLY,
        ]);
        $client->setAccessType('offline');

        match ($connection->auth_type) {
            ImportConnectionAuthType::ServiceAccount => $this->configureServiceAccount($client, $connection),
            ImportConnectionAuthType::OAuth => $this->configureOAuth($client, $connection),
            default => throw new RuntimeException('Google Drive connections require service-account or OAuth credentials.'),
        };

        return new GoogleApiDriveClient($client);
    }

    private function configureServiceAccount(GoogleClient $client, ImportConnection $connection): void
    {
        $serviceAccount = data_get($connection->credentials, 'service_account', $connection->credentials);

        if (! is_array($serviceAccount) || $serviceAccount === []) {
            throw new RuntimeException('Google service-account credentials are missing.');
        }

        $client->setAuthConfig($serviceAccount);
    }

    private function configureOAuth(GoogleClient $client, ImportConnection $connection): void
    {
        $credentials = $connection->credentials ?? [];
        $accessToken = (string) data_get($credentials, 'access_token');
        $refreshToken = (string) data_get($credentials, 'refresh_token');

        if ($accessToken === '' && $refreshToken === '') {
            throw new RuntimeException('Google OAuth credentials are missing.');
        }

        $expiresIn = (int) data_get($credentials, 'expires_in', 3600);
        $expiresAt = data_get($credentials, 'expires_at');
        $created = $expiresAt
            ? CarbonImmutable::parse($expiresAt)->subSeconds($expiresIn)->timestamp
            : 0;

        $client->setAccessToken(array_filter([
            'access_token' => $accessToken,
            'created' => $created,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'scope' => implode(' ', GoogleDriveConnector::scopes()),
            'token_type' => data_get($credentials, 'token_type', 'Bearer'),
        ]));
    }
}
