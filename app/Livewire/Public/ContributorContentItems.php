<?php

namespace App\Livewire\Public;

use App\Models\Author;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContributorDiscovery;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ContributorContentItems extends Component
{
    use WithPagination;

    public Author $author;

    public function render(): View
    {
        return view('livewire.public.contributor-content-items', [
            'cardOptions' => $this->cardOptions(),
            'items' => PublicContributorDiscovery::contentItemsForContributor($this->author)
                ->paginate($this->cardOptions()->cardsPerPage)
                ->withQueryString(),
        ]);
    }

    protected function cardOptions(): PublicContentCardOptions
    {
        return PublicContentCardOptions::fromSettings(app(PublicContentSettings::class));
    }
}
