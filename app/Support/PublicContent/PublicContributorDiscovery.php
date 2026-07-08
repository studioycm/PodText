<?php

namespace App\Support\PublicContent;

use App\Models\Author;
use App\Models\ContentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PublicContributorDiscovery
{
    public static function contributors(?string $search = null, string $sort = 'count_desc'): Builder
    {
        $aggregates = app(PublicTranscriptionAggregates::class);

        $query = Author::query()
            ->select('authors.*')
            ->addSelect([
                'public_transcriptions_count' => $aggregates->contributorTranscriptionsCountQuery(),
                'public_content_items_count' => $aggregates->contributorContentItemsCountQuery(),
            ])
            ->whereExists($aggregates->contributorExistsQuery());

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
        $aggregates = app(PublicTranscriptionAggregates::class);

        $query = PublicContentItemQueries::base()
            ->tap(fn (Builder $query): Builder => $aggregates->whereContentItemHasContributor($query, (int) $authorId))
            ->with([
                'transcriptions' => fn ($query) => $query
                    ->published()
                    ->whereHas('authors', fn (Builder $query): Builder => $query->whereKey($authorId))
                    ->with('authors')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id'),
            ]);

        if ($search !== '') {
            $like = "%{$search}%";

            $query->where(function (Builder $query) use ($aggregates, $authorId, $like): void {
                $query
                    ->where('title', 'like', $like)
                    ->orWhereHas('contentGroup', fn (Builder $query): Builder => $query->where('title', 'like', $like))
                    ->orWhere(fn (Builder $query): Builder => $aggregates->whereContentItemHasContributorTranscriptionTitle($query, (int) $authorId, $like));
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

    /**
     * @return array<int, string>
     */
    public static function transcriberOptions(): array
    {
        return self::contributors(sort: 'name_asc')
            ->pluck('name', 'id')
            ->all();
    }
}
