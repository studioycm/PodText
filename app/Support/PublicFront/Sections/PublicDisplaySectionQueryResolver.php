<?php

namespace App\Support\PublicFront\Sections;

use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublicDisplaySectionQueryResolver
{
    /**
     * @return array{
     *     items: Collection<int, ContentItem>,
     *     contentGroups: Collection<int, ContentGroup>,
     *     categories: Collection<int, Category>,
     *     contributors: Collection<int, mixed>
     * }
     */
    public function resolve(PublicDisplaySectionConfigResult $config): array
    {
        $sourceType = $config->sourceType();

        return match ($sourceType) {
            PublicDisplaySectionRegistry::LATEST_CONTENT_ITEMS,
            PublicDisplaySectionRegistry::CATEGORY_CONTENT_ITEMS,
            PublicDisplaySectionRegistry::TAG_CONTENT_ITEMS,
            PublicDisplaySectionRegistry::CONTENT_GROUP_ITEMS,
            PublicDisplaySectionRegistry::MANUAL_CONTENT_ITEMS => [
                'items' => $this->contentItems($config),
                'contentGroups' => collect(),
                'categories' => collect(),
                'contributors' => collect(),
            ],
            PublicDisplaySectionRegistry::CONTENT_GROUPS => [
                'items' => collect(),
                'contentGroups' => $this->contentGroups($config),
                'categories' => collect(),
                'contributors' => collect(),
            ],
            PublicDisplaySectionRegistry::CATEGORIES => [
                'items' => collect(),
                'contentGroups' => collect(),
                'categories' => $this->categories($config),
                'contributors' => collect(),
            ],
            PublicDisplaySectionRegistry::CONTRIBUTORS,
            PublicDisplaySectionRegistry::TOP_TRANSCRIBERS => [
                'items' => collect(),
                'contentGroups' => collect(),
                'categories' => collect(),
                'contributors' => $this->contributors($config),
            ],
            default => [
                'items' => collect(),
                'contentGroups' => collect(),
                'categories' => collect(),
                'contributors' => collect(),
            ],
        };
    }

    /**
     * @return Collection<int, ContentItem>
     */
    private function contentItems(PublicDisplaySectionConfigResult $config): Collection
    {
        $query = PublicContentItemQueries::base();
        $sourceType = $config->sourceType();
        $includeIds = $config->selectionConfig['include_ids'];
        $excludeIds = $config->selectionConfig['exclude_ids'];

        if ($sourceType === PublicDisplaySectionRegistry::CATEGORY_CONTENT_ITEMS) {
            $category = Category::query()
                ->visible()
                ->find($config->sourceConfig['category_id'] ?? null);

            if (! $category) {
                return collect();
            }

            $this->applyCategoryConstraint($query, $category, (bool) ($config->sourceConfig['include_descendants'] ?? true));
        }

        if ($sourceType === PublicDisplaySectionRegistry::TAG_CONTENT_ITEMS) {
            $tag = ContentTag::query()
                ->content()
                ->enabled()
                ->find($config->sourceConfig['tag_id'] ?? null);

            if (! $tag) {
                return collect();
            }

            $query->withEnabledContentTag($tag);
        }

        if ($sourceType === PublicDisplaySectionRegistry::CONTENT_GROUP_ITEMS) {
            $group = ContentGroup::query()
                ->published()
                ->find($config->sourceConfig['content_group_id'] ?? null);

            if (! $group) {
                return collect();
            }

            $query->where('content_group_id', $group->getKey());
        }

        if ($sourceType === PublicDisplaySectionRegistry::MANUAL_CONTENT_ITEMS) {
            if ($includeIds === []) {
                return collect();
            }

            $query->whereIn((new ContentItem)->getQualifiedKeyName(), $includeIds);
        } elseif ($includeIds !== []) {
            $query->whereIn((new ContentItem)->getQualifiedKeyName(), $includeIds);
        }

        if ($excludeIds !== []) {
            $query->whereNotIn((new ContentItem)->getQualifiedKeyName(), $excludeIds);
        }

        if ($sourceType === PublicDisplaySectionRegistry::MANUAL_CONTENT_ITEMS && $includeIds !== []) {
            return $this->sortManualContentItems($query->get(), $includeIds)
                ->take($this->resultLimit($config))
                ->values();
        }

        return $this->sortContentItems($query, $config)
            ->limit($this->resultLimit($config))
            ->get();
    }

    /**
     * @return Collection<int, ContentGroup>
     */
    private function contentGroups(PublicDisplaySectionConfigResult $config): Collection
    {
        $query = PublicContentGroupQueries::base();

        $this->applyIdSelection($query, $config);
        $this->sortContentGroups($query, $config);

        return $query
            ->limit($this->resultLimit($config))
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(PublicDisplaySectionConfigResult $config): Collection
    {
        $query = Category::query()
            ->visible()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('contentItems', fn (Builder $query): Builder => $query->published())
                    ->orWhereHas('contentGroups.contentItems', fn (Builder $query): Builder => $query->published());
            });

        $this->applyIdSelection($query, $config);

        if (($config->sourceConfig['sort'] ?? 'name_asc') === 'newest') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderBy('name')->orderBy('id');
        }

        return $query
            ->limit($this->resultLimit($config))
            ->get();
    }

    /**
     * @return Collection<int, mixed>
     */
    private function contributors(PublicDisplaySectionConfigResult $config): Collection
    {
        $query = PublicContributorDiscovery::contributors();

        $this->applyIdSelection($query, $config);

        if (($config->sourceConfig['sort'] ?? 'top_transcriptions') === 'name_asc') {
            $query->reorder('name')->orderBy('id');
        }

        return $query
            ->limit($this->resultLimit($config))
            ->get();
    }

    private function applyCategoryConstraint(Builder $query, Category $category, bool $includeDescendants): void
    {
        if ($includeDescendants) {
            $query->inCategoryTree($category);

            return;
        }

        $query->where(function (Builder $query) use ($category): void {
            $query
                ->whereHas('categories', fn (Builder $query): Builder => $query->whereKey($category->getKey()))
                ->orWhereHas('contentGroup.categories', fn (Builder $query): Builder => $query->whereKey($category->getKey()));
        });
    }

    private function sortContentItems(Builder $query, PublicDisplaySectionConfigResult $config): Builder
    {
        return match ($config->sourceConfig['sort'] ?? 'latest_transcription') {
            'oldest_transcription' => $query->orderByEffectiveTranscriptionPublishedAt('asc'),
            'title_asc' => $query->orderBy('title')->orderBy('id'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
            'original_newest' => $query->orderByDesc('original_published_at')->orderByDesc('id'),
            'original_oldest' => $query->orderByRaw('original_published_at is null')->orderBy('original_published_at')->orderBy('id'),
            default => PublicContentItemQueries::pinnedFirst($query),
        };
    }

    private function sortContentGroups(Builder $query, PublicDisplaySectionConfigResult $config): void
    {
        match ($config->sourceConfig['sort'] ?? 'homepage_order') {
            'title_asc', 'name_asc' => $query->orderBy('title')->orderBy('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->orderedForHomepage(),
        };
    }

    /**
     * @param  Collection<int, ContentItem>  $items
     * @param  array<int, int>  $includeIds
     * @return Collection<int, ContentItem>
     */
    private function sortManualContentItems(Collection $items, array $includeIds): Collection
    {
        $positions = array_flip($includeIds);

        return $items
            ->sortBy(fn (ContentItem $item): int => $positions[$item->getKey()] ?? PHP_INT_MAX)
            ->values();
    }

    private function applyIdSelection(Builder $query, PublicDisplaySectionConfigResult $config): void
    {
        $qualifiedKey = $query->getModel()->getQualifiedKeyName();
        $includeIds = $config->selectionConfig['include_ids'];
        $excludeIds = $config->selectionConfig['exclude_ids'];

        if ($includeIds !== []) {
            $query->whereIn($qualifiedKey, $includeIds);
        }

        if ($excludeIds !== []) {
            $query->whereNotIn($qualifiedKey, $excludeIds);
        }
    }

    private function resultLimit(PublicDisplaySectionConfigResult $config): int
    {
        $perPage = (int) ($config->paginationConfig['per_page'] ?? 6);
        $totalLimit = (int) ($config->paginationConfig['total_limit'] ?? $perPage);

        if ($config->sourceType() === PublicDisplaySectionRegistry::LATEST_CONTENT_ITEMS) {
            return max(50, min(100, $totalLimit));
        }

        return max(1, min($perPage, $totalLimit));
    }
}
