<?php

namespace App\Livewire\Public;

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
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\Sections\PublicDisplaySectionResolver;
use App\Support\PublicFront\Sections\PublicDisplaySectionResult;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    #[Url(as: 'categories', except: '')]
    public string $filterCategories = '';

    #[Url(as: 'tags', except: '')]
    public string $filterTags = '';

    /** @var array<int, int> */
    public array $filterCategoryIds = [];

    /** @var array<int, int> */
    public array $filterTagIds = [];

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

    /** @var array<string, string> */
    public array $latestSearch = [];

    /** @var array<string, int> */
    public array $latestPage = [];

    /** @var array<string, int> */
    public array $latestVisiblePages = [];

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
        $this->filterCategoryIds = $this->normalizeIdList($this->filterCategories);
        $this->filterTagIds = $this->normalizeIdList($this->filterTags);

        if ($this->filterCategoryId === null && $this->filterCategoryIds !== []) {
            $this->filterCategoryId = $this->filterCategoryIds[0];
        }

        if ($this->filterTagId === null && $this->filterTagIds !== []) {
            $this->filterTagId = $this->filterTagIds[0];
        }

        if ($this->filterCategoryId !== null && ! in_array($this->filterCategoryId, $this->filterCategoryIds, true)) {
            $this->filterCategoryIds[] = $this->filterCategoryId;
            $this->filterCategories = $this->idListToString($this->filterCategoryIds);
        }

        if ($this->filterTagId !== null && ! in_array($this->filterTagId, $this->filterTagIds, true)) {
            $this->filterTagIds[] = $this->filterTagId;
            $this->filterTags = $this->idListToString($this->filterTagIds);
        }
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

        if ($property === 'filterCategoryId') {
            $this->filterCategoryIds = $this->filterCategoryId ? [$this->filterCategoryId] : [];
            $this->filterCategories = $this->idListToString($this->filterCategoryIds);
        }

        if ($property === 'filterTagId') {
            $this->filterTagIds = $this->filterTagId ? [$this->filterTagId] : [];
            $this->filterTags = $this->idListToString($this->filterTagIds);
        }

        if ($property === 'filterCategories') {
            $this->filterCategoryIds = $this->normalizeIdList($this->filterCategories);
            $this->filterCategoryId = $this->filterCategoryIds[0] ?? null;
        }

        if ($property === 'filterTags') {
            $this->filterTagIds = $this->normalizeIdList($this->filterTags);
            $this->filterTagId = $this->filterTagIds[0] ?? null;
        }

        if (Str::startsWith($property, 'latestSearch.')) {
            $sectionKey = Str::after($property, 'latestSearch.');
            $this->latestPage[$sectionKey] = 1;
            $this->latestVisiblePages[$sectionKey] = 1;
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterCategoryId = null;
        $this->filterTagId = null;
        $this->filterCategories = '';
        $this->filterTags = '';
        $this->filterCategoryIds = [];
        $this->filterTagIds = [];
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

    public function toggleCategoryFilter(int $categoryId): void
    {
        $this->filterCategoryIds = $this->toggleId($this->filterCategoryIds, $categoryId);
        $this->filterCategoryId = $this->filterCategoryIds[0] ?? null;
        $this->filterCategories = $this->idListToString($this->filterCategoryIds);

        $this->resetPage();
    }

    public function toggleTagFilter(int $tagId): void
    {
        $this->filterTagIds = $this->toggleId($this->filterTagIds, $tagId);
        $this->filterTagId = $this->filterTagIds[0] ?? null;
        $this->filterTags = $this->idListToString($this->filterTagIds);

        $this->resetPage();
    }

    public function previousLatestPage(string $sectionKey): void
    {
        $this->latestPage[$sectionKey] = max(1, $this->latestPage($sectionKey) - 1);
        $this->latestVisiblePages[$sectionKey] = 1;
    }

    public function nextLatestPage(string $sectionKey, int $totalPages): void
    {
        $this->latestPage[$sectionKey] = min(max(1, $totalPages), $this->latestPage($sectionKey) + 1);
        $this->latestVisiblePages[$sectionKey] = 1;
    }

    public function loadMoreLatest(string $sectionKey, int $totalPages): void
    {
        $this->latestVisiblePages[$sectionKey] = min(max(1, $totalPages), $this->latestVisiblePages($sectionKey) + 1);
    }

    public function activeFilterCount(): int
    {
        return count($this->filterCategoryIds)
            + count($this->filterTagIds)
            + (filled($this->filterContentGroupId) ? 1 : 0)
            + (filled($this->filterAuthorId) ? 1 : 0)
            + (filled($this->filterProvider) ? 1 : 0)
            + (filled($this->filterEffectiveFrom) ? 1 : 0)
            + (filled($this->filterEffectiveUntil) ? 1 : 0)
            + (filled($this->filterOriginalFrom) ? 1 : 0)
            + (filled($this->filterOriginalUntil) ? 1 : 0)
            + (filled($this->filterDurationMin) ? 1 : 0)
            + (filled($this->filterDurationMax) ? 1 : 0)
            + (filled($this->filterHasMedia) ? 1 : 0);
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
        $cardTemplate = $templateResolver->resolve('content_item', null, [
            'layout' => $layout,
            'density' => $cardOptions->density,
            'image_size' => $cardOptions->imageSize,
            'title_size' => $cardOptions->titleSize,
        ]);
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
            'cardTemplate' => $cardTemplate,
            'categoryOptions' => $this->categoryOptions(),
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
        $categoryIds = $this->filterCategoryIds;

        if ($categoryIds === [] && filled($this->filterCategoryId)) {
            $categoryIds = [$this->filterCategoryId];
        }

        if ($categoryIds !== []) {
            $categories = Category::query()
                ->visible()
                ->whereKey($categoryIds)
                ->get();

            if ($categories->isEmpty()) {
                $query->whereRaw('0 = 1');
            } else {
                $query->where(function (Builder $query) use ($categories): void {
                    $categories->each(
                        fn (Category $category): Builder => $query->orWhere(
                            fn (Builder $query): Builder => $query->inCategoryTree($category),
                        ),
                    );
                });
            }
        }

        $tagIds = $this->filterTagIds;

        if ($tagIds === [] && filled($this->filterTagId)) {
            $tagIds = [$this->filterTagId];
        }

        if ($tagIds !== []) {
            $enabledTagIds = ContentTag::query()
                ->content()
                ->enabled()
                ->whereKey($tagIds)
                ->pluck('id');

            $enabledTagIds->isNotEmpty()
                ? $query->whereHas('tags', fn (Builder $query): Builder => $query->whereIn('tags.id', $enabledTagIds))
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
            || $this->filterCategoryIds !== []
            || $this->filterTagIds !== []
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

    /** @return Collection<int, PublicDisplaySectionResult> */
    protected function homepageSections(): Collection
    {
        $sections = HomepageSection::query()
            ->visible()
            ->ordered()
            ->with(['category', 'tag', 'contentGroup'])
            ->get();

        if ($sections->isEmpty()) {
            return $this->settings()->show_latest_section
                ? collect([$this->sectionResolver()->defaultLatestSection($this->homepageItemLimit())])
                : collect();
        }

        return $sections
            ->reject(fn (HomepageSection $section): bool => $section->type?->value === 'latest' && ! $this->settings()->show_latest_section)
            ->pipe(fn (Collection $sections): Collection => $this->sectionResolver()->resolveMany($sections))
            ->values();
    }

    protected function sectionResultCount(Collection $sections): int
    {
        return $sections
            ->flatMap(function (PublicDisplaySectionResult $section): Collection {
                return $section->items
                    ->pluck('id')
                    ->merge($section->contentGroups->map(fn (ContentGroup $group): string => "group-{$group->id}"))
                    ->merge($section->categories->map(fn (Category $category): string => "category-{$category->id}"))
                    ->merge($section->contributors->map(fn (mixed $author): string => "author-{$author->id}"));
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

    protected function sectionResolver(): PublicDisplaySectionResolver
    {
        return app(PublicDisplaySectionResolver::class);
    }

    public function isLatestSection(PublicDisplaySectionResult $section): bool
    {
        return $section->sourceType === 'latest_content_items';
    }

    public function sectionContentItemCardTemplate(
        PublicDisplaySectionResult $section,
        PublicFrontCardTemplate $fallback,
    ): PublicFrontCardTemplate {
        if (! $section->cardTemplate instanceof PublicFrontCardTemplate) {
            return $fallback;
        }

        if (($section->displayConfig['template_key'] ?? null) !== null) {
            return $section->cardTemplate;
        }

        if (($section->displayConfig['template_overrides'] ?? []) !== []) {
            return $section->cardTemplate;
        }

        return $fallback;
    }

    public function latestSearchValue(string $sectionKey): string
    {
        return trim((string) ($this->latestSearch[$sectionKey] ?? ''));
    }

    public function latestPageSize(PublicDisplaySectionResult $section): int
    {
        return max(4, min(25, (int) ($section->paginationConfig['per_page'] ?? 6)));
    }

    public function latestTotalLimit(PublicDisplaySectionResult $section): int
    {
        return max(50, min(100, (int) ($section->paginationConfig['total_limit'] ?? 50)));
    }

    public function latestMode(PublicDisplaySectionResult $section): string
    {
        $mode = (string) ($section->paginationConfig['mode'] ?? 'none');

        return in_array($mode, ['none', 'simple', 'load_more', 'next_previous'], true) ? $mode : 'none';
    }

    public function latestPage(string $sectionKey): int
    {
        return max(1, (int) ($this->latestPage[$sectionKey] ?? 1));
    }

    public function latestVisiblePages(string $sectionKey): int
    {
        return max(1, (int) ($this->latestVisiblePages[$sectionKey] ?? 1));
    }

    /**
     * @return Collection<int, ContentItem>
     */
    public function latestFilteredItems(PublicDisplaySectionResult $section): Collection
    {
        $search = Str::of($this->latestSearchValue($section->key))->lower();

        if ($search->isEmpty()) {
            return $section->items
                ->take($this->latestTotalLimit($section))
                ->values();
        }

        return $section->items
            ->filter(function (ContentItem $item) use ($search): bool {
                $haystack = collect([
                    $item->title,
                    $item->contentGroup?->title,
                    $item->description_markdown,
                ])
                    ->merge($item->effectiveCategories()->pluck('name'))
                    ->merge(($item->relationLoaded('enabledContentTags') ? $item->enabledContentTags : $item->publicTags())->pluck('name'))
                    ->filter()
                    ->implode(' ');

                return Str::of($haystack)->lower()->contains((string) $search);
            })
            ->take($this->latestTotalLimit($section))
            ->values();
    }

    /**
     * @return Collection<int, ContentItem>
     */
    public function visibleLatestItems(PublicDisplaySectionResult $section): Collection
    {
        $items = $this->latestFilteredItems($section);
        $perPage = $this->latestPageSize($section);
        $mode = $this->latestMode($section);

        if ($mode === 'load_more') {
            return $items
                ->take($perPage * $this->latestVisiblePages($section->key))
                ->values();
        }

        if (in_array($mode, ['simple', 'next_previous'], true)) {
            return $items
                ->forPage($this->latestPage($section->key), $perPage)
                ->values();
        }

        return $items
            ->take($perPage)
            ->values();
    }

    public function latestTotalPages(PublicDisplaySectionResult $section): int
    {
        return max(1, (int) ceil($this->latestFilteredItems($section)->count() / $this->latestPageSize($section)));
    }

    public function latestHasMore(PublicDisplaySectionResult $section): bool
    {
        return $this->visibleLatestItems($section)->count() < $this->latestFilteredItems($section)->count();
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIdList(string $ids): array
    {
        if (blank($ids)) {
            return [];
        }

        return str($ids)
            ->explode(',')
            ->filter(fn (string $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function idListToString(array $ids): string
    {
        return collect($ids)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->implode(',');
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function toggleId(array $ids, int $id): array
    {
        if (in_array($id, $ids, true)) {
            return array_values(array_diff($ids, [$id]));
        }

        $ids[] = $id;

        return collect($ids)
            ->unique()
            ->values()
            ->all();
    }
}
