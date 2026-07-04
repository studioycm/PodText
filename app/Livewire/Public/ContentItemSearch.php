<?php

namespace App\Livewire\Public;

use App\Enums\HomepageSectionType;
use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\BrowseContributors;
use App\Filament\Public\Pages\BrowseTagContentItems;
use App\Filament\Public\Pages\SearchContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContentItem;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContentItemSearch extends Component
{
    use WithPagination;

    public string $context = 'home';

    public ?int $categoryId = null;

    public ?int $tagId = null;

    public ?int $contentGroupId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sort = '';

    #[Url(as: 'category', except: null)]
    public ?int $filterCategoryId = null;

    #[Url(as: 'tag', except: null)]
    public ?int $filterTagId = null;

    #[Url(as: 'group', except: null)]
    public ?int $filterContentGroupId = null;

    #[Url(as: 'author', except: null)]
    public ?int $filterAuthorId = null;

    #[Url(as: 'provider', except: '')]
    public string $filterProvider = '';

    #[Url(as: 'effective_from', except: '')]
    public string $filterEffectiveFrom = '';

    #[Url(as: 'effective_until', except: '')]
    public string $filterEffectiveUntil = '';

    #[Url(as: 'original_from', except: '')]
    public string $filterOriginalFrom = '';

    #[Url(as: 'original_until', except: '')]
    public string $filterOriginalUntil = '';

    #[Url(as: 'duration_min', except: null)]
    public ?int $filterDurationMin = null;

    #[Url(as: 'duration_max', except: null)]
    public ?int $filterDurationMax = null;

    #[Url(as: 'media', except: '')]
    public string $filterHasMedia = '';

    public bool $sortWasSelected = false;

    public function mount(
        string $context = 'home',
        ?int $categoryId = null,
        ?int $tagId = null,
        ?int $contentGroupId = null,
    ): void {
        $this->context = $context;
        $this->categoryId = $categoryId;
        $this->tagId = $tagId;
        $this->contentGroupId = $contentGroupId;
        $this->sortWasSelected = request()->query->has('sort');
        $this->sort = $this->normalizeSort($this->sort ?: $this->defaultSort());
        $this->filterHasMedia = $this->normalizeMediaFilter($this->filterHasMedia);
    }

    public function updated(string $property, mixed $value = null): void
    {
        if ($property === 'sort') {
            $this->sortWasSelected = true;
            $this->sort = $this->normalizeSort($this->sort);
        }

        if ($property === 'filterHasMedia') {
            $this->filterHasMedia = $this->normalizeMediaFilter($this->filterHasMedia);
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterCategoryId = null;
        $this->filterTagId = null;
        $this->filterContentGroupId = null;
        $this->filterAuthorId = null;
        $this->filterProvider = '';
        $this->filterEffectiveFrom = '';
        $this->filterEffectiveUntil = '';
        $this->filterOriginalFrom = '';
        $this->filterOriginalUntil = '';
        $this->filterDurationMin = null;
        $this->filterDurationMax = null;
        $this->filterHasMedia = '';
        $this->sortWasSelected = false;
        $this->sort = $this->defaultSort();

        $this->resetPage();
    }

    public function resultCount(): int
    {
        if ($this->shouldRenderHomepageSections()) {
            return $this->sectionResultCount($this->homepageSections());
        }

        return $this->paginatedContentItems()->total();
    }

    public function itemUrl(ContentItem $contentItem): string
    {
        return ShowContentItem::getUrl([
            'contentGroupSlug' => $contentItem->contentGroup->slug,
            'contentItemSlug' => $contentItem->slug,
        ], panel: 'public');
    }

    public function groupUrl(ContentGroup $contentGroup): string
    {
        return ShowContentGroup::getUrl([
            'contentGroupSlug' => $contentGroup->slug,
        ], panel: 'public');
    }

    /**
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return Category::query()
            ->visible()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function tagOptions(): array
    {
        return ContentTag::query()
            ->content()
            ->enabled()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ContentTag $tag): array => [$tag->getKey() => (string) $tag->name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function contentGroupOptions(): array
    {
        return ContentGroup::query()
            ->published()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function authorOptions(): array
    {
        return Author::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function providerOptions(): array
    {
        return $this->basePublicQuery()
            ->whereNotNull('embed_provider')
            ->distinct()
            ->orderBy('embed_provider')
            ->pluck('embed_provider', 'embed_provider')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return [
            'latest_transcription' => __('public.sort.latest_transcription'),
            'oldest_transcription' => __('public.sort.oldest_transcription'),
            'title_asc' => __('public.sort.title_asc'),
            'title_desc' => __('public.sort.title_desc'),
            'duration_shortest' => __('public.sort.duration_shortest'),
            'duration_longest' => __('public.sort.duration_longest'),
            'original_newest' => __('public.sort.original_newest'),
            'original_oldest' => __('public.sort.original_oldest'),
        ];
    }

    public function defaultSort(): string
    {
        return $this->normalizeSort($this->settings()->default_public_sort);
    }

    public function render(): View
    {
        $cardOptions = $this->cardOptions();
        $templateResolver = app(PublicFrontCardTemplateResolver::class);
        $layout = $this->resultLayout();
        $sections = $this->shouldRenderHomepageSections()
            ? $this->homepageSections()
            : collect();
        $results = $this->shouldRenderHomepageSections()
            ? null
            : $this->paginatedContentItems();
        $resultCount = $results instanceof LengthAwarePaginator
            ? $results->total()
            : $this->sectionResultCount($sections);

        return view('livewire.public.content-item-search', [
            'authorOptions' => $this->authorOptions(),
            'cardOptions' => $cardOptions,
            'cardTemplate' => $templateResolver->resolve('content_item'),
            'categoryOptions' => $this->categoryOptions(),
            'contributorCardTemplate' => $templateResolver->resolve('contributor'),
            'contentGroupOptions' => $this->contentGroupOptions(),
            'layout' => $layout,
            'providerOptions' => $this->providerOptions(),
            'resultCount' => $resultCount,
            'results' => $results,
            'sections' => $sections,
            'tagOptions' => $this->tagOptions(),
        ]);
    }

    protected function contentItemQuery(): Builder
    {
        $query = $this->basePublicQuery();

        $this->applyContext($query);
        $this->applyPublicSearch($query);
        $this->applyFilters($query);

        return $this->applySort($query, $this->shouldUseHomepagePinnedFirst());
    }

    protected function basePublicQuery(): Builder
    {
        return PublicContentItemQueries::base();
    }

    protected function applyContext(Builder $query): void
    {
        if ($this->context === 'category' && filled($this->categoryId)) {
            $query->inCategoryTree($this->categoryId);
        }

        if ($this->context === 'tag' && filled($this->tagId)) {
            $tag = ContentTag::query()->content()->enabled()->find($this->tagId);

            $tag
                ? $query->withEnabledContentTag($tag)
                : $query->whereRaw('0 = 1');
        }

        if ($this->context === 'content_group' && filled($this->contentGroupId)) {
            $query->where('content_group_id', $this->contentGroupId);
        }
    }

    protected function applyPublicSearch(Builder $query): void
    {
        $search = trim($this->search);

        if ($search === '') {
            return;
        }

        $like = "%{$search}%";

        $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('title', 'like', $like)
                ->orWhereHas('contentGroup', fn (Builder $query): Builder => $query->where('title', 'like', $like))
                ->orWhereHas('categories', fn (Builder $query): Builder => $query->visible()->where('name', 'like', $like))
                ->orWhereHas('contentGroup.categories', fn (Builder $query): Builder => $query->visible()->where('name', 'like', $like))
                ->orWhereHas('tags', function (Builder $query) use ($like): void {
                    $query
                        ->where('type', 'content')
                        ->where('is_enabled', true)
                        ->where(function (Builder $query) use ($like): void {
                            $query
                                ->where('name', 'like', $like)
                                ->orWhere('name->'.app()->getLocale(), 'like', $like);
                        });
                });
        });
    }

    protected function applyFilters(Builder $query): void
    {
        if (filled($this->filterCategoryId)) {
            $category = Category::query()->visible()->find($this->filterCategoryId);

            $category
                ? $query->inCategoryTree($category)
                : $query->whereRaw('0 = 1');
        }

        if (filled($this->filterTagId)) {
            $tag = ContentTag::query()->content()->enabled()->find($this->filterTagId);

            $tag
                ? $query->withEnabledContentTag($tag)
                : $query->whereRaw('0 = 1');
        }

        if (filled($this->filterContentGroupId)) {
            $query->where('content_group_id', $this->filterContentGroupId);
        }

        if (filled($this->filterAuthorId)) {
            $query->whereHas('authors', fn (Builder $query): Builder => $query->whereKey($this->filterAuthorId));
        }

        if (filled($this->filterProvider)) {
            $query->where('embed_provider', $this->filterProvider);
        }

        $this->applyDateFilters($query);
        $this->applyDurationFilter($query);
        $this->applyMediaFilter($query);
    }

    protected function applyDateFilters(Builder $query): void
    {
        $effectiveFrom = $this->normalizedDate($this->filterEffectiveFrom);
        $effectiveUntil = $this->normalizedDate($this->filterEffectiveUntil);
        $originalFrom = $this->normalizedDate($this->filterOriginalFrom);
        $originalUntil = $this->normalizedDate($this->filterOriginalUntil);

        if ($effectiveFrom) {
            $query->whereRaw('date('.PublicContentItemQueries::effectiveTranscriptionPublishedAtSql().') >= ?', [$effectiveFrom]);
        }

        if ($effectiveUntil) {
            $query->whereRaw('date('.PublicContentItemQueries::effectiveTranscriptionPublishedAtSql().') <= ?', [$effectiveUntil]);
        }

        if ($originalFrom) {
            $query->whereDate('original_published_at', '>=', $originalFrom);
        }

        if ($originalUntil) {
            $query->whereDate('original_published_at', '<=', $originalUntil);
        }
    }

    protected function applyDurationFilter(Builder $query): void
    {
        if (filled($this->filterDurationMin)) {
            $query->where('duration_seconds', '>=', max(0, (int) $this->filterDurationMin));
        }

        if (filled($this->filterDurationMax)) {
            $query->where('duration_seconds', '<=', max(0, (int) $this->filterDurationMax));
        }
    }

    protected function applyMediaFilter(Builder $query): void
    {
        if ($this->filterHasMedia === 'yes') {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNotNull('media_url')
                    ->orWhereNotNull('embed_url')
                    ->orWhereNotNull('direct_media_url');
            });
        }

        if ($this->filterHasMedia === 'no') {
            $query
                ->whereNull('media_url')
                ->whereNull('embed_url')
                ->whereNull('direct_media_url');
        }
    }

    protected function applySort(Builder $query, bool $pinnedFirst = false): Builder
    {
        if ($pinnedFirst) {
            return $this->applyPinnedFirstSort($query);
        }

        return match ($this->normalizeSort($this->sort)) {
            'oldest_transcription' => $query->orderByEffectiveTranscriptionPublishedAt('asc'),
            'title_asc' => $query->orderBy('title')->orderBy('id'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
            'duration_shortest' => $query->orderByRaw('duration_seconds is null')->orderBy('duration_seconds')->orderBy('id'),
            'duration_longest' => $query->orderByDesc('duration_seconds')->orderByDesc('id'),
            'original_newest' => $query->orderByDesc('original_published_at')->orderByDesc('id'),
            'original_oldest' => $query->orderByRaw('original_published_at is null')->orderBy('original_published_at')->orderBy('id'),
            default => $query->orderByEffectiveTranscriptionPublishedAt(),
        };
    }

    protected function applyPinnedFirstSort(Builder $query): Builder
    {
        return PublicContentItemQueries::pinnedFirst($query);
    }

    protected function shouldUseHomepagePinnedFirst(): bool
    {
        return $this->context === 'home'
            && ! $this->sortWasSelected;
    }

    protected function shouldRenderHomepageSections(): bool
    {
        return $this->context === 'home'
            && ! $this->hasActiveDiscoveryState();
    }

    protected function hasActiveDiscoveryState(): bool
    {
        return filled($this->search)
            || $this->sortWasSelected
            || filled($this->filterCategoryId)
            || filled($this->filterTagId)
            || filled($this->filterContentGroupId)
            || filled($this->filterAuthorId)
            || filled($this->filterProvider)
            || filled($this->filterEffectiveFrom)
            || filled($this->filterEffectiveUntil)
            || filled($this->filterOriginalFrom)
            || filled($this->filterOriginalUntil)
            || filled($this->filterDurationMin)
            || filled($this->filterDurationMax)
            || filled($this->filterHasMedia);
    }

    protected function paginatedContentItems(): LengthAwarePaginator
    {
        return $this->contentItemQuery()
            ->paginate($this->cardOptions()->cardsPerPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function homepageSections(): Collection
    {
        $sections = HomepageSection::query()
            ->visible()
            ->ordered()
            ->with(['category', 'tag', 'contentGroup'])
            ->get()
            ->filter(fn (HomepageSection $section): bool => $this->isRenderableHomepageSection($section));

        if ($sections->isEmpty()) {
            return $this->settings()->show_latest_section
                ? collect([$this->defaultLatestSectionData()])
                : collect();
        }

        return $sections
            ->map(fn (HomepageSection $section): array => $this->homepageSectionData($section))
            ->values();
    }

    protected function isRenderableHomepageSection(HomepageSection $section): bool
    {
        if ($section->type === HomepageSectionType::CuratedQuery) {
            return false;
        }

        if ($section->type === HomepageSectionType::Latest) {
            return $this->settings()->show_latest_section;
        }

        if ($section->type === HomepageSectionType::Category) {
            return (bool) $section->category?->is_visible;
        }

        if ($section->type === HomepageSectionType::Tag) {
            return (bool) $section->tag?->is_enabled;
        }

        if ($section->type === HomepageSectionType::ContentGroup) {
            return $section->contentGroup !== null;
        }

        if ($section->type === HomepageSectionType::TopTranscribers) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function homepageSectionData(HomepageSection $section): array
    {
        return [
            'description' => null,
            'contributors' => $this->homepageSectionContributors($section),
            'heading' => $section->name,
            'items' => $this->homepageSectionItems($section),
            'key' => "section-{$section->getKey()}",
            'targetLabel' => $this->homepageSectionTargetLabel($section),
            'type' => $section->type->value,
            'viewMoreUrl' => $this->homepageSectionViewMoreUrl($section),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultLatestSectionData(): array
    {
        return [
            'description' => null,
            'contributors' => collect(),
            'heading' => __('public.sections.latest'),
            'items' => $this->applyPinnedFirstSort($this->basePublicQuery())
                ->limit($this->homepageItemLimit())
                ->get(),
            'key' => 'section-latest-default',
            'targetLabel' => null,
            'type' => HomepageSectionType::Latest->value,
            'viewMoreUrl' => SearchContentItems::getUrl(panel: 'public'),
        ];
    }

    protected function homepageSectionItems(HomepageSection $section): Collection
    {
        if ($section->type === HomepageSectionType::TopTranscribers) {
            return collect();
        }

        $query = $this->basePublicQuery();

        if ($section->type === HomepageSectionType::Category) {
            $query->inCategoryTree($section->category);
        }

        if ($section->type === HomepageSectionType::Tag) {
            $query->withEnabledContentTag($section->tag);
        }

        if ($section->type === HomepageSectionType::ContentGroup) {
            $query->where('content_group_id', $section->contentGroup->getKey());
        }

        return $this->applyPinnedFirstSort($query)
            ->limit(max(1, $section->limit))
            ->get();
    }

    protected function homepageSectionContributors(HomepageSection $section): Collection
    {
        if ($section->type !== HomepageSectionType::TopTranscribers) {
            return collect();
        }

        return PublicContributorDiscovery::topContributors($section->limit);
    }

    protected function homepageSectionTargetLabel(HomepageSection $section): ?string
    {
        return match ($section->type) {
            HomepageSectionType::Category => $section->category?->name,
            HomepageSectionType::Tag => $section->tag?->name,
            HomepageSectionType::ContentGroup => $section->contentGroup?->title,
            HomepageSectionType::TopTranscribers => __('public.sections.top_transcribers_target'),
            default => null,
        };
    }

    protected function homepageSectionViewMoreUrl(HomepageSection $section): ?string
    {
        return match ($section->type) {
            HomepageSectionType::Latest => SearchContentItems::getUrl(panel: 'public'),
            HomepageSectionType::Category => $section->category
                ? BrowseCategoryContentItems::getUrl(['categorySlug' => $section->category->slug], panel: 'public')
                : null,
            HomepageSectionType::Tag => $section->tag
                ? BrowseTagContentItems::getUrl(['tagSlug' => $section->tag->slug], panel: 'public')
                : null,
            HomepageSectionType::ContentGroup => $section->contentGroup
                ? ShowContentGroup::getUrl(['contentGroupSlug' => $section->contentGroup->slug], panel: 'public')
                : null,
            HomepageSectionType::TopTranscribers => BrowseContributors::getUrl(panel: 'public'),
            default => null,
        };
    }

    protected function sectionResultCount(Collection $sections): int
    {
        return $sections
            ->flatMap(function (array $section): Collection {
                return $section['items']
                    ->pluck('id')
                    ->merge($section['contributors']->map(fn (Author $author): string => "author-{$author->id}"));
            })
            ->unique()
            ->count();
    }

    protected function homepageItemLimit(): int
    {
        return max(1, min(48, $this->settings()->homepage_item_limit));
    }

    protected function normalizeSort(?string $sort): string
    {
        return match ($sort) {
            'oldest', 'oldest_transcription' => 'oldest_transcription',
            'title', 'title_asc' => 'title_asc',
            'title_desc' => 'title_desc',
            'duration_shortest' => 'duration_shortest',
            'duration_longest' => 'duration_longest',
            'original_newest', 'latest' => 'original_newest',
            'original_oldest' => 'original_oldest',
            'pinned', 'latest_transcription' => 'latest_transcription',
            default => 'latest_transcription',
        };
    }

    protected function normalizeMediaFilter(string $value): string
    {
        return in_array($value, ['yes', 'no'], true) ? $value : '';
    }

    protected function normalizedDate(?string $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resultLayout(): string
    {
        return $this->settings()->default_result_layout === 'rows' ? 'rows' : 'cards';
    }

    protected function settings(): PublicContentSettings
    {
        return app(PublicContentSettings::class);
    }

    protected function cardOptions(): PublicContentCardOptions
    {
        return PublicContentCardOptions::fromSettings($this->settings());
    }
}
