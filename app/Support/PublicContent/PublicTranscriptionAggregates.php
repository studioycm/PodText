<?php

namespace App\Support\PublicContent;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class PublicTranscriptionAggregates
{
    public function __construct(
        private readonly PublicTranscriptionPolicy $policy,
        private readonly PublicTranscriptionSelector $selector,
    ) {}

    /**
     * @return array<string, QueryBuilder>
     */
    public function contentItemAggregateSelects(): array
    {
        return [
            'public_transcriptions_count' => $this->contentItemTranscriptionsQuery()->selectRaw(
                MultiTranscriptionSurfaces::isMultiMode()
                    ? 'count(*)'
                    : 'count(distinct transcriptions.content_item_id)',
            ),
            'public_total_word_count' => $this->contentItemTranscriptionsQuery()->selectRaw('coalesce(sum(coalesce(transcriptions.word_count, 0)), 0)'),
            'public_latest_transcription_published_at' => $this->contentItemTranscriptionsQuery()->selectRaw('max(transcriptions.published_at)'),
            'public_transcriber_count' => $this->contentItemTranscriberRowsQuery()->selectRaw('count(distinct author_transcription.author_id)'),
        ];
    }

    /**
     * @return array<string, QueryBuilder>
     */
    public function contentGroupAggregateSelects(): array
    {
        return [
            'public_transcriptions_count' => $this->contentGroupTranscriptionsQuery()->selectRaw(
                MultiTranscriptionSurfaces::isMultiMode()
                    ? 'count(*)'
                    : 'count(distinct content_items.id)',
            ),
            'public_total_word_count' => $this->contentGroupTranscriptionsQuery()->selectRaw('coalesce(sum(coalesce(transcriptions.word_count, 0)), 0)'),
            'public_latest_transcription_published_at' => $this->contentGroupTranscriptionsQuery()->selectRaw('max(transcriptions.published_at)'),
            'public_transcriber_count' => $this->contentGroupTranscriberRowsQuery()->selectRaw('count(distinct author_transcription.author_id)'),
        ];
    }

    public function publicTranscriptionsCountForItem(ContentItem $item): int
    {
        return $this->selector->publicTranscriptionsCountForItem($item, $this->policy->modeForCounts());
    }

    /**
     * @return array{
     *     public_content_items_count: int,
     *     public_transcriptions_count: int,
     *     total_word_count: int,
     *     total_reading_minutes: int,
     *     transcriber_count: int,
     *     latest_transcription_published_at: mixed
     * }
     */
    public function contentGroupSummary(ContentGroup|int $group): array
    {
        $groupId = $group instanceof ContentGroup ? $group->getKey() : $group;

        $contentGroup = ContentGroup::query()
            ->whereKey($groupId)
            ->withCount([
                'contentItems as public_content_items_count' => fn (Builder $query): Builder => $query->published(),
            ])
            ->addSelect($this->contentGroupAggregateSelects())
            ->first();

        $wordCount = (int) ($contentGroup?->public_total_word_count ?? 0);

        return [
            'public_content_items_count' => (int) ($contentGroup?->public_content_items_count ?? 0),
            'public_transcriptions_count' => (int) ($contentGroup?->public_transcriptions_count ?? 0),
            'total_word_count' => $wordCount,
            'total_reading_minutes' => $this->selector->readingMinutes($wordCount),
            'transcriber_count' => (int) ($contentGroup?->public_transcriber_count ?? 0),
            'latest_transcription_published_at' => $contentGroup?->public_latest_transcription_published_at,
        ];
    }

    public function contributorTranscriptionsCountQuery(): QueryBuilder
    {
        return $this->contributorTranscriptionRowsQuery()
            ->selectRaw(
                MultiTranscriptionSurfaces::isMultiMode()
                    ? 'count(distinct transcriptions.id)'
                    : 'count(distinct content_items.id)',
            );
    }

    public function contributorContentItemsCountQuery(): QueryBuilder
    {
        return $this->contributorTranscriptionRowsQuery()
            ->selectRaw('count(distinct content_items.id)');
    }

    public function contributorExistsQuery(): QueryBuilder
    {
        return $this->contributorTranscriptionRowsQuery()
            ->selectRaw('1');
    }

    public function whereContentItemHasContributor(Builder $query, int $authorId): Builder
    {
        return $this->selector->whereContentItemHasTranscriber($query, $authorId, $this->policy->modeForCounts());
    }

    public function whereContentItemHasContributorTranscriptionTitle(Builder $query, int $authorId, string $like): Builder
    {
        return $this->selector->whereContentItemHasTranscriberTranscriptionTitle($query, $authorId, $like, $this->policy->modeForCounts());
    }

    private function contentItemTranscriptionsQuery(): QueryBuilder
    {
        $query = DB::query()
            ->from('transcriptions')
            ->whereColumn('transcriptions.content_item_id', 'content_items.id');

        $this->selector->constrainJoinedPublicTranscriptions($query, $this->policy->modeForCounts());

        return $query;
    }

    private function contentItemTranscriberRowsQuery(): QueryBuilder
    {
        $query = DB::query()
            ->from('author_transcription')
            ->join('transcriptions', 'author_transcription.transcription_id', '=', 'transcriptions.id')
            ->whereColumn('transcriptions.content_item_id', 'content_items.id');

        $this->selector->constrainJoinedPublicTranscriptions($query, $this->policy->modeForCounts());

        return $query;
    }

    private function contentGroupTranscriptionsQuery(): QueryBuilder
    {
        $query = DB::query()
            ->from('transcriptions')
            ->join('content_items', 'transcriptions.content_item_id', '=', 'content_items.id')
            ->whereColumn('content_items.content_group_id', 'content_groups.id');

        $this->selector->constrainJoinedPublishedContentItems($query, contentGroupsTable: null);
        $this->selector->constrainJoinedPublicTranscriptions($query, $this->policy->modeForCounts());

        return $query;
    }

    private function contentGroupTranscriberRowsQuery(): QueryBuilder
    {
        $query = DB::query()
            ->from('author_transcription')
            ->join('transcriptions', 'author_transcription.transcription_id', '=', 'transcriptions.id')
            ->join('content_items', 'transcriptions.content_item_id', '=', 'content_items.id')
            ->whereColumn('content_items.content_group_id', 'content_groups.id');

        $this->selector->constrainJoinedPublishedContentItems($query, contentGroupsTable: null);
        $this->selector->constrainJoinedPublicTranscriptions($query, $this->policy->modeForCounts());

        return $query;
    }

    private function contributorTranscriptionRowsQuery(): QueryBuilder
    {
        $query = DB::query()
            ->from('author_transcription')
            ->join('transcriptions', 'author_transcription.transcription_id', '=', 'transcriptions.id')
            ->join('content_items', 'transcriptions.content_item_id', '=', 'content_items.id')
            ->join('content_groups', 'content_items.content_group_id', '=', 'content_groups.id')
            ->whereColumn('author_transcription.author_id', 'authors.id');

        $this->selector->constrainJoinedPublishedContentItems($query);
        $this->selector->constrainJoinedPublicTranscriptions($query, $this->policy->modeForCounts());

        return $query;
    }
}
