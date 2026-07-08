<?php

namespace App\Support\PublicContent;

use App\Enums\PublicationStatus;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class PublicTranscriptionSelector
{
    public function __construct(
        private readonly PublicTranscriptionPolicy $policy,
    ) {}

    public function policy(): PublicTranscriptionPolicy
    {
        return $this->policy;
    }

    public function effectiveTranscriptionForItem(ContentItem $item): ?Transcription
    {
        $item->loadMissing([
            'featuredTranscription.authors',
            'latestPublishedTranscription.authors',
        ]);

        return $item->effectiveTranscription();
    }

    /**
     * @return Collection<int, Transcription>
     */
    public function publicTranscriptionsForItem(ContentItem $item, ?string $mode = null): Collection
    {
        $mode ??= $this->policy->modeForPublicDisplay();

        if ($mode === PublicTranscriptionPolicy::MODE_ALL_PUBLISHED) {
            $effectiveTranscription = $this->effectiveTranscriptionForItem($item);

            return $item
                ->transcriptions()
                ->published()
                ->with('authors')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get()
                ->sortBy(fn (Transcription $transcription): int => $effectiveTranscription?->is($transcription) ? 0 : 1)
                ->values();
        }

        return collect([$this->effectiveTranscriptionForItem($item)])
            ->filter()
            ->values();
    }

    public function publicTranscriptionsCountForItem(ContentItem $item, ?string $mode = null): int
    {
        return $this->publicTranscriptionsForItem($item, $mode ?? $this->policy->modeForCounts())
            ->count();
    }

    public function withPublicTranscriptionRelations(Builder $query): Builder
    {
        $query->with([
            'featuredTranscription.authors',
            'latestPublishedTranscription.authors',
        ]);

        if ($this->policy->publicModeCountsAllPublished()) {
            $query->with([
                'transcriptions' => fn ($query) => $query
                    ->published()
                    ->with('authors')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id'),
            ]);
        }

        return $query;
    }

    public function whereContentItemHasTranscriber(Builder $query, int $authorId, ?string $mode = null): Builder
    {
        $mode ??= $this->policy->modeForPublicDisplay();

        return $query->whereExists(function (QueryBuilder $query) use ($authorId, $mode): void {
            $query
                ->selectRaw('1')
                ->from('author_transcription')
                ->join('transcriptions', 'author_transcription.transcription_id', '=', 'transcriptions.id')
                ->whereColumn('transcriptions.content_item_id', 'content_items.id')
                ->where('author_transcription.author_id', $authorId);

            $this->constrainJoinedPublicTranscriptions($query, $mode);
        });
    }

    public function whereContentItemHasTranscriberTranscriptionTitle(Builder $query, int $authorId, string $like, ?string $mode = null): Builder
    {
        $mode ??= $this->policy->modeForPublicDisplay();

        return $query->whereExists(function (QueryBuilder $query) use ($authorId, $like, $mode): void {
            $query
                ->selectRaw('1')
                ->from('author_transcription')
                ->join('transcriptions', 'author_transcription.transcription_id', '=', 'transcriptions.id')
                ->whereColumn('transcriptions.content_item_id', 'content_items.id')
                ->where('author_transcription.author_id', $authorId)
                ->where('transcriptions.title', 'like', $like);

            $this->constrainJoinedPublicTranscriptions($query, $mode);
        });
    }

    public function constrainJoinedPublicTranscriptions(
        QueryBuilder $query,
        ?string $mode = null,
        string $contentItemsTable = 'content_items',
        string $transcriptionsTable = 'transcriptions',
    ): QueryBuilder {
        $mode ??= $this->policy->modeForCounts();

        $this->constrainJoinedPublishedTranscriptions($query, $transcriptionsTable);

        if ($mode === PublicTranscriptionPolicy::MODE_FEATURED_ONLY) {
            $query->whereRaw("{$transcriptionsTable}.id = ".$this->effectivePublishedTranscriptionIdSql($contentItemsTable));
        }

        return $query;
    }

    public function constrainJoinedPublishedTranscriptions(QueryBuilder $query, string $transcriptionsTable = 'transcriptions'): QueryBuilder
    {
        return $query
            ->where("{$transcriptionsTable}.status", PublicationStatus::Published->value)
            ->whereNotNull("{$transcriptionsTable}.transcript_markdown")
            ->where("{$transcriptionsTable}.transcript_markdown", '!=', '')
            ->where(function (QueryBuilder $query) use ($transcriptionsTable): void {
                $query
                    ->whereNull("{$transcriptionsTable}.published_at")
                    ->orWhere("{$transcriptionsTable}.published_at", '<=', now());
            });
    }

    public function constrainJoinedPublishedContentItems(
        QueryBuilder $query,
        string $contentItemsTable = 'content_items',
        ?string $contentGroupsTable = 'content_groups',
    ): QueryBuilder {
        $query
            ->where("{$contentItemsTable}.status", PublicationStatus::Published->value)
            ->where(function (QueryBuilder $query) use ($contentItemsTable): void {
                $query
                    ->whereNull("{$contentItemsTable}.published_at")
                    ->orWhere("{$contentItemsTable}.published_at", '<=', now());
            });

        if ($contentGroupsTable !== null) {
            $query
                ->where("{$contentGroupsTable}.status", PublicationStatus::Published->value)
                ->where(function (QueryBuilder $query) use ($contentGroupsTable): void {
                    $query
                        ->whereNull("{$contentGroupsTable}.published_at")
                        ->orWhere("{$contentGroupsTable}.published_at", '<=', now());
                });
        }

        return $query;
    }

    public function effectivePublishedTranscriptionIdSql(string $contentItemsTable = 'content_items'): string
    {
        $featuredAlias = 'featured_public_transcriptions';
        $latestAlias = 'latest_public_transcriptions';

        return "coalesce(
            (select {$featuredAlias}.id from transcriptions as {$featuredAlias} where {$featuredAlias}.id = {$contentItemsTable}.featured_transcription_id and {$featuredAlias}.content_item_id = {$contentItemsTable}.id and ".$this->publishedWhereSql($featuredAlias).' limit 1),
            (select '.$latestAlias.".id from transcriptions as {$latestAlias} where {$latestAlias}.content_item_id = {$contentItemsTable}.id and ".$this->publishedWhereSql($latestAlias)." order by {$latestAlias}.published_at desc, {$latestAlias}.id desc limit 1)
        )";
    }

    public function publishedWhereSql(string $transcriptionsTable = 'transcriptions'): string
    {
        $published = str_replace("'", "''", PublicationStatus::Published->value);

        return "{$transcriptionsTable}.status = '{$published}'
            and {$transcriptionsTable}.transcript_markdown is not null
            and {$transcriptionsTable}.transcript_markdown != ''
            and ({$transcriptionsTable}.published_at is null or {$transcriptionsTable}.published_at <= CURRENT_TIMESTAMP)";
    }

    public function readingMinutes(?int $wordCount): int
    {
        if ($wordCount === null || $wordCount <= 0) {
            return 0;
        }

        return max(1, (int) ceil($wordCount / 200));
    }
}
