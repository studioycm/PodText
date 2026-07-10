<?php

namespace Database\Factories;

use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Models\ImportConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportConnection>
 */
class ImportConnectionFactory extends Factory
{
    protected $model = ImportConnection::class;

    public function definition(): array
    {
        return [
            'auth_type' => ImportConnectionAuthType::None,
            'credentials' => [],
            'name' => $this->faker->words(2, true),
            'provider' => ImportConnectionProvider::Manual,
            'settings' => [],
            'status' => ImportConnectionStatus::Untested,
        ];
    }

    public function googleServiceAccount(): self
    {
        return $this->state(fn (): array => [
            'auth_type' => ImportConnectionAuthType::ServiceAccount,
            'credentials' => [
                'service_account' => [
                    'client_email' => 'podtext-importer@example.iam.gserviceaccount.com',
                    'private_key' => 'fake-private-key',
                    'type' => 'service_account',
                ],
            ],
            'provider' => ImportConnectionProvider::GoogleDrive,
            'settings' => [
                'folder_id' => 'fake-folder',
                'spreadsheet_id' => 'fake-sheet',
            ],
        ]);
    }

    public function googleOAuth(): self
    {
        return $this->state(fn (): array => [
            'auth_type' => ImportConnectionAuthType::OAuth,
            'credentials' => [
                'access_token' => 'fake-access-token',
                'expires_at' => now()->addHour()->toIso8601String(),
                'refresh_token' => 'fake-refresh-token',
            ],
            'provider' => ImportConnectionProvider::GoogleDrive,
            'settings' => [
                'spreadsheet_id' => 'fake-sheet',
            ],
        ]);
    }

    public function spotify(): self
    {
        return $this->state(fn (): array => [
            'auth_type' => ImportConnectionAuthType::ClientCredentials,
            'credentials' => [
                'client_id' => 'fake-client-id',
                'client_secret' => 'fake-client-secret',
            ],
            'provider' => ImportConnectionProvider::Spotify,
        ]);
    }
}
