<?php

namespace App\Support\Importer\Spotify;

use App\Enums\ImportConnectionProvider;
use App\Models\ImportConnection;
use App\Support\Importer\Contracts\SpotifyClient;
use App\Support\Importer\Contracts\SpotifyClientFactory;
use InvalidArgumentException;

class SpotifyConnector
{
    public function __construct(
        private readonly SpotifyClientFactory $clients,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetchEpisode(ImportConnection $connection, string $spotifyId): array
    {
        return $this->client($connection)->fetchEpisode($spotifyId);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchShow(ImportConnection $connection, string $spotifyId): array
    {
        return $this->client($connection)->fetchShow($spotifyId);
    }

    /**
     * @return array<string, mixed>
     */
    public function ping(ImportConnection $connection): array
    {
        return $this->client($connection)->ping();
    }

    private function client(ImportConnection $connection): SpotifyClient
    {
        if ($connection->provider !== ImportConnectionProvider::Spotify) {
            throw new InvalidArgumentException('Connection is not a Spotify connection.');
        }

        return $this->clients->make($connection);
    }
}
