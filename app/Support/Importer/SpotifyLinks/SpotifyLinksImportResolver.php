<?php

namespace App\Support\Importer\SpotifyLinks;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use Illuminate\Support\Str;

class SpotifyLinksImportResolver
{
    /**
     * @var array<string, string>
     */
    private array $groupReferenceKeys = [];

    /**
     * @var array<string, string>
     */
    private array $episodeReferenceKeys = [];

    /**
     * @param  array<int, array<string, mixed>>  $episodeRows
     * @param  array<int, array<string, mixed>>  $podcastRows
     * @return array{episode_rows: array<int, array<string, mixed>>, podcast_rows: array<int, array<string, mixed>>}
     */
    public function prepareRows(array $episodeRows, array $podcastRows): array
    {
        $preparedPodcastRows = [];
        $podcastRowsByKey = [];

        foreach ($podcastRows as $podcastRow) {
            if (($podcastRow['status'] ?? null) === 'error') {
                $preparedPodcastRows[] = $podcastRow;

                continue;
            }

            $podcastRow = $this->preparePodcastRow($podcastRow);
            $rowKey = $this->podcastRowKey($podcastRow);

            if ($rowKey !== null) {
                $podcastRowsByKey[$rowKey] = $podcastRow;

                continue;
            }

            $preparedPodcastRows[] = $podcastRow;
        }

        foreach ($episodeRows as $index => $episodeRow) {
            if (($episodeRow['status'] ?? null) === 'error') {
                $episodeRows[$index] = $episodeRow;

                continue;
            }

            $existingEpisode = $this->resolveEpisode($episodeRow);
            $group = $existingEpisode?->contentGroup ?: $this->resolveGroup($episodeRow);
            $groupReferenceKey = $group?->reference_key ?: $this->referenceKeyForGroup($episodeRow);

            $episodeRows[$index] = [
                ...$episodeRow,
                'reference_key' => $existingEpisode?->reference_key ?: $this->referenceKeyForEpisode($episodeRow),
                'content_group_reference_key' => $groupReferenceKey,
                'existing_episode_id' => $existingEpisode?->getKey(),
                'existing_group_id' => $group?->getKey(),
            ];

            $podcastRow = $this->preparePodcastRow($this->podcastRowFromEpisode($episodeRows[$index], $group));
            $podcastRowsByKey[$this->podcastRowKey($podcastRow) ?? $groupReferenceKey] = $podcastRow;
        }

        return [
            'episode_rows' => array_values($episodeRows),
            'podcast_rows' => array_merge(array_values($podcastRowsByKey), $preparedPodcastRows),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function resolveEpisode(array $row): ?ContentItem
    {
        $episodeId = $this->episodeId($row);
        $externalId = filled($row['external_id'] ?? null) ? (string) $row['external_id'] : null;

        if ($episodeId === null && $externalId === null) {
            return null;
        }

        return ContentItem::query()
            ->with('contentGroup')
            ->where(function ($query) use ($episodeId, $externalId): void {
                if ($episodeId !== null) {
                    $query->where('media_metadata->episode_id', $episodeId);
                }

                if ($externalId !== null) {
                    $method = $episodeId === null ? 'where' : 'orWhere';

                    $query->{$method}('external_id', $externalId);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function resolveGroup(array $row): ?ContentGroup
    {
        $showId = $this->showId($row);

        if (filled($showId)) {
            $contentItem = ContentItem::query()
                ->with('contentGroup')
                ->where('media_metadata->show_id', $showId)
                ->first();

            if ($contentItem?->contentGroup instanceof ContentGroup) {
                return $contentItem->contentGroup;
            }
        }

        $showName = $this->showName($row);

        if (blank($showName)) {
            return null;
        }

        return ContentGroup::query()
            ->where('title', $showName)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function referenceKeyForGroup(array $row): string
    {
        if (filled($row['content_group_reference_key'] ?? null)) {
            return (string) $row['content_group_reference_key'];
        }

        if (($row['type'] ?? null) === 'show' && filled($row['reference_key'] ?? null)) {
            return (string) $row['reference_key'];
        }

        $key = $this->showId($row)
            ?: $this->showName($row)
            ?: (string) ($row['input'] ?? '')
            ?: (string) Str::ulid();

        return $this->groupReferenceKeys[$key] ??= (string) Str::ulid();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function referenceKeyForEpisode(array $row): string
    {
        if (filled($row['reference_key'] ?? null)) {
            return (string) $row['reference_key'];
        }

        $key = $this->episodeId($row)
            ?: (string) ($row['input'] ?? '')
            ?: (string) ($row['title'] ?? '')
            ?: (string) Str::ulid();

        return $this->episodeReferenceKeys[$key] ??= (string) Str::ulid();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function preparePodcastRow(array $row): array
    {
        $group = $this->resolveGroup($row);

        return [
            ...$row,
            'reference_key' => $group?->reference_key ?: ($row['reference_key'] ?? $this->referenceKeyForGroup($row)),
            'existing_group_id' => $group?->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function podcastRowFromEpisode(array $row, ?ContentGroup $group): array
    {
        return [
            'type' => 'show',
            'input' => $row['show_url'] ?? '',
            'external_id' => $this->showId($row),
            'show_id' => $this->showId($row),
            'show_name' => $this->showName($row),
            'title' => $group?->title ?: $this->showName($row),
            'description_markdown' => $row['show_description_markdown'] ?? '',
            'media_url' => $row['show_url'] ?? '',
            'external_thumbnail_url' => $row['show_thumbnail_url'] ?? '',
            'status' => $row['status'] ?? 'fetched',
            'status_label' => $row['status_label'] ?? '',
            'source' => $row['source'] ?? '',
            'source_label' => $row['source_label'] ?? '',
            'reason' => '',
            'reference_key' => $group?->reference_key ?: $row['content_group_reference_key'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function podcastRowKey(array $row): ?string
    {
        return $this->showId($row)
            ?: $this->showName($row)
            ?: (filled($row['reference_key'] ?? null) ? (string) $row['reference_key'] : null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function episodeId(array $row): ?string
    {
        $episodeId = $row['external_id'] ?? $row['episode_id'] ?? null;

        if (filled($episodeId)) {
            return (string) $episodeId;
        }

        $metadata = $row['media_metadata'] ?? null;

        if (is_array($metadata) && filled($metadata['episode_id'] ?? null)) {
            return (string) $metadata['episode_id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function showId(array $row): ?string
    {
        $showId = $row['show_id'] ?? null;

        if (blank($showId) && ($row['type'] ?? null) === 'show') {
            $showId = $row['external_id'] ?? null;
        }

        $metadata = $row['media_metadata'] ?? null;

        if (blank($showId) && is_array($metadata) && filled($metadata['show_id'] ?? null)) {
            $showId = $metadata['show_id'];
        }

        return filled($showId) ? (string) $showId : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function showName(array $row): ?string
    {
        $showName = $row['show_name'] ?? null;

        if (blank($showName) && ($row['type'] ?? null) === 'show') {
            $showName = $row['title'] ?? null;
        }

        return filled($showName) ? (string) $showName : null;
    }
}
