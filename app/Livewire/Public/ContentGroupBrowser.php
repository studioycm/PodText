<?php

namespace App\Livewire\Public;

use App\Models\ContentGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function groups(): LengthAwarePaginator
    {
        return ContentGroup::query()
            ->published()
            ->withCount([
                'contentItems as published_content_items_count' => fn (Builder $query): Builder => $query->published(),
            ])
            ->when(
                filled($this->search),
                fn (Builder $query): Builder => $query->where('title', 'like', '%'.$this->search.'%'),
            )
            ->when(
                $this->normalizedSort() === 'title',
                fn (Builder $query): Builder => $query->orderBy('title')->orderBy('id'),
                fn (Builder $query): Builder => $query->orderByDesc('published_at')->orderByDesc('id'),
            )
            ->paginate(6);
    }

    public function render(): View
    {
        return view('livewire.public.content-group-browser');
    }

    private function normalizedSort(): string
    {
        return in_array($this->sort, ['newest', 'title'], true) ? $this->sort : 'newest';
    }
}
