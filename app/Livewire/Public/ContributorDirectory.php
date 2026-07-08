<?php

namespace App\Livewire\Public;

use App\Filament\Public\Pages\ShowContributor;
use App\Models\Author;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContributorDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'contributor', except: null)]
    public ?int $selectedContributorId = null;

    #[Url(as: 'per_page', except: null)]
    public ?int $perPage = null;

    #[Url(as: 'sort', except: '')]
    public string $sort = '';

    #[Url(as: 'preview_q', except: '')]
    public string $previewSearch = '';

    public function mount(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);
        $this->sort = $this->normalizeSort($this->sort ?: $this->defaultSort());
    }

    public function updatedSearch(): void
    {
        $this->selectedContributorId = null;
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->sort = $this->normalizeSort($this->sort);
        $this->resetPage();
    }

    public function updatedPreviewSearch(): void
    {
        $this->previewSearch = trim($this->previewSearch);
    }

    public function selectContributor(int $authorId): void
    {
        $this->selectedContributorId = PublicContributorDiscovery::findContributor($authorId)?->getKey();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->selectedContributorId = null;
        $this->previewSearch = '';
        $this->resetPage();
    }

    public function contributorUrl(Author $author): string
    {
        return ShowContributor::getUrl([
            'authorSlug' => $author->slug,
        ], panel: 'public');
    }

    public function render(): View
    {
        $contributors = $this->contributors();
        $selectedContributor = $this->selectedContributor();
        $templateResolver = app(PublicFrontCardTemplateResolver::class);

        return view('livewire.public.contributor-directory', [
            'cardOptions' => $this->cardOptions(),
            'cardTemplate' => $templateResolver->resolve('contributor'),
            'config' => $this->contributorsConfig(),
            'contentItemCardTemplate' => $templateResolver->resolve('content_item'),
            'contributors' => $contributors,
            'previewItems' => $selectedContributor
                ? PublicContributorDiscovery::previewItemsForContributor(
                    $selectedContributor,
                    (int) ($this->contributorsConfig()['directory']['preview_items_per_page'] ?? 6),
                    $this->previewSearch,
                )
                : collect(),
            'pageSizeOptions' => $this->pageSizeOptions(),
            'selectedContributor' => $selectedContributor,
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    protected function contributors(): LengthAwarePaginator
    {
        return PublicContributorDiscovery::contributors($this->search, $this->sort)
            ->paginate((int) $this->perPage)
            ->withQueryString();
    }

    protected function selectedContributor(): ?Author
    {
        if (! $this->selectedContributorId) {
            return null;
        }

        return PublicContributorDiscovery::findContributor($this->selectedContributorId);
    }

    protected function cardOptions(): PublicContentCardOptions
    {
        $directoryConfig = $this->contributorsConfig()['directory'] ?? [];

        return $this->renderContext()
            ->cardOptions()
            ->withTranscriptionDisplay((string) ($directoryConfig['transcription_display'] ?? 'effective_plus_count'));
    }

    /**
     * @return array<int, string>
     */
    protected function pageSizeOptions(): array
    {
        return collect($this->contributorsConfig()['directory']['per_page_options'] ?? [10, 15, 20])
            ->mapWithKeys(fn (int|string $value): array => [(int) $value => (string) $value])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function sortOptions(): array
    {
        $labels = [
            'name_asc' => __('public.sort.contributors_name_asc'),
            'name_desc' => __('public.sort.contributors_name_desc'),
            'count_desc' => __('public.sort.contributors_count_desc'),
            'count_asc' => __('public.sort.contributors_count_asc'),
        ];

        return collect($this->contributorsConfig()['directory']['sort_options'] ?? array_keys($labels))
            ->filter(fn (string $sort): bool => array_key_exists($sort, $labels))
            ->mapWithKeys(fn (string $sort): array => [$sort => $labels[$sort]])
            ->all();
    }

    protected function normalizePerPage(?int $perPage): int
    {
        $options = array_keys($this->pageSizeOptions());

        return in_array($perPage, $options, true)
            ? $perPage
            : (int) ($this->contributorsConfig()['directory']['default_per_page'] ?? 10);
    }

    protected function normalizeSort(string $sort): string
    {
        $options = array_keys($this->sortOptions());

        return in_array($sort, $options, true)
            ? $sort
            : $this->defaultSort();
    }

    protected function defaultSort(): string
    {
        return (string) ($this->contributorsConfig()['directory']['default_sort'] ?? 'count_desc');
    }

    /**
     * @return array<string, mixed>
     */
    protected function contributorsConfig(): array
    {
        return $this->renderContext()->contributorsPage();
    }

    protected function renderContext(): PublicFrontRenderContext
    {
        return app(PublicFrontRenderContext::class);
    }
}
