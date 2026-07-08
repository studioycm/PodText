<?php

namespace App\Support\PublicFront\Groups;

use App\Models\Category;
use App\Models\ContentGroup;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublicContentGroupQueries
{
    public static function base(): Builder
    {
        return ContentGroup::query()
            ->published()
            ->whereHas('contentItems', fn (Builder $query): Builder => $query->published())
            ->with(['categories'])
            ->withCount([
                'contentItems as public_content_items_count' => fn (Builder $query): Builder => $query->published(),
            ])
            ->addSelect(app(PublicTranscriptionAggregates::class)->contentGroupAggregateSelects());
    }

    public static function applySearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('title', 'like', $like)
                ->orWhere('description_markdown', 'like', $like)
                ->orWhereHas('categories', fn (Builder $query): Builder => $query->visible()->where('name', 'like', $like))
                ->orWhereHas('contentItems', function (Builder $query) use ($like): void {
                    $query
                        ->published()
                        ->where(function (Builder $query) use ($like): void {
                            $query
                                ->where('title', 'like', $like)
                                ->orWhereHas('categories', fn (Builder $query): Builder => $query->visible()->where('name', 'like', $like));
                        });
                });
        });
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public static function applyCategoryFilters(Builder $query, array $categoryIds): Builder
    {
        $categories = Category::query()
            ->visible()
            ->whereKey($categoryIds)
            ->get();

        if ($categories->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $query) use ($categories): void {
            $categories->each(function (Category $category) use ($query): void {
                $visibleDescendantIds = self::visibleDescendantIds($category);

                if ($visibleDescendantIds === []) {
                    return;
                }

                $query->orWhere(function (Builder $query) use ($visibleDescendantIds): void {
                    $query
                        ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('categories.id', $visibleDescendantIds))
                        ->orWhereHas('contentItems', function (Builder $query) use ($visibleDescendantIds): void {
                            $query
                                ->published()
                                ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('categories.id', $visibleDescendantIds));
                        });
                });
            });
        });
    }

    /**
     * @return Collection<int, Category>
     */
    public static function categoryOptions(): Collection
    {
        return Category::query()
            ->visible()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private static function visibleDescendantIds(Category $category): array
    {
        return Category::query()
            ->visible()
            ->whereIn((new Category)->getQualifiedKeyName(), $category->descendantIds()->all())
            ->pluck('id')
            ->all();
    }
}
