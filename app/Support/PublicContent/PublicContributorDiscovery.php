<?php

namespace App\Support\PublicContent;

use App\Models\Author;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PublicContributorDiscovery
{
    public static function contributors(?string $search = null, string $sort = 'count_desc'): Builder
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

        return match ($sort) {
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'count_asc' => $query
                ->orderBy('public_transcriptions_count')
                ->orderBy('public_content_items_count')
                ->orderBy('name')
                ->orderBy('id'),
            default => $query
                ->orderByDesc('public_transcriptions_count')
                ->orderByDesc('public_content_items_count')
                ->orderBy('name')
                ->orderBy('id'),
        };
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
    public static function topContributors(int $limit, ?array $authorIds = null): Collection
    {
        $query = self::contributors();

        if (is_array($authorIds) && $authorIds !== []) {
            $query->whereKey($authorIds);
        }

        return $query
            ->limit(max(1, $limit))
            ->get();
    }

    public static function contentItemsForContributor(Author|int $author, ?string $search = null, string $sort = 'latest_transcription'): Builder
    {
        $authorId = $author instanceof Author ? $author->getKey() : $author;
        $search = trim((string) $search);

        $query = PublicContentItemQueries::base()
            ->whereHas('transcriptions', function (Builder $query) use ($authorId): Builder {
                return $query
                    ->published()
                    ->where('author_id', $authorId);
            })
            ->with([
                'transcriptions' => fn ($query) => $query
                    ->published()
                    ->where('author_id', $authorId)
                    ->orderByDesc('published_at')
                    ->orderByDesc('id'),
            ]);

        if ($search !== '') {
            $like = "%{$search}%";

            $query->where(function (Builder $query) use ($authorId, $like): void {
                $query
                    ->where('title', 'like', $like)
                    ->orWhereHas('contentGroup', fn (Builder $query): Builder => $query->where('title', 'like', $like))
                    ->orWhereHas('transcriptions', fn (Builder $query): Builder => $query
                        ->published()
                        ->where('author_id', $authorId)
                        ->where('title', 'like', $like));
            });
        }

        return match ($sort) {
            'oldest_transcription' => $query->orderByEffectiveTranscriptionPublishedAt('asc'),
            'title_asc' => $query->orderBy('title')->orderBy('id'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
            default => $query->orderByEffectiveTranscriptionPublishedAt(),
        };
    }

    /**
     * @return Collection<int, ContentItem>
     */
    public static function previewItemsForContributor(Author|int $author, int $limit = 3, ?string $search = null): Collection
    {
        return self::contentItemsForContributor($author, $search)
            ->limit(max(1, $limit))
            ->get();
    }

    public static function paginatedPreviewItemsForContributor(Author|int $author, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min(24, $perPage));
        $page = max(1, $page);
        $query = self::contentItemsForContributor($author);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $items = $query
            ->forPage($page, $perPage)
            ->get();

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
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
