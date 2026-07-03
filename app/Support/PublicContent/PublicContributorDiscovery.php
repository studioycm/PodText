<?php

namespace App\Support\PublicContent;

use App\Models\Author;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class PublicContributorDiscovery
{
    public static function contributors(?string $search = null): Builder
    {
        $query = Author::query()
            ->select('authors.*')
            ->addSelect([
                'public_transcriptions_count' => self::publicTranscriptionsCountQuery(),
                'public_content_items_count' => self::publicContentItemsCountQuery(),
            ])
            ->whereHas(
                'transcriptions',
                fn (Builder $query): Builder => self::publicTranscriptionConstraint($query),
            );

        $search = trim((string) $search);

        if ($search !== '') {
            $like = "%{$search}%";

            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        return $query
            ->orderByDesc('public_transcriptions_count')
            ->orderByDesc('public_content_items_count')
            ->orderBy('name')
            ->orderBy('id');
    }

    public static function findContributor(int $authorId): ?Author
    {
        return self::contributors()
            ->whereKey($authorId)
            ->first();
    }

    /**
     * @throws ModelNotFoundException<Author>
     */
    public static function findContributorBySlug(string $slug): Author
    {
        return self::contributors()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Author>
     */
    public static function topContributors(int $limit): Collection
    {
        return self::contributors()
            ->limit(max(1, $limit))
            ->get();
    }

    public static function contentItemsForContributor(Author|int $author): Builder
    {
        $authorId = $author instanceof Author ? $author->getKey() : $author;

        return PublicContentItemQueries::base()
            ->whereHas('transcriptions', function (Builder $query) use ($authorId): Builder {
                return $query
                    ->published()
                    ->where('author_id', $authorId);
            })
            ->orderByEffectiveTranscriptionPublishedAt();
    }

    /**
     * @return Collection<int, ContentItem>
     */
    public static function previewItemsForContributor(Author|int $author, int $limit = 3): Collection
    {
        return self::contentItemsForContributor($author)
            ->limit(max(1, $limit))
            ->get();
    }

    private static function publicTranscriptionConstraint(Builder $query): Builder
    {
        return $query
            ->published()
            ->whereHas('contentItem', fn (Builder $query): Builder => $query->published());
    }

    private static function publicTranscriptionsCountQuery(): Builder
    {
        return Transcription::query()
            ->selectRaw('count(*)')
            ->whereColumn('transcriptions.author_id', 'authors.id')
            ->published()
            ->whereHas('contentItem', fn (Builder $query): Builder => $query->published());
    }

    private static function publicContentItemsCountQuery(): Builder
    {
        return Transcription::query()
            ->selectRaw('count(distinct content_item_id)')
            ->whereColumn('transcriptions.author_id', 'authors.id')
            ->published()
            ->whereHas('contentItem', fn (Builder $query): Builder => $query->published());
    }
}
