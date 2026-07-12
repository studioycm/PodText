<?php

namespace App\Support\Importer\Contracts;

interface SpotifyClient
{
    /**
     * @return array<string, mixed>
     */
    public function fetchEpisode(string $spotifyId): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchShow(string $spotifyId): array;

    /**
     * @return array<string, mixed>
     */
    public function ping(): array;
}
