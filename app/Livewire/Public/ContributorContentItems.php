<?php

namespace App\Livewire\Public;

use App\Models\Author;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContributorContentItems extends Component
{
    use WithPagination;

    public Author $author;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sort = '';

    #[Url(as: 'per_page', except: null)]
    public ?int $perPage = null;

    public function mount(): void
    {
        $this->sort = $this->normalizeSort($this->sort ?: $this->defaultSort());
        $this->perPage = $this->normalizePerPage($this->perPage);
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->sort = $this->normalizeSort($this->sort);
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);
        $this->resetPage();
    }

    public function clearItemSearch(): void
    {
        $this->search = '';
        $this->sort = $this->defaultSort();
        $this->perPage = $this->normalizePerPage(null);
        $this->resetPage();
    }

    public function render(): View
    {
        $config = $this->contributorsConfig();
        $pageConfig = $config['page'] ?? [];

        return view('livewire.public.contributor-content-items', [
            'cardOptions' => $this->cardOptions(),
            'cardTemplate' => app(PublicFrontCardTemplateResolver::class)->resolve('content_item'),
            'config' => $config,
            'items' => PublicContributorDiscovery::contentItemsForContributor($this->author, $this->search, $this->sort)
                ->paginate((int) ($this->perPage ?? $pageConfig['items_per_page'] ?? $this->cardOptions()->cardsPerPage))
                ->withQueryString(),
            'pageSizeOptions' => $this->pageSizeOptions(),
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    protected function cardOptions(): PublicContentCardOptions
    {
        return PublicContentCardOptions::fromSettings(app(PublicContentSettings::class));
    }

    /**
     * @return array<string, mixed>
     */
    protected function contributorsConfig(): array
    {
        return app(PublicFrontConfigReader::class)
            ->read()
            ->group('contributors_page');
    }

    /**
     * @return array<int, string>
     */
    protected function pageSizeOptions(): array
    {
        return collect($this->contributorsConfig()['page']['page_size_options'] ?? [6, 12, 24])
            ->mapWithKeys(fn (int|string $value): array => [(int) $value => (string) $value])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function sortOptions(): array
    {
        $labels = [
            'latest_transcription' => __('public.sort.latest_transcription'),
            'oldest_transcription' => __('public.sort.oldest_transcription'),
            'title_asc' => __('public.sort.title_asc'),
            'title_desc' => __('public.sort.title_desc'),
        ];

        return collect($this->contributorsConfig()['page']['sort_options'] ?? array_keys($labels))
            ->filter(fn (string $sort): bool => array_key_exists($sort, $labels))
            ->mapWithKeys(fn (string $sort): array => [$sort => $labels[$sort]])
            ->all();
    }

    protected function normalizePerPage(?int $perPage): int
    {
        $options = array_keys($this->pageSizeOptions());
        $default = (int) ($this->contributorsConfig()['page']['items_per_page'] ?? 12);

        return in_array($perPage, $options, true) ? (int) $perPage : $default;
    }

    protected function normalizeSort(string $sort): string
    {
        $options = array_keys($this->sortOptions());

        return in_array($sort, $options, true) ? $sort : $this->defaultSort();
    }

    protected function defaultSort(): string
    {
        return (string) ($this->contributorsConfig()['page']['default_sort'] ?? 'latest_transcription');
    }
}
