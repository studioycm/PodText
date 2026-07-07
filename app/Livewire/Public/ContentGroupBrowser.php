<?php

namespace App\Livewire\Public;

use App\Models\Category;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContentGroupBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    #[Url(as: 'categories', except: '')]
    public string $categories = '';

    /** @var array<int, int> */
    public array $categoryIds = [];

    public function mount(): void
    {
        $this->categoryIds = $this->normalizeIdList($this->categories);
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
        $this->sort = 'newest';

        $this->resetPage();
    }

    #[Computed]
    public function groups(): LengthAwarePaginator
    {
        $config = $this->pageConfig();
        $query = PublicContentGroupQueries::base()
            ->when(
                (bool) ($config['search_enabled'] ?? true),
                fn (Builder $query): Builder => PublicContentGroupQueries::applySearch($query, $this->search),
            )
            ->when(
                (bool) ($config['category_filter_enabled'] ?? true) && $this->categoryIds !== [],
                fn (Builder $query): Builder => PublicContentGroupQueries::applyCategoryFilters($query, $this->categoryIds),
            )
            ->when(
                $this->normalizedSort() === 'title',
                fn (Builder $query): Builder => $query->orderBy('title')->orderBy('id'),
                fn (Builder $query): Builder => $query->orderByDesc('published_at')->orderByDesc('id'),
            );

        return $query
            ->paginate($this->cardsPerPage())
            ->withQueryString();
    }

    public function render(): View
    {
        return view('livewire.public.content-group-browser', [
            'cardTemplate' => $this->cardTemplate(),
            'categoryOptions' => $this->categoryOptions(),
            'pageConfig' => $this->pageConfig(),
        ]);
    }

    private function normalizedSort(): string
    {
        return in_array($this->sort, ['newest', 'title'], true) ? $this->sort : 'newest';
    }

    /**
     * @return array<string, mixed>
     */
    private function pageConfig(): array
    {
        return $this->renderContext()->podcastsPage();
    }

    private function cardTemplate(): PublicFrontCardTemplate
    {
        $config = $this->pageConfig();

        return app(PublicFrontCardTemplateResolver::class)->resolve(
            family: 'content_group',
            key: $config['template_key'] ?? null,
        );
    }

    /**
     * @return Collection<int, Category>
     */
    private function categoryOptions(): Collection
    {
        return PublicContentGroupQueries::categoryOptions();
    }

    private function cardsPerPage(): int
    {
        return max(1, min(48, (int) ($this->pageConfig()['cards_per_page'] ?? 12)));
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
