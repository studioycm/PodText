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
use Illuminate\Support\Collection;
use Livewire\Component;

class TopTranscribersSection extends Component
{
    public string $sectionKey = 'top-transcribers';

    public ?string $heading = null;

    public ?string $viewMoreUrl = null;

    /** @var array<int, int> */
    public array $contributorIds = [];

    public ?int $selectedContributorId = null;

    public int $previewPerPage = 5;

    public int $previewPage = 1;

    public function mount(): void
    {
        $this->contributorIds = collect($this->contributorIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        $this->previewPerPage = $this->normalizePreviewPerPage($this->previewPerPage);
        $this->selectedContributorId = $this->normalizeSelectedContributorId($this->selectedContributorId);
    }

    public function selectContributor(int $authorId): void
    {
        $this->selectedContributorId = $this->normalizeSelectedContributorId($authorId);
        $this->previewPage = 1;
    }

    public function updatedPreviewPerPage(): void
    {
        $this->previewPerPage = $this->normalizePreviewPerPage($this->previewPerPage);
        $this->previewPage = 1;
    }

    public function previousPreviewPage(): void
    {
        $this->previewPage = max(1, $this->previewPage - 1);
    }

    public function nextPreviewPage(int $lastPage): void
    {
        $this->previewPage = min(max(1, $lastPage), $this->previewPage + 1);
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
        $selectedContributor = $this->selectedContributor($contributors);
        $previewItems = $selectedContributor
            ? PublicContributorDiscovery::paginatedPreviewItemsForContributor(
                $selectedContributor,
                $this->previewPerPage,
                $this->previewPage,
            )
            : new LengthAwarePaginator([], 0, $this->previewPerPage, 1);

        $this->previewPage = $previewItems->currentPage();

        return view('livewire.public.top-transcribers-section', [
            'cardOptions' => $this->cardOptions(),
            'cardTemplate' => app(PublicFrontCardTemplateResolver::class)->resolve('contributor'),
            'config' => $this->contributorsConfig(),
            'contentItemCardTemplate' => app(PublicFrontCardTemplateResolver::class)->resolve('content_item'),
            'contributors' => $contributors,
            'pageSizeOptions' => $this->pageSizeOptions(),
            'previewItems' => $previewItems,
            'selectedContributor' => $selectedContributor,
        ]);
    }

    /**
     * @return Collection<int, Author>
     */
    protected function contributors(): Collection
    {
        $topConfig = $this->contributorsConfig()['top_transcribers'] ?? [];

        if (! ($topConfig['enabled'] ?? true)) {
            return collect();
        }

        return PublicContributorDiscovery::topContributors(
            (int) ($topConfig['limit'] ?? 8),
            $this->contributorIds,
        );
    }

    /**
     * @param  Collection<int, Author>  $contributors
     */
    protected function selectedContributor(Collection $contributors): ?Author
    {
        $selected = $contributors->firstWhere('id', $this->selectedContributorId);

        if ($selected instanceof Author) {
            return $selected;
        }

        $selected = $contributors->first();
        $this->selectedContributorId = $selected?->getKey();

        return $selected;
    }

    protected function normalizeSelectedContributorId(?int $authorId): ?int
    {
        $contributors = $this->contributors();

        if ($contributors->isEmpty()) {
            return null;
        }

        if ($authorId !== null && $contributors->contains('id', $authorId)) {
            return $authorId;
        }

        return $contributors->first()?->getKey();
    }

    /**
     * @return array<int, string>
     */
    protected function pageSizeOptions(): array
    {
        return collect($this->contributorsConfig()['top_transcribers']['preview_page_size_options'] ?? [5, 10, 15])
            ->mapWithKeys(fn (int|string $value): array => [(int) $value => (string) $value])
            ->all();
    }

    protected function normalizePreviewPerPage(int $perPage): int
    {
        $options = array_keys($this->pageSizeOptions());

        return in_array($perPage, $options, true)
            ? $perPage
            : (int) ($this->contributorsConfig()['top_transcribers']['preview_default_page_size'] ?? 5);
    }

    protected function cardOptions(): PublicContentCardOptions
    {
        return $this->renderContext()->cardOptions();
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
