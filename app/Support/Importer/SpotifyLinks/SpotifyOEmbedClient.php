<?php

namespace App\Support\Importer\SpotifyLinks;

use Illuminate\Support\Facades\Http;

class SpotifyOEmbedClient
{
    /**
     * @return array{title: string|null, thumbnail: string|null}
     */
    public function fetch(string $url): array
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->get('https://open.spotify.com/oembed', [
                'url' => $url,
            ])
            ->throw()
            ->json();

        return [
            'thumbnail' => data_get($response, 'thumbnail_url'),
            'title' => data_get($response, 'title'),
        ];
    }
}
