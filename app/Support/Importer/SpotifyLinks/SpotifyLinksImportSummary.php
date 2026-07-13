<?php

namespace App\Support\Importer\SpotifyLinks;

class SpotifyLinksImportSummary
{
    public function __construct(
        public int $newPodcasts = 0,
        public int $newEpisodes = 0,
        public int $linkedExistingPodcasts = 0,
        public int $existingEpisodesSkipped = 0,
        public int $failedRows = 0,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'new_podcasts' => $this->newPodcasts,
            'new_episodes' => $this->newEpisodes,
            'linked_existing_podcasts' => $this->linkedExistingPodcasts,
            'existing_episodes_skipped' => $this->existingEpisodesSkipped,
            'failed_rows' => $this->failedRows,
        ];
    }
}
