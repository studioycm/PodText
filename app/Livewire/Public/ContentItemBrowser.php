<?php

namespace App\Livewire\Public;

use App\Models\ContentGroup;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function mount(ContentGroup $contentGroup): void
    {
        $this->contentGroup = $contentGroup;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function items(): LengthAwarePaginator
    {
        return PublicContentItemQueries::base()
            ->where('content_group_id', $this->contentGroup->getKey())
            ->when(
                filled($this->search),
                fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                    $like = "%{$this->search}%";

                    $query
                        ->where('title', 'like', $like)
                        ->orWhere('description_markdown', 'like', $like);
                }),
            )
            ->when(
                $this->normalizedSort() === 'title',
                fn (Builder $query): Builder => $query->orderBy('title')->orderBy('id'),
                fn (Builder $query): Builder => $query->orderByEffectiveTranscriptionPublishedAt(),
            )
            ->paginate($this->itemsPerPage())
            ->withQueryString();
    }

    public function render(): View
    {
        return view('livewire.public.content-item-browser', [
            'cardOptions' => $this->cardOptions(),
            'cardTemplate' => $this->cardTemplate(),
            'groupPageConfig' => $this->groupPageConfig(),
            'items' => $this->items(),
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
        return app(PublicFrontConfigReader::class)
            ->read()
            ->group('podcasts_page');
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

        return app(PublicFrontCardTemplateResolver::class)->resolve(
            family: 'content_item',
            key: $config['item_template_key'] ?? null,
        );
    }

    private function cardOptions(): PublicContentCardOptions
    {
        $base = PublicContentCardOptions::fromSettings();
        $groupPageConfig = $this->groupPageConfig();

        return new PublicContentCardOptions(
            imageSize: $base->imageSize,
            density: $base->density,
            titleSize: $base->titleSize,
            showGroupBadge: false,
            showAuthors: $base->showAuthors,
            showCategories: (bool) ($groupPageConfig['show_categories'] ?? true),
            showTags: $base->showTags,
            showDuration: $base->showDuration,
            showEffectiveDate: $base->showEffectiveDate,
            showDescription: (bool) ($groupPageConfig['show_episode_descriptions'] ?? true),
            descriptionLines: $base->descriptionLines,
            cardsPerPage: $this->itemsPerPage(),
        );
    }

    private function itemsPerPage(): int
    {
        return max(1, min(48, (int) ($this->groupPageConfig()['items_per_page'] ?? 12)));
    }
}
