<div class="space-y-6" data-test="content-item-search">
    <div class="space-y-4">
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

                <div class="grid gap-3 sm:grid-cols-[auto_auto] sm:items-end">
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
            :author-options="$authorOptions"
            :category-options="$categoryOptions"
            :content-group-options="$contentGroupOptions"
            :provider-options="$providerOptions"
            :tag-options="$tagOptions"
        />
    </div>

    @if($sections->isNotEmpty())
        <div class="space-y-8" data-test="homepage-sections">
            @foreach($sections as $section)
                @php
                    $sectionType = $section->section?->type?->value ?? ($section->sourceType === 'latest_content_items' ? 'latest' : $section->sourceType);
                @endphp

                <section class="space-y-4" data-test="homepage-section" data-section-type="{{ $sectionType }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
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

                        @if($section->viewMoreUrl)
                            <a
                                href="{{ $section->viewMoreUrl }}"
                                class="text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                                data-test="homepage-section-view-more"
                            >
                                {{ __('public.actions.view_more') }}
                            </a>
                        @endif
                    </div>

                    @if(in_array($section->sourceType, ['contributors', 'top_transcribers'], true))
                        @if($section->contributors->isNotEmpty())
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-test="top-transcribers-grid">
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
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-test="content-groups-grid">
                            @foreach($section->contentGroups as $group)
                                <x-public.content-group-card
                                    :group="$group"
                                    :card-template="$section->cardTemplate"
                                    wire:key="homepage-content-group-{{ $group->id }}"
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
                            :card-template="$section->cardTemplate ?? $cardTemplate"
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
