<?php

namespace App\Support\Importer\Spotify;

use App\Support\Importer\Contracts\SpotifyClient;
use Illuminate\Support\Facades\Http;

class SpotifyHttpClient implements SpotifyClient
{
    public function __construct(
        private readonly string $accessToken,
    ) {}

    public function fetchEpisode(string $spotifyId): array
    {
        $episode = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get("https://api.spotify.com/v1/episodes/{$spotifyId}", [
                'market' => 'IL',
            ])
            ->throw()
            ->json();

        $images = data_get($episode, 'images', []);
        $image = is_array($images) ? ($images[0]['url'] ?? null) : null;

        return [
            'description' => data_get($episode, 'description'),
            'duration' => data_get($episode, 'duration_ms') ? (int) floor(((int) data_get($episode, 'duration_ms')) / 1000) : null,
            'embed_url' => data_get($episode, 'id') ? 'https://open.spotify.com/embed/episode/'.data_get($episode, 'id') : null,
            'external_id' => data_get($episode, 'id'),
            'external_url' => data_get($episode, 'external_urls.spotify'),
            'html_description' => data_get($episode, 'html_description'),
            'release_date' => data_get($episode, 'release_date'),
            'show' => data_get($episode, 'show.name'),
            'show_external_url' => data_get($episode, 'show.external_urls.spotify'),
            'show_id' => data_get($episode, 'show.id'),
            'thumbnail' => $image,
            'title' => data_get($episode, 'name'),
            'uri' => data_get($episode, 'uri'),
        ];
    }

    public function fetchShow(string $spotifyId): array
    {
        $show = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get("https://api.spotify.com/v1/shows/{$spotifyId}", [
                'market' => 'IL',
            ])
            ->throw()
            ->json();

        $images = data_get($show, 'images', []);
        $image = is_array($images) ? ($images[0]['url'] ?? null) : null;

        return [
            'description' => data_get($show, 'description'),
            'external_id' => data_get($show, 'id'),
            'external_url' => data_get($show, 'external_urls.spotify'),
            'html_description' => data_get($show, 'html_description'),
            'languages' => data_get($show, 'languages', []),
            'publisher' => data_get($show, 'publisher'),
            'thumbnail' => $image,
            'title' => data_get($show, 'name'),
            'total_episodes' => data_get($show, 'total_episodes'),
            'uri' => data_get($show, 'uri'),
        ];
    }

    public function ping(): array
    {
        return [
            'profile' => 'client_credentials',
            'token' => filled($this->accessToken) ? 'available' : 'missing',
        ];
    }
}
