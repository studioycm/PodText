<?php

namespace App\Support\Importer\Spotify;

use App\Models\ImportConnection;
use App\Support\Importer\Contracts\SpotifyClient;
use App\Support\Importer\Contracts\SpotifyClientFactory;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpotifyHttpClientFactory implements SpotifyClientFactory
{
    public function make(ImportConnection $connection): SpotifyClient
    {
        $clientId = (string) data_get($connection->credentials, 'client_id');
        $clientSecret = (string) data_get($connection->credentials, 'client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Spotify client credentials are missing.');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(15)
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'client_credentials',
            ])
            ->throw()
            ->json();

        return new SpotifyHttpClient((string) data_get($response, 'access_token'));
    }
}
