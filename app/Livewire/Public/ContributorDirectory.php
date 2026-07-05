<?php

namespace App\Livewire\Public;

use App\Filament\Public\Pages\ShowContributor;
use App\Models\Author;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
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

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    #[Url(as: 'sort', except: 'count_desc')]
    public string $sort = 'count_desc';

    #[Url(as: 'preview_q', except: '')]
    public string $previewSearch = '';

    public function mount(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);
        $this->sort = $this->normalizeSort($this->sort);
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
            'contentItemCardTemplate' => $templateResolver->resolve('content_item'),
            'contributors' => $contributors,
            'previewItems' => $selectedContributor
                ? PublicContributorDiscovery::previewItemsForContributor($selectedContributor, 6, $this->previewSearch)
                : collect(),
            'pageSizeOptions' => $this->pageSizeOptions(),
            'selectedContributor' => $selectedContributor,
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    protected function contributors(): LengthAwarePaginator
    {
        return PublicContributorDiscovery::contributors($this->search, $this->sort)
            ->paginate($this->perPage)
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
        return PublicContentCardOptions::fromSettings(app(PublicContentSettings::class));
    }

    /**
     * @return array<int, string>
     */
    protected function pageSizeOptions(): array
    {
        return [
            10 => '10',
            15 => '15',
            20 => '20',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sortOptions(): array
    {
        return [
            'name_asc' => __('public.sort.contributors_name_asc'),
            'name_desc' => __('public.sort.contributors_name_desc'),
            'count_desc' => __('public.sort.contributors_count_desc'),
            'count_asc' => __('public.sort.contributors_count_asc'),
        ];
    }

    protected function normalizePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 15, 20], true) ? $perPage : 10;
    }

    protected function normalizeSort(string $sort): string
    {
        return in_array($sort, ['name_asc', 'name_desc', 'count_desc', 'count_asc'], true) ? $sort : 'count_desc';
    }
}
