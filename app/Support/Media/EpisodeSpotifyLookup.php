<?php

namespace App\Support\Media;

use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Models\ImportConnection;
use App\Support\Importer\Spotify\SpotifyConnector;
use App\Support\Importer\SpotifyLinks\SpotifyHtmlToMarkdown;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class EpisodeSpotifyLookup
{
    public function __construct(
        private readonly SpotifyConnector $spotify,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
    {
        $spotifyId = $this->episodeId($episodeInput);

        if ($spotifyId === null) {
            throw new InvalidArgumentException('The Spotify episode value is not a valid episode URL, URI, or ID.');
        }

        $connection ??= $this->defaultConnection();
        $episode = $this->spotify->fetchEpisode($connection, $spotifyId);

        $duration = $episode['duration'] ?? null;
        $releaseDate = $this->releaseDate($episode['release_date'] ?? null);
        $externalUrl = $episode['external_url'] ?? "https://open.spotify.com/episode/{$spotifyId}";
        $descriptionMarkdown = $this->descriptionMarkdown(
            $episode['html_description'] ?? null,
            $episode['description'] ?? null,
        );

        return [
            'description_markdown' => $descriptionMarkdown,
            'title' => $episode['title'] ?? null,
            'title_prefix' => $episode['show'] ?? null,
            'media_url' => $externalUrl,
            'embed_url' => $episode['embed_url'] ?? "https://open.spotify.com/embed/episode/{$spotifyId}",
            'embed_provider' => 'spotify',
            'external_id' => $episode['external_id'] ?? $spotifyId,
            'external_title' => $episode['title'] ?? null,
            'external_description' => $episode['description'] ?? null,
            'external_thumbnail_url' => $episode['thumbnail'] ?? null,
            'duration_seconds' => $duration,
            'media_duration_seconds' => $duration,
            'original_published_at' => $releaseDate,
            'external_published_at' => $releaseDate,
            'media_metadata' => [
                'provider' => 'spotify',
                'episode_id' => $spotifyId,
                'episode_uri' => $episode['uri'] ?? null,
                'show' => $episode['show'] ?? null,
                'show_external_url' => $episode['show_external_url'] ?? null,
                'show_id' => $episode['show_id'] ?? null,
                'html_description' => $episode['html_description'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupShow(string $showInput, ?ImportConnection $connection = null): array
    {
        $spotifyId = $this->showId($showInput);

        if ($spotifyId === null) {
            throw new InvalidArgumentException('The Spotify show value is not a valid show URL, URI, or ID.');
        }

        $connection ??= $this->defaultConnection();
        $show = $this->spotify->fetchShow($connection, $spotifyId);
        $externalUrl = $show['external_url'] ?? "https://open.spotify.com/show/{$spotifyId}";
        $descriptionMarkdown = $this->descriptionMarkdown(
            $show['html_description'] ?? null,
            $show['description'] ?? null,
        );

        return [
            'description' => $show['description'] ?? null,
            'description_markdown' => $descriptionMarkdown,
            'external_id' => $show['external_id'] ?? $spotifyId,
            'external_url' => $externalUrl,
            'html_description' => $show['html_description'] ?? null,
            'languages' => $show['languages'] ?? [],
            'publisher' => $show['publisher'] ?? null,
            'thumbnail' => $show['thumbnail'] ?? null,
            'title' => $show['title'] ?? null,
            'total_episodes' => $show['total_episodes'] ?? null,
            'uri' => $show['uri'] ?? null,
        ];
    }

    private function defaultConnection(): ImportConnection
    {
        return ImportConnection::query()
            ->where('provider', ImportConnectionProvider::Spotify->value)
            ->orderByRaw('case when status = ? then 0 else 1 end', [ImportConnectionStatus::Connected->value])
            ->latest('last_tested_at')
            ->latest('id')
            ->firstOrFail();
    }

    private function episodeId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9]{10,}$/', $input) === 1) {
            return $input;
        }

        if (preg_match('/spotify:episode:([A-Za-z0-9]+)/', $input, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('#open\.spotify\.com/(?:intl-[a-z]{2}/)?episode/([A-Za-z0-9]+)#i', $input, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function showId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9]{10,}$/', $input) === 1) {
            return $input;
        }

        if (preg_match('/spotify:show:([A-Za-z0-9]+)/', $input, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('#open\.spotify\.com/(?:intl-[a-z]{2}/)?show/([A-Za-z0-9]+)#i', $input, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function releaseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'Asia/Jerusalem')->startOfDay();
    }

    private function descriptionMarkdown(mixed $html, mixed $plainText): string
    {
        $converter = app(SpotifyHtmlToMarkdown::class);
        $description = $converter->convert(is_string($html) ? $html : null);

        if ($description !== '') {
            return $description;
        }

        return $converter->normalizePlainText(is_string($plainText) ? $plainText : null);
    }
}
