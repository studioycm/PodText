<?php

namespace App\Support\Importer\SpotifyLinks;

use App\Support\Importer\ImporterThrottle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SpotifyOpenGraphClient
{
    private const CACHE_TTL_SECONDS = 21600;

    private const HOST = 'open.spotify.com';

    private const MAX_REDIRECTS = 3;

    public function __construct(
        private readonly ImporterThrottle $throttle,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     title: string|null,
     *     description: string|null,
     *     image: string|null,
     *     canonical_url: string|null,
     *     duration_seconds: int|null,
     *     release_date: string|null,
     *     show_name: string|null
     * }|null
     */
    public function fetch(string $url): ?array
    {
        $canonicalUrl = $this->canonicalUrl($url);

        if ($canonicalUrl === null) {
            return null;
        }

        $cached = Cache::remember(
            $this->cacheKey($canonicalUrl),
            self::CACHE_TTL_SECONDS,
            fn (): array => ['result' => $this->fetchUncached($canonicalUrl)],
        );

        return is_array($cached) ? ($cached['result'] ?? null) : null;
    }

    /**
     * @return array{
     *     type: string,
     *     title: string|null,
     *     description: string|null,
     *     image: string|null,
     *     canonical_url: string|null,
     *     duration_seconds: int|null,
     *     release_date: string|null,
     *     show_name: string|null
     * }|null
     */
    private function fetchUncached(string $url): ?array
    {
        try {
            $this->throttle->wait('spotify.opengraph');

            $response = $this->request($url);

            if (! $response?->successful()) {
                return null;
            }

            return $this->parse($response->body(), $url);
        } catch (Throwable) {
            return null;
        }
    }

    private function request(string $url, int $redirects = 0): ?Response
    {
        $response = Http::withUserAgent('PodText Spotify reduced-mode fetcher/1.0')
            ->accept('text/html,application/xhtml+xml')
            ->connectTimeout(4)
            ->timeout(8)
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if ($response->status() < 300 || $response->status() >= 400) {
            return $response;
        }

        if ($redirects >= self::MAX_REDIRECTS) {
            return null;
        }

        $redirectUrl = $this->redirectUrl($url, $response->header('Location'));

        if ($redirectUrl === null) {
            return null;
        }

        return $this->request($redirectUrl, $redirects + 1);
    }

    /**
     * @return array{
     *     type: string,
     *     title: string|null,
     *     description: string|null,
     *     image: string|null,
     *     canonical_url: string|null,
     *     duration_seconds: int|null,
     *     release_date: string|null,
     *     show_name: string|null
     * }|null
     */
    private function parse(string $html, string $url): ?array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $structured = $this->structuredData($xpath);
        $type = $this->typeFromUrl($url) ?? 'episode';
        $ogDescription = $this->meta($xpath, 'og:description');
        $title = $this->clean(data_get($structured, 'name')) ?: $this->meta($xpath, 'og:title');
        $description = $this->clean(data_get($structured, 'description')) ?: $ogDescription;
        $image = $this->imageFromStructuredData($structured) ?: $this->meta($xpath, 'og:image');
        $releaseDate = $this->clean(data_get($structured, 'datePublished')) ?: $this->meta($xpath, 'music:release_date');
        $duration = $this->durationSeconds(data_get($structured, 'duration'))
            ?? $this->durationSeconds($this->meta($xpath, 'music:duration'));
        $showName = $this->clean(data_get($structured, 'partOfSeries.name'));

        if ($type === 'episode' && $showName === null) {
            $showName = $this->showNameFromOgDescription($ogDescription);
        }

        return [
            'canonical_url' => $this->meta($xpath, 'og:url') ?: $url,
            'description' => $description,
            'duration_seconds' => $duration,
            'image' => $image,
            'release_date' => $releaseDate,
            'show_name' => $showName,
            'title' => $title,
            'type' => $type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//script[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "application/ld+json"]');

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            $json = trim($node->textContent);

            if ($json === '') {
                continue;
            }

            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->structuredCandidates($decoded) as $candidate) {
                if ($this->hasUsefulStructuredData($candidate)) {
                    return $candidate;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<int, array<string, mixed>>
     */
    private function structuredCandidates(array $decoded): array
    {
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return collect($decoded['@graph'])
                ->filter(fn (mixed $candidate): bool => is_array($candidate))
                ->values()
                ->all();
        }

        if (array_is_list($decoded)) {
            return collect($decoded)
                ->filter(fn (mixed $candidate): bool => is_array($candidate))
                ->values()
                ->all();
        }

        return [$decoded];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function hasUsefulStructuredData(array $candidate): bool
    {
        return filled($candidate['name'] ?? null)
            || filled($candidate['description'] ?? null)
            || filled($candidate['datePublished'] ?? null);
    }

    private function meta(DOMXPath $xpath, string $name): ?string
    {
        $escaped = $this->xpathLiteral(mb_strtolower($name));
        $query = '//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = '.$escaped
            .' or translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = '.$escaped.']';
        $node = $xpath->query($query)?->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        return $this->clean($node->getAttribute('content'));
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function imageFromStructuredData(array $structured): ?string
    {
        $image = $structured['image'] ?? null;

        if (is_string($image)) {
            return $this->clean($image);
        }

        if (is_array($image)) {
            $value = $image['url'] ?? $image[0] ?? null;

            return is_string($value) ? $this->clean($value) : null;
        }

        return null;
    }

    private function durationSeconds(mixed $value): ?int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $value, $matches) !== 1) {
            return null;
        }

        return ((int) ($matches[1] ?? 0) * 86400)
            + ((int) ($matches[2] ?? 0) * 3600)
            + ((int) ($matches[3] ?? 0) * 60)
            + (int) ($matches[4] ?? 0);
    }

    private function canonicalUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== self::HOST) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (preg_match('#^/(?:intl-[a-z]{2}/)?(episode|show)/([A-Za-z0-9]+)#i', $path, $matches) !== 1) {
            return null;
        }

        return 'https://'.self::HOST.'/'.mb_strtolower($matches[1]).'/'.$matches[2];
    }

    private function redirectUrl(string $baseUrl, ?string $location): ?string
    {
        if (blank($location)) {
            return null;
        }

        $location = trim((string) $location);

        if (str_starts_with($location, '/')) {
            $location = 'https://'.self::HOST.$location;
        } elseif (! str_starts_with($location, 'https://')) {
            $base = parse_url($baseUrl);
            $path = rtrim(dirname($base['path'] ?? '/'), '/');
            $location = 'https://'.self::HOST.$path.'/'.$location;
        }

        $parts = parse_url($location);

        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== self::HOST) {
            return null;
        }

        return $this->canonicalUrl($location);
    }

    private function typeFromUrl(string $url): ?string
    {
        return preg_match('#/((?:episode)|(?:show))/[A-Za-z0-9]+#', $url, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function showNameFromOgDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        if (preg_match('/^(.+?)\s*(?:\x{00B7}|\||-)\s*Episode$/u', $description, $matches) !== 1) {
            return null;
        }

        return $this->clean($matches[1]);
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace("/\r\n?/", "\n", $value) ?? $value;
        $value = preg_replace("/[ \t]+\n/u", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function cacheKey(string $url): string
    {
        return 'spotify:opengraph:'.sha1($url);
    }

    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        return "concat('".str_replace("'", "', \"'\", '", $value)."')";
    }
}
