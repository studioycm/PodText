<?php

namespace App\Livewire\Public;

use App\Models\Category;
use App\Models\ContentGroup;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContentItemBrowser extends Component
{
    use WithPagination;

    #[Locked]
    public ContentGroup $contentGroup;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(as: 'itemCategories', except: '')]
    public string $categories = '';

    #[Url(as: 'perPage', except: '')]
    public string $perPage = '';

    /** @var array<int, int> */
    public array $categoryIds = [];

    public function mount(ContentGroup $contentGroup): void
    {
        $this->contentGroup = $contentGroup;
        $this->categoryIds = $this->normalizeIdList($this->categories);

        if ($this->sort === '') {
            $this->sort = $this->defaultSort();
        }

        if ($this->perPage === '') {
            $this->perPage = (string) $this->defaultItemsPerPage();
        }

        $this->sort = $this->normalizedSort();
        $this->perPage = (string) $this->itemsPerPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedCategories(): void
    {
        $this->categoryIds = $this->normalizeIdList($this->categories);

        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleCategoryFilter(int $categoryId): void
    {
        $this->categoryIds = $this->toggleId($this->categoryIds, $categoryId);
        $this->categories = $this->idListToString($this->categoryIds);

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categories = '';
        $this->categoryIds = [];
        $this->sort = $this->defaultSort();
        $this->perPage = (string) $this->defaultItemsPerPage();

        $this->resetPage();
    }

    public function items(): LengthAwarePaginator
    {
        return PublicContentItemQueries::base()
            ->where('content_group_id', $this->contentGroup->getKey())
            ->when(
                $this->searchEnabled() && filled($this->search),
                fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                    $like = "%{$this->search}%";

                    $query
                        ->where('title', 'like', $like)
                        ->orWhere('description_markdown', 'like', $like);
                }),
            )
            ->when(
                $this->categoryFilterEnabled() && $this->categoryIds !== [],
                fn (Builder $query): Builder => $this->applyCategoryFilters($query),
            )
            ->tap(fn (Builder $query): Builder => $this->applySort($query))
            ->paginate($this->itemsPerPage())
            ->withQueryString();
    }

    public function render(): View
    {
        return view('livewire.public.content-item-browser', [
            'cardOptions' => $this->cardOptions(),
            'cardTemplate' => $this->cardTemplate(),
            'categoryOptions' => $this->categoryOptions(),
            'gridColumns' => $this->gridColumns(),
            'gridGap' => $this->gridGap(),
            'groupPageConfig' => $this->groupPageConfig(),
            'items' => $this->items(),
            'itemsLayout' => $this->itemsLayout(),
            'pageSizeOptions' => $this->pageSizeOptions(),
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    private function normalizedSort(): string
    {
        $sort = match ($this->sort) {
            'newest' => 'latest_transcription',
            'title' => 'title_asc',
            default => $this->sort,
        };

        return in_array($sort, $this->sortOptions(), true) ? $sort : $this->defaultSort();
    }

    private function applySort(Builder $query): Builder
    {
        return match ($this->sortEnabled() ? $this->normalizedSort() : $this->defaultSort()) {
            'oldest_transcription' => $query->orderByEffectiveTranscriptionPublishedAt('asc'),
            'title_asc' => $query->orderBy('title')->orderBy('id'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
            'original_newest' => $query->orderByDesc('original_published_at')->orderByDesc('id'),
            'original_oldest' => $query->orderBy('original_published_at')->orderBy('id'),
            'duration_longest' => $query->orderByDesc('duration_seconds')->orderByDesc('id'),
            'duration_shortest' => $query->orderBy('duration_seconds')->orderBy('id'),
            default => $query->orderByEffectiveTranscriptionPublishedAt(),
        };
    }

    private function applyCategoryFilters(Builder $query): Builder
    {
        $categories = Category::query()
            ->visible()
            ->whereKey($this->categoryIds)
            ->get();

        if ($categories->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $query) use ($categories): void {
            $categories->each(function (Category $category) use ($query): void {
                $query->orWhere(fn (Builder $query): Builder => $query->inCategoryTree($category));
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function pageConfig(): array
    {
        return $this->renderContext()->podcastsPage();
    }

    /**
     * @return array<string, mixed>
     */
    private function groupPageConfig(): array
    {
        $config = $this->pageConfig();
        $groupPageConfig = $config['group_page'] ?? [];

        return is_array($groupPageConfig) ? $groupPageConfig : [];
    }

    private function cardTemplate(): PublicFrontCardTemplate
    {
        $config = $this->pageConfig();
        $groupPageConfig = $this->groupPageConfig();

        return app(PublicFrontCardTemplateResolver::class)->resolve(
            family: 'content_item',
            key: $config['item_template_key'] ?? null,
            overrides: [
                'layout' => $this->itemsLayout(),
                'density' => (string) ($groupPageConfig['item_density'] ?? 'comfortable'),
                'image_size' => (string) ($groupPageConfig['item_image_size'] ?? 'medium'),
                'title_size' => (string) ($groupPageConfig['item_title_size'] ?? 'base'),
            ],
        );
    }

    private function cardOptions(): PublicContentCardOptions
    {
        $base = $this->renderContext()->cardOptions();
        $groupPageConfig = $this->groupPageConfig();

        return new PublicContentCardOptions(
            imageSize: (string) ($groupPageConfig['item_image_size'] ?? $base->imageSize),
            imageFit: (string) ($groupPageConfig['item_image_fit'] ?? $base->imageFit),
            imageRadius: (string) ($groupPageConfig['item_image_radius'] ?? $base->imageRadius),
            density: (string) ($groupPageConfig['item_density'] ?? $base->density),
            titleSize: (string) ($groupPageConfig['item_title_size'] ?? $base->titleSize),
            showGroupBadge: false,
            showAuthors: (bool) ($groupPageConfig['show_episode_authors'] ?? $base->showAuthors),
            showCategories: (bool) ($groupPageConfig['show_categories'] ?? true),
            showTags: (bool) ($groupPageConfig['show_episode_tags'] ?? $base->showTags),
            showDuration: (bool) ($groupPageConfig['show_episode_duration'] ?? $base->showDuration),
            showEffectiveDate: (bool) ($groupPageConfig['show_episode_effective_date'] ?? $base->showEffectiveDate),
            showDescription: (bool) ($groupPageConfig['show_episode_descriptions'] ?? true),
            descriptionLines: $base->descriptionLines,
            cardsPerPage: $this->itemsPerPage(),
        );
    }

    /**
     * @return Collection<int, Category>
     */
    private function categoryOptions(): Collection
    {
        if (! $this->categoryFilterEnabled()) {
            return collect();
        }

        return Category::query()
            ->visible()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('contentItems', function (Builder $query): void {
                        $query
                            ->published()
                            ->where('content_group_id', $this->contentGroup->getKey());
                    })
                    ->orWhereHas('contentGroups', fn (Builder $query): Builder => $query->whereKey($this->contentGroup->getKey()));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function sortOptions(): array
    {
        $options = $this->groupPageConfig()['sort_options'] ?? PublicFrontConfigRegistry::podcastGroupItemSorts();

        if (! is_array($options)) {
            return PublicFrontConfigRegistry::podcastGroupItemSorts();
        }

        return collect($options)
            ->filter(fn (mixed $option): bool => is_string($option) && in_array($option, PublicFrontConfigRegistry::podcastGroupItemSorts(), true))
            ->unique()
            ->values()
            ->whenEmpty(fn (Collection $options): Collection => collect(PublicFrontConfigRegistry::podcastGroupItemSorts()))
            ->all();
    }

    /**
     * @return array<int>
     */
    private function pageSizeOptions(): array
    {
        $options = $this->groupPageConfig()['page_size_options'] ?? [6, 12, 24, 48];

        if (! is_array($options)) {
            return [$this->defaultItemsPerPage()];
        }

        return collect([...$options, $this->defaultItemsPerPage()])
            ->filter(fn (mixed $option): bool => is_numeric($option))
            ->map(fn (mixed $option): int => max(1, min(48, (int) $option)))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function defaultSort(): string
    {
        $default = $this->groupPageConfig()['default_sort'] ?? 'latest_transcription';

        return is_string($default) && in_array($default, $this->sortOptions(), true)
            ? $default
            : ($this->sortOptions()[0] ?? 'latest_transcription');
    }

    private function itemsPerPage(): int
    {
        $perPage = is_numeric($this->perPage) ? (int) $this->perPage : $this->defaultItemsPerPage();

        return in_array($perPage, $this->pageSizeOptions(), true)
            ? $perPage
            : $this->defaultItemsPerPage();
    }

    private function defaultItemsPerPage(): int
    {
        return max(1, min(48, (int) ($this->groupPageConfig()['items_per_page'] ?? 12)));
    }

    private function itemsLayout(): string
    {
        $layout = $this->groupPageConfig()['items_layout'] ?? 'cards';

        return in_array($layout, PublicFrontConfigRegistry::layouts(), true) ? $layout : 'cards';
    }

    private function gridColumns(): int
    {
        return max(1, min(4, (int) ($this->groupPageConfig()['items_grid_columns'] ?? 3)));
    }

    private function gridGap(): string
    {
        $gap = $this->groupPageConfig()['items_grid_gap'] ?? 'comfortable';

        return is_string($gap) && in_array($gap, PublicFrontConfigRegistry::podcastGroupItemGridGaps(), true)
            ? $gap
            : 'comfortable';
    }

    private function searchEnabled(): bool
    {
        return (bool) ($this->groupPageConfig()['search_enabled'] ?? true);
    }

    private function sortEnabled(): bool
    {
        return (bool) ($this->groupPageConfig()['sort_enabled'] ?? true);
    }

    private function categoryFilterEnabled(): bool
    {
        return (bool) ($this->groupPageConfig()['category_filter_enabled'] ?? true);
    }

    private function renderContext(): PublicFrontRenderContext
    {
        return app(PublicFrontRenderContext::class);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIdList(string $ids): array
    {
        if (blank($ids)) {
            return [];
        }

        return str($ids)
            ->explode(',')
            ->filter(fn (string $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function idListToString(array $ids): string
    {
        return collect($ids)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->implode(',');
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function toggleId(array $ids, int $id): array
    {
        if (in_array($id, $ids, true)) {
            return array_values(array_diff($ids, [$id]));
        }

        $ids[] = $id;

        return collect($ids)
            ->unique()
            ->values()
            ->all();
    }
}
