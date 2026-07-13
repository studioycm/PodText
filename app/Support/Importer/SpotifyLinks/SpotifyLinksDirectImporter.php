<?php

namespace App\Support\Importer\SpotifyLinks;

use App\Enums\PublicationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Support\Slugs\HebrewSlugger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SpotifyLinksDirectImporter
{
    /**
     * @var array<string, bool>
     */
    private array $createdPodcastReferenceKeys = [];

    public function __construct(
        private readonly SpotifyLinksImportResolver $resolver,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $episodeRows
     * @param  array<int, array<string, mixed>>  $podcastRows
     */
    public function summarize(array $episodeRows, array $podcastRows): SpotifyLinksImportSummary
    {
        ['episode_rows' => $episodeRows, 'podcast_rows' => $podcastRows] = $this->resolver->prepareRows($episodeRows, $podcastRows);

        $summary = new SpotifyLinksImportSummary;
        $newPodcastKeys = [];

        foreach ($podcastRows as $row) {
            if (($row['status'] ?? null) === 'error') {
                continue;
            }

            $referenceKey = (string) ($row['reference_key'] ?? '');

            if ($referenceKey === '' || $this->groupByReferenceKey($referenceKey) || $this->resolver->resolveGroup($row)) {
                continue;
            }

            $newPodcastKeys[$referenceKey] = true;
        }

        foreach ($episodeRows as $row) {
            if (($row['status'] ?? null) === 'error') {
                continue;
            }

            if ($this->resolver->resolveEpisode($row)) {
                $summary->existingEpisodesSkipped++;

                continue;
            }

            $summary->newEpisodes++;

            if ($this->resolver->resolveGroup($row)) {
                $summary->linkedExistingPodcasts++;
            }
        }

        $summary->newPodcasts = count($newPodcastKeys);

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $episodeRows
     * @param  array<int, array<string, mixed>>  $podcastRows
     * @return array{summary: SpotifyLinksImportSummary, episode_rows: array<int, array<string, mixed>>, podcast_rows: array<int, array<string, mixed>>}
     */
    public function import(array $episodeRows, array $podcastRows): array
    {
        ['episode_rows' => $episodeRows, 'podcast_rows' => $podcastRows] = $this->resolver->prepareRows($episodeRows, $podcastRows);

        $summary = new SpotifyLinksImportSummary;

        foreach ($podcastRows as $index => $row) {
            if (($row['status'] ?? null) === 'error') {
                continue;
            }

            try {
                $group = DB::transaction(fn (): ContentGroup => $this->createOrResolveGroup($row));

                if (($row['existing_group_id'] ?? null) || ! $group->wasRecentlyCreated) {
                    $podcastRows[$index] = $this->withOutcome($row, 'existing_podcast', __('admin.spotify_fetcher.import_outcomes.existing_podcast'), group: $group);

                    continue;
                }

                $summary->newPodcasts++;
                $this->createdPodcastReferenceKeys[(string) $group->reference_key] = true;
                $podcastRows[$index] = $this->withOutcome($row, 'imported_podcast', __('admin.spotify_fetcher.import_outcomes.imported_podcast'), group: $group);
            } catch (Throwable $throwable) {
                $summary->failedRows++;
                $podcastRows[$index] = $this->withFailure($row, $throwable);
            }
        }

        foreach ($episodeRows as $index => $row) {
            if (($row['status'] ?? null) === 'error') {
                continue;
            }

            try {
                $result = DB::transaction(fn (): array => $this->importEpisodeRow($row));

                if (($result['status'] ?? null) === 'skipped_existing_episode') {
                    $summary->existingEpisodesSkipped++;
                    $episodeRows[$index] = $this->withOutcome(
                        $row,
                        'skipped_existing_episode',
                        __('admin.spotify_fetcher.import_outcomes.skipped_existing_episode'),
                        item: $result['item'],
                    );

                    continue;
                }

                $summary->newEpisodes++;

                if ($result['linked_existing_group'] ?? false) {
                    $summary->linkedExistingPodcasts++;
                }

                $episodeRows[$index] = $this->withOutcome(
                    $row,
                    'imported_episode',
                    __('admin.spotify_fetcher.import_outcomes.imported_episode'),
                    group: $result['group'],
                    item: $result['item'],
                );
            } catch (Throwable $throwable) {
                $summary->failedRows++;
                $episodeRows[$index] = $this->withFailure($row, $throwable);
            }
        }

        return [
            'summary' => $summary,
            'episode_rows' => $episodeRows,
            'podcast_rows' => $podcastRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{status: string, group?: ContentGroup, item?: ContentItem, linked_existing_group?: bool}
     */
    private function importEpisodeRow(array $row): array
    {
        $existingEpisode = $this->resolver->resolveEpisode($row);

        if ($existingEpisode instanceof ContentItem) {
            return [
                'status' => 'skipped_existing_episode',
                'item' => $existingEpisode,
            ];
        }

        $groupReferenceKey = (string) ($row['content_group_reference_key'] ?? '');
        $existingGroup = $this->groupByReferenceKey($groupReferenceKey) ?: $this->resolver->resolveGroup($row);
        $group = $existingGroup ?: $this->createGroup($this->groupRowFromEpisode($row));

        $item = ContentItem::query()->create([
            'reference_key' => $row['reference_key'] ?? $this->resolver->referenceKeyForEpisode($row),
            'content_group_id' => $group->getKey(),
            'title' => $this->requiredText($row['title'] ?? null, 'title'),
            'title_prefix' => $row['title_prefix'] ?? null,
            'slug' => HebrewSlugger::unique(
                (string) ($row['title'] ?? $row['external_id'] ?? 'spotify-episode'),
                fn (string $slug): bool => ContentItem::query()
                    ->where('content_group_id', $group->getKey())
                    ->where('slug', $slug)
                    ->exists(),
            ),
            'description_markdown' => $row['description_markdown'] ?? null,
            'media_url' => $this->requiredText($row['media_url'] ?? null, 'media_url'),
            'embed_url' => $row['embed_url'] ?? null,
            'embed_provider' => 'spotify',
            'duration_seconds' => filled($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null,
            'media_duration_seconds' => filled($row['duration_seconds'] ?? null) ? (int) $row['duration_seconds'] : null,
            'external_id' => $row['external_id'] ?? null,
            'external_title' => $row['title'] ?? null,
            'external_description' => $row['external_description'] ?? null,
            'external_thumbnail_url' => $row['external_thumbnail_url'] ?? null,
            'external_published_at' => $this->date($row['release_date'] ?? null),
            'media_metadata' => $this->metadata($row),
            'direct_media_url' => null,
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'original_published_at' => $this->date($row['release_date'] ?? null),
        ]);

        return [
            'status' => 'imported_episode',
            'group' => $group,
            'item' => $item,
            'linked_existing_group' => $existingGroup instanceof ContentGroup
                && ! isset($this->createdPodcastReferenceKeys[(string) $existingGroup->reference_key]),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createOrResolveGroup(array $row): ContentGroup
    {
        $referenceKey = (string) ($row['reference_key'] ?? '');

        return $this->groupByReferenceKey($referenceKey)
            ?: $this->resolver->resolveGroup($row)
            ?: $this->createGroup($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createGroup(array $row): ContentGroup
    {
        return ContentGroup::query()->create([
            'reference_key' => $row['reference_key'] ?? $this->resolver->referenceKeyForGroup($row),
            'title' => $this->requiredText($row['title'] ?? $row['show_name'] ?? null, 'title'),
            'slug' => HebrewSlugger::unique(
                (string) ($row['title'] ?? $row['show_name'] ?? 'spotify-podcast'),
                fn (string $slug): bool => ContentGroup::query()->where('slug', $slug)->exists(),
            ),
            'group_type_label_singular' => 'Podcast',
            'group_type_label_plural' => 'Podcasts',
            'default_item_type_label_singular' => 'Episode',
            'default_item_type_label_plural' => 'Episodes',
            'description_markdown' => $row['description_markdown'] ?? null,
            'original_language_code' => 'he',
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);
    }

    private function groupByReferenceKey(string $referenceKey): ?ContentGroup
    {
        if ($referenceKey === '') {
            return null;
        }

        return ContentGroup::query()
            ->where('reference_key', $referenceKey)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function groupRowFromEpisode(array $row): array
    {
        return [
            'reference_key' => $row['content_group_reference_key'] ?? $this->resolver->referenceKeyForGroup($row),
            'title' => $row['show_name'] ?? '',
            'show_id' => $row['show_id'] ?? null,
            'show_name' => $row['show_name'] ?? null,
            'description_markdown' => $row['show_description_markdown'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function metadata(array $row): array
    {
        return array_filter([
            'episode_id' => $row['external_id'] ?? null,
            'provider' => 'spotify',
            'reduced' => (bool) ($row['reduced'] ?? false),
            'show' => $row['show_name'] ?? null,
            'show_id' => $row['show_id'] ?? null,
            'source' => $row['source'] ?? null,
            'source_input' => $row['input'] ?? null,
        ], fn (mixed $value): bool => filled($value) || is_bool($value));
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        $value = (string) $value;

        foreach (['d/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = CarbonImmutable::createFromFormat($format, $value, 'Asia/Jerusalem');

            if ($date !== false) {
                return $date;
            }
        }

        return CarbonImmutable::parse($value, 'Asia/Jerusalem');
    }

    private function requiredText(mixed $value, string $field): string
    {
        if (filled($value)) {
            return (string) $value;
        }

        throw new RuntimeException(__('validation.required', ['attribute' => $field]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withOutcome(
        array $row,
        string $status,
        string $label,
        ?ContentGroup $group = null,
        ?ContentItem $item = null,
    ): array {
        return [
            ...$row,
            'direct_import_status' => $status,
            'direct_import_label' => $label,
            'direct_import_group_id' => $group?->getKey(),
            'direct_import_item_id' => $item?->getKey(),
            'direct_import_error' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withFailure(array $row, Throwable $throwable): array
    {
        return [
            ...$row,
            'direct_import_status' => 'failed',
            'direct_import_label' => __('admin.spotify_fetcher.import_outcomes.failed'),
            'direct_import_group_id' => null,
            'direct_import_item_id' => null,
            'direct_import_error' => $throwable->getMessage(),
        ];
    }
}
