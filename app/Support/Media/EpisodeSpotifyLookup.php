<?php

namespace App\Support\Media;

use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Models\ImportConnection;
use App\Support\Importer\Spotify\SpotifyConnector;
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

        return [
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
                'html_description' => $episode['html_description'] ?? null,
            ],
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

    private function releaseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'Asia/Jerusalem')->startOfDay();
    }
}
