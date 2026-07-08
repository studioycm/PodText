<div class="space-y-6" data-test="content-item-search">
    @if($this->shouldRenderDiscoveryChrome())
    <div class="space-y-4" data-test="discovery-chrome">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.search') }}</span>
                    <input
                        type="search"
                        wire:model.live.debounce.350ms="search"
                        data-test="item-search"
                        placeholder="{{ __('public.filters.search_items_placeholder') }}"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                    >
                </label>

                <div class="grid gap-3 sm:grid-cols-[auto_auto_auto] sm:items-end">
                    <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                        <span>{{ __('public.filters.sort') }}</span>
                        <select
                            wire:model.live="sort"
                            data-test="item-sort"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                        >
                            @foreach($this->sortOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button
                        type="button"
                        x-data
                        x-on:click="$dispatch('open-public-filter-drawer')"
                        data-test="open-filter-drawer"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-800 transition hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100 dark:hover:bg-primary-900"
                    >
                        <span>{{ __('public.filters.open_filters') }}</span>
                        @if($this->activeFilterCount() > 0)
                            <span class="rounded-full bg-primary-700 px-2 py-0.5 text-xs text-white dark:bg-primary-300 dark:text-primary-950" data-test="active-filter-count">
                                {{ $this->activeFilterCount() }}
                            </span>
                        @endif
                    </button>

                    <button
                        type="button"
                        wire:click="clearFilters"
                        data-test="clear-filters"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        {{ __('public.actions.clear_filters') }}
                    </button>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-medium text-gray-900 dark:text-gray-100" data-test="result-count">
                    {{ trans_choice('public.results.count', $resultCount, ['count' => $resultCount]) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('public.results.public_items_only') }}
                </p>
            </div>
        </div>

        <x-public.public-filter-panel
            :category-options="$categoryOptions"
            :content-group-options="$contentGroupOptions"
            :provider-options="$providerOptions"
            :tag-options="$tagOptions"
            :transcriber-options="$transcriberOptions"
            :active-filter-count="$this->activeFilterCount()"
            :active-category-ids="$filterCategoryIds"
            :active-tag-ids="$filterTagIds"
        />
    </div>
    @endif

    @if($sections->isNotEmpty())
        <div class="space-y-8" data-test="homepage-sections">
            @foreach($sections as $section)
                @php
                    $sectionType = $section->section?->type?->value ?? ($section->sourceType === 'latest_content_items' ? 'latest' : $section->sourceType);
                    $isLatestSection = $this->isLatestSection($section);
                    $latestItems = collect();
                    $latestTotalPages = 1;
                    $latestMode = 'none';
                    $latestPage = 1;

                    if ($isLatestSection) {
                        $latestItems = $this->visibleLatestItems($section);
                        $latestTotalPages = $this->latestTotalPages($section);
                        $latestMode = $this->latestMode($section);
                        $latestPage = $this->latestPage($section->key);
                    }
                @endphp

                <section class="space-y-4" data-test="homepage-section" data-section-type="{{ $sectionType }}">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between" data-test="homepage-section-header">
                        <div class="space-y-1">
                            @if($section->targetLabel)
                                <p class="text-sm font-medium text-primary-600 dark:text-primary-400" data-test="homepage-section-target">
                                    {{ $section->targetLabel }}
                                </p>
                            @endif

                            @if($section->heading)
                                <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white" data-test="homepage-section-heading">
                                    {{ $section->heading }}
                                </h2>
                            @endif

                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end" data-test="homepage-section-actions">
                        @if($isLatestSection)
                            <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200" data-test="latest-controls">
                                <span>{{ __('public.filters.latest_search') }}</span>
                                <input
                                    type="search"
                                    wire:model.live.debounce.300ms="latestSearch.{{ $section->key }}"
                                    data-test="latest-search"
                                    placeholder="{{ __('public.filters.latest_search_placeholder') }}"
                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 sm:w-64"
                                >
                            </label>

                            @if(in_array($latestMode, ['simple', 'next_previous'], true))
                                <div class="flex items-center gap-2" data-test="latest-next-previous">
                                    <button
                                        type="button"
                                        wire:click="previousLatestPage('{{ $section->key }}')"
                                        @disabled($latestPage <= 1)
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                        data-test="latest-previous"
                                    >
                                        {{ __('public.actions.previous') }}
                                    </button>

                                    <span class="text-xs text-gray-500 dark:text-gray-400" data-test="latest-page-indicator">
                                        {{ $latestPage }} / {{ $latestTotalPages }}
                                    </span>

                                    <button
                                        type="button"
                                        wire:click="nextLatestPage('{{ $section->key }}', {{ $latestTotalPages }})"
                                        @disabled($latestPage >= $latestTotalPages)
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                        data-test="latest-next"
                                    >
                                        {{ __('public.actions.next') }}
                                    </button>
                                </div>
                            @endif
                        @endif

                        @if($section->viewMoreUrl)
                            <a
                                href="{{ $section->viewMoreUrl }}"
                                class="inline-flex items-center justify-center rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-800 transition hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100 dark:hover:bg-primary-900"
                                data-test="homepage-section-view-more"
                            >
                                {{ __('public.actions.view_more') }}
                            </a>
                        @endif
                        </div>
                    </div>

                    @if($section->sourceType === \App\Support\PublicFront\Sections\PublicDisplaySectionRegistry::CONTENT_BLOCK)
                        @php
                            $contentStyle = $section->displayConfig['content_style'] ?? 'plain';
                            $buttonLabel = $section->displayConfig['button_label'] ?? null;
                            $buttonRouteKey = $section->displayConfig['button_route_key'] ?? null;
                            $buttonFormKey = $section->displayConfig['button_form_key'] ?? null;
                            $buttonDisplayMode = $section->displayConfig['button_display_mode'] ?? 'modal';
                            $buttonUrl = is_string($buttonRouteKey)
                                ? app(\App\Support\PublicFront\Menu\PublicRouteRegistry::class)->url($buttonRouteKey)
                                : null;
                        @endphp

                        <div
                            @class([
                                'space-y-4 rounded-lg',
                                'border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900' => $contentStyle === 'callout',
                                'border border-primary-200 bg-primary-50 p-5 shadow-sm dark:border-primary-900 dark:bg-primary-950' => $contentStyle === 'accent',
                            ])
                            data-test="homepage-content-block"
                            data-content-style="{{ $contentStyle }}"
                        >
                            @if(filled($section->displayConfig['body'] ?? null))
                                <x-public.markdown-content :markdown="$section->displayConfig['body']" />
                            @endif

                            @if($buttonLabel && $buttonUrl)
                                <a
                                    href="{{ $buttonUrl }}"
                                    class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    data-test="homepage-content-block-button"
                                >
                                    {{ $buttonLabel }}
                                </a>
                            @elseif($buttonLabel && $buttonFormKey)
                                <button
                                    type="button"
                                    x-data="{}"
                                    x-on:click="window.dispatchEvent(new CustomEvent('open-public-form', { detail: { formKey: @js($buttonFormKey) } }))"
                                    class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    data-test="homepage-content-block-form-button"
                                    data-form-key="{{ $buttonFormKey }}"
                                >
                                    {{ $buttonLabel }}
                                </button>

                                <livewire:public.public-form-modal
                                    :form-key="$buttonFormKey"
                                    :display-mode="$buttonDisplayMode"
                                    :show-trigger="false"
                                    :key="'homepage-content-block-form-'.$section->key.'-'.$buttonFormKey"
                                />
                            @endif
                        </div>
                    @elseif($isLatestSection)
                        @if($latestItems->isNotEmpty())
                            <x-public.content-item-grid
                                :items="$latestItems"
                                :card-options="$cardOptions"
                                :layout="$section->layout($layout)"
                                :card-template="$this->sectionContentItemCardTemplate($section, $cardTemplate)"
                                wire:key="{{ $section->key }}-latest-grid"
                            />

                            @if($latestMode === 'load_more' && $this->latestHasMore($section))
                                <div class="flex justify-center" data-test="latest-load-more">
                                    <button
                                        type="button"
                                        wire:click="loadMoreLatest('{{ $section->key }}', {{ $latestTotalPages }})"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                    >
                                        {{ __('public.actions.load_more') }}
                                    </button>
                                </div>
                            @endif
                        @else
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="homepage-section-empty">
                                {{ __('public.empty.items') }}
                            </div>
                        @endif
                    @elseif($section->sourceType === \App\Support\PublicFront\Sections\PublicDisplaySectionRegistry::TOP_TRANSCRIBERS)
                        <livewire:public.top-transcribers-section
                            :section-key="$section->key"
                            :heading="$section->heading"
                            :view-more-url="$section->viewMoreUrl"
                            :contributor-ids="$section->contributors->pluck('id')->all()"
                            :key="'top-transcribers-'.$section->key"
                        />
                    @elseif($section->sourceType === \App\Support\PublicFront\Sections\PublicDisplaySectionRegistry::CONTRIBUTORS)
                        @if($section->contributors->isNotEmpty())
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-test="contributors-grid">
                                @foreach($section->contributors as $author)
                                    <x-public.contributor-card
                                        :author="$author"
                                        :full-page-url="\App\Filament\Public\Pages\ShowContributor::getUrl(['authorSlug' => $author->slug], panel: 'public')"
                                        :card-template="$section->cardTemplate"
                                        wire:key="top-transcriber-card-{{ $author->id }}"
                                    />
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="homepage-section-empty">
                                {{ __('public.empty.contributors') }}
                            </div>
                        @endif
                    @elseif($section->contentGroups->isNotEmpty())
                        @php($groupCards = app(\App\Support\PublicFront\Cards\PublicContentGroupCardPresenter::class)->presentMany($section->contentGroups, $section->cardTemplate))
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-test="content-groups-grid">
                            @foreach($groupCards as $card)
                                <x-public.content-group-card
                                    :card="$card"
                                    :card-template="$section->cardTemplate"
                                    wire:key="homepage-content-group-{{ $card['id'] }}"
                                />
                            @endforeach
                        </div>
                    @elseif($section->categories->isNotEmpty())
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-test="categories-grid">
                            @foreach($section->categories as $category)
                                <a
                                    href="{{ \App\Filament\Public\Pages\BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public') }}"
                                    class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700"
                                    data-test="category-card"
                                    wire:key="homepage-category-{{ $category->id }}"
                                >
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ $category->name }}
                                    </h3>

                                    @if(filled($category->description_markdown))
                                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                            {{ str($category->description_markdown)->stripTags()->squish() }}
                                        </p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @elseif($section->items->isNotEmpty())
                        <x-public.content-item-grid
                            :items="$section->items"
                            :card-options="$cardOptions"
                            :layout="$section->layout($layout)"
                            :card-template="$this->sectionContentItemCardTemplate($section, $cardTemplate)"
                            wire:key="{{ $section->key }}"
                        />
                    @else
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="homepage-section-empty">
                            {{ __('public.empty.items') }}
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @elseif($results)
        @if($results->isNotEmpty())
            <x-public.content-item-grid
                :items="$results"
                :card-options="$cardOptions"
                :layout="$layout"
                :card-template="$cardTemplate"
            />

            @if($results->hasPages())
                <div data-test="content-item-pagination">
                    {{ $results->links() }}
                </div>
            @endif
        @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-results">
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('public.empty.items') }}</p>
                <p class="mt-1">{{ __('public.empty.items_description') }}</p>
            </div>
        @endif
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-results">
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('public.empty.items') }}</p>
            <p class="mt-1">{{ __('public.empty.items_description') }}</p>
        </div>
    @endif
</div>
