<?php

namespace App\Livewire\Public;

use App\Enums\HomepageSectionType;
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
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContentItemSearch extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public string $context = 'home';

    public ?int $categoryId = null;

    public ?int $tagId = null;

    public ?int $contentGroupId = null;

    #[Url(as: 'sort', except: '')]
    public string $sort = '';

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
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->contentItemQuery())
            ->columns([
                ViewColumn::make('public_card')
                    ->label(__('public.labels.content_item'))
                    ->view('filament.tables.columns.public-content-item-card')
                    ->viewData(fn (): array => [
                        'cardOptions' => $this->cardOptions(),
                    ]),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => $this->resultLayout() === 'rows' ? 1 : 2,
                'xl' => $this->resultLayout() === 'rows' ? 1 : 3,
            ])
            ->searchable()
            ->searchPlaceholder(__('public.filters.search_items_placeholder'))
            ->searchUsing(fn (Builder $query, string $search): Builder => $this->applyPublicSearch($query, $search))
            ->filters($this->filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
            ->defaultPaginationPageOption($this->cardOptions()->cardsPerPage)
            ->paginationPageOptions([$this->cardOptions()->cardsPerPage])
            ->recordUrl(fn (ContentItem $record): string => ShowContentItem::getUrl([
                'contentGroupSlug' => $record->contentGroup->slug,
                'contentItemSlug' => $record->slug,
            ], panel: 'public'))
            ->emptyStateHeading(__('public.empty.items'))
            ->emptyStateDescription(__('public.empty.items_description'));
    }

    public function updatedSort(): void
    {
        $this->sortWasSelected = true;
        $this->sort = $this->normalizeSort($this->sort);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->tableSearch = '';
        $this->tableFilters = null;
        $this->sortWasSelected = false;
        $this->sort = $this->defaultSort();
        $this->resetPage();
        $this->resetTable();
    }

    public function resultCount(): int
    {
        $records = $this->getTableRecords();

        if ($records instanceof LengthAwarePaginator) {
            return $records->total();
        }

        return $records->count();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function queryString(): array
    {
        return [
            'tableSearch' => ['as' => 'q', 'except' => ''],
            'tableFilters' => ['as' => 'filters', 'except' => null],
        ];
    }

    protected function contentItemQuery(): Builder
    {
        $query = $this->basePublicQuery();

        if ($this->context === 'home') {
            $itemIds = $this->homepageSectionItemIds();

            if (is_array($itemIds)) {
                $query->whereKey($itemIds);
            }
        }

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

        return $this->applySort($query);
    }

    protected function basePublicQuery(): Builder
    {
        return ContentItem::query()
            ->published()
            ->with([
                'authors',
                'categories',
                'contentGroup.categories',
                'enabledContentTags',
                'featuredTranscription',
                'latestPublishedTranscription',
            ])
            ->withEffectiveTranscriptionPublishedAt();
    }

    protected function applyPublicSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $query) use ($like): void {
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

    protected function applySort(Builder $query): Builder
    {
        if ($this->shouldUseHomepagePinnedFirst()) {
            $now = now();

            return $query
                ->orderByRaw(
                    'case when is_pinned = 1 and (pinned_at is null or pinned_at <= ?) and (pinned_until is null or pinned_until > ?) then 0 else 1 end',
                    [$now, $now],
                )
                ->orderByRaw('pin_order is null')
                ->orderBy('pin_order')
                ->orderByDesc('pinned_at')
                ->orderByEffectiveTranscriptionPublishedAt();
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

    protected function shouldUseHomepagePinnedFirst(): bool
    {
        return $this->context === 'home'
            && ! $this->sortWasSelected;
    }

    /**
     * @return array<int>|null
     */
    protected function homepageSectionItemIds(): ?array
    {
        $sections = HomepageSection::query()
            ->visible()
            ->ordered()
            ->with(['category', 'tag', 'contentGroup'])
            ->get();

        if ($sections->isEmpty()) {
            return $this->settings()->show_latest_section ? null : [];
        }

        return $sections
            ->flatMap(fn (HomepageSection $section): Collection => $this->homepageSectionIds($section))
            ->unique()
            ->values()
            ->all();
    }

    protected function homepageSectionIds(HomepageSection $section): Collection
    {
        if ($section->type === HomepageSectionType::CuratedQuery) {
            return collect();
        }

        if ($section->type === HomepageSectionType::Latest && ! $this->settings()->show_latest_section) {
            return collect();
        }

        $query = $this->basePublicQuery();

        if ($section->type === HomepageSectionType::Category) {
            if (! $section->category?->is_visible) {
                return collect();
            }

            $query->inCategoryTree($section->category);
        }

        if ($section->type === HomepageSectionType::Tag) {
            if (! $section->tag?->is_enabled) {
                return collect();
            }

            $query->withEnabledContentTag($section->tag);
        }

        if ($section->type === HomepageSectionType::ContentGroup) {
            if (! $section->contentGroup) {
                return collect();
            }

            $query->where('content_group_id', $section->contentGroup->getKey());
        }

        return $query
            ->orderByEffectiveTranscriptionPublishedAt()
            ->limit(max(1, $section->limit))
            ->pluck('id');
    }

    /**
     * @return array<int, SelectFilter|TernaryFilter|Filter>
     */
    protected function filters(): array
    {
        return [
            SelectFilter::make('category')
                ->label(__('public.filters.category'))
                ->options(fn (): array => Category::query()
                    ->visible()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    $category = Category::query()->visible()->find($data['value']);

                    return $category
                        ? $query->inCategoryTree($category)
                        : $query->whereRaw('0 = 1');
                }),
            SelectFilter::make('tag')
                ->label(__('public.filters.tag'))
                ->options(fn (): array => ContentTag::query()
                    ->content()
                    ->enabled()
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (ContentTag $tag): array => [$tag->getKey() => $tag->name])
                    ->all())
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    $tag = ContentTag::query()->content()->enabled()->find($data['value']);

                    return $tag
                        ? $query->withEnabledContentTag($tag)
                        : $query->whereRaw('0 = 1');
                }),
            SelectFilter::make('content_group_id')
                ->label(__('public.filters.group'))
                ->options(fn (): array => ContentGroup::query()
                    ->published()
                    ->orderBy('title')
                    ->pluck('title', 'id')
                    ->all())
                ->searchable()
                ->preload(),
            SelectFilter::make('author')
                ->label(__('public.filters.author'))
                ->options(fn (): array => Author::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereHas('authors', fn (Builder $query): Builder => $query->whereKey($data['value']))
                    : $query),
            SelectFilter::make('embed_provider')
                ->label(__('public.filters.provider'))
                ->options(fn (): array => ContentItem::query()
                    ->published()
                    ->whereNotNull('embed_provider')
                    ->distinct()
                    ->orderBy('embed_provider')
                    ->pluck('embed_provider', 'embed_provider')
                    ->all()),
            Filter::make('effective_date')
                ->label(__('public.filters.effective_date'))
                ->schema([
                    DatePicker::make('from')
                        ->label(__('public.filters.from_date'))
                        ->displayFormat('d/m/Y')
                        ->timezone('Asia/Jerusalem'),
                    DatePicker::make('until')
                        ->label(__('public.filters.until_date'))
                        ->displayFormat('d/m/Y')
                        ->timezone('Asia/Jerusalem'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->whereHas('transcriptions', function (Builder $query) use ($data): void {
                    $query
                        ->published()
                        ->when(filled($data['from'] ?? null), fn (Builder $query): Builder => $query->whereDate('published_at', '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->whereDate('published_at', '<=', $data['until']));
                })),
            Filter::make('original_date')
                ->label(__('public.filters.original_date'))
                ->schema([
                    DatePicker::make('from')
                        ->label(__('public.filters.from_date'))
                        ->displayFormat('d/m/Y')
                        ->timezone('Asia/Jerusalem'),
                    DatePicker::make('until')
                        ->label(__('public.filters.until_date'))
                        ->displayFormat('d/m/Y')
                        ->timezone('Asia/Jerusalem'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['from'] ?? null), fn (Builder $query): Builder => $query->whereDate('original_published_at', '>=', $data['from']))
                    ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->whereDate('original_published_at', '<=', $data['until']))),
            Filter::make('duration')
                ->label(__('public.filters.duration'))
                ->schema([
                    TextInput::make('min')
                        ->label(__('public.filters.duration_min'))
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                    TextInput::make('max')
                        ->label(__('public.filters.duration_max'))
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['min'] ?? null), fn (Builder $query): Builder => $query->where('duration_seconds', '>=', $data['min']))
                    ->when(filled($data['max'] ?? null), fn (Builder $query): Builder => $query->where('duration_seconds', '<=', $data['max']))),
            TernaryFilter::make('has_media')
                ->label(__('public.filters.has_media'))
                ->trueLabel(__('public.labels.yes'))
                ->falseLabel(__('public.labels.no'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                        $query
                            ->whereNotNull('media_url')
                            ->orWhereNotNull('embed_url')
                            ->orWhereNotNull('direct_media_url');
                    }),
                    false: fn (Builder $query): Builder => $query
                        ->whereNull('media_url')
                        ->whereNull('embed_url')
                        ->whereNull('direct_media_url'),
                ),
        ];
    }

    public function defaultSort(): string
    {
        return $this->normalizeSort($this->settings()->default_public_sort);
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

    public function render(): View
    {
        return view('livewire.public.content-item-search', [
            'cardOptions' => $this->cardOptions(),
        ]);
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
