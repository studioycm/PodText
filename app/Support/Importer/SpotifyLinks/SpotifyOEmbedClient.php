<?php

namespace App\Support\Importer\SpotifyLinks;

use App\Support\Importer\ImporterThrottle;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyOEmbedClient
{
    private const CACHE_TTL_SECONDS = 21600;

    public function __construct(
        private readonly ImporterThrottle $throttle,
    ) {}

    /**
     * @return array{title: string|null, thumbnail: string|null, html: string|null, embed_url: string|null}
     */
    public function fetch(string $url): array
    {
        $cached = Cache::remember(
            $this->cacheKey($url),
            self::CACHE_TTL_SECONDS,
            fn (): array => ['result' => $this->fetchUncached($url)],
        );

        return $cached['result'];
    }

    /**
     * @return array{title: string|null, thumbnail: string|null, html: string|null, embed_url: string|null}
     */
    private function fetchUncached(string $url): array
    {
        $this->throttle->wait('spotify.oembed');

        $response = Http::acceptJson()
            ->withUserAgent('PodText Spotify reduced-mode fetcher/1.0')
            ->connectTimeout(4)
            ->timeout(10)
            ->get('https://open.spotify.com/oembed', [
                'url' => $url,
            ])
            ->throw()
            ->json();
        $html = data_get($response, 'html');

        return [
            'embed_url' => $this->embedUrlFromHtml(is_string($html) ? $html : null),
            'html' => is_string($html) ? $html : null,
            'thumbnail' => data_get($response, 'thumbnail_url'),
            'title' => data_get($response, 'title'),
        ];
    }

    private function embedUrlFromHtml(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('iframe') as $iframe) {
            if (! $iframe instanceof DOMElement) {
                continue;
            }

            $src = trim($iframe->getAttribute('src'));

            if (str_starts_with($src, 'https://open.spotify.com/embed/')) {
                return $src;
            }
        }

        return null;
    }

    private function cacheKey(string $url): string
    {
        return 'spotify:oembed:'.sha1($url);
    }
}
