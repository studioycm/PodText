<?php

namespace App\Livewire\Public;

use App\Filament\Public\Pages\ShowContributor;
use App\Models\Author;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContributorDiscovery;
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

    public function updatedSearch(): void
    {
        $this->selectedContributorId = null;
        $this->resetPage();
    }

    public function selectContributor(int $authorId): void
    {
        $this->selectedContributorId = PublicContributorDiscovery::findContributor($authorId)?->getKey();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->selectedContributorId = null;
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

        return view('livewire.public.contributor-directory', [
            'cardOptions' => $this->cardOptions(),
            'contributors' => $contributors,
            'previewItems' => $selectedContributor
                ? PublicContributorDiscovery::previewItemsForContributor($selectedContributor)
                : collect(),
            'selectedContributor' => $selectedContributor,
        ]);
    }

    protected function contributors(): LengthAwarePaginator
    {
        return PublicContributorDiscovery::contributors($this->search)
            ->paginate(12)
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
}
