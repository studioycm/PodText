<?php

namespace App\Livewire\Public;

use App\Models\ContentGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContentItemBrowser extends Component
{
    #[Locked]
    public ContentGroup $contentGroup;

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function mount(ContentGroup $contentGroup): void
    {
        $this->contentGroup = $contentGroup;
    }

    #[Computed]
    public function items(): Collection
    {
        return $this->contentGroup
            ->contentItems()
            ->published()
            ->with(['authors', 'contentGroup', 'featuredTranscription', 'latestPublishedTranscription'])
            ->when(
                $this->normalizedSort() === 'title',
                fn (Builder $query): Builder => $query->orderBy('title')->orderBy('id'),
                fn (Builder $query): Builder => $query->orderByEffectiveTranscriptionPublishedAt(),
            )
            ->get();
    }

    public function render(): View
    {
        return view('livewire.public.content-item-browser');
    }

    private function normalizedSort(): string
    {
        return in_array($this->sort, ['newest', 'title'], true) ? $this->sort : 'newest';
    }
}
