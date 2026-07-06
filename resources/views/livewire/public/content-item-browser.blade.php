<div>
    @php
        $searchEnabled = (bool) ($groupPageConfig['search_enabled'] ?? true);
        $sortEnabled = (bool) ($groupPageConfig['sort_enabled'] ?? true);
        $categoryFilterEnabled = (bool) ($groupPageConfig['category_filter_enabled'] ?? true) && $categoryOptions->isNotEmpty();
        $perPageEnabled = (bool) ($groupPageConfig['per_page_selector_enabled'] ?? true) && count($pageSizeOptions) > 1;
        $showControls = $searchEnabled || $sortEnabled || $categoryFilterEnabled || $perPageEnabled;
    @endphp

    @if($showControls)
        <div class="mb-4 space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900" data-test="group-item-controls">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto_auto] md:items-end">
                @if($searchEnabled)
                    <label class="grid min-w-0 gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>{{ __('public.filters.search_group_items') }}</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            data-test="group-item-search"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            placeholder="{{ __('public.filters.search_group_items_placeholder') }}"
                        >
                    </label>
                @endif

                @if($sortEnabled)
                    <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>{{ __('public.filters.sort_items') }}</span>
                        <select
                            wire:model.live="sort"
                            data-test="item-sort"
                            class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            @foreach($sortOptions as $sortOption)
                                <option value="{{ $sortOption }}">{{ __("public.sort.{$sortOption}") }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if($perPageEnabled)
                    <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>{{ __('public.filters.per_page') }}</span>
                        <select
                            wire:model.live="perPage"
                            data-test="group-item-per-page"
                            class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            @foreach($pageSizeOptions as $pageSizeOption)
                                <option value="{{ $pageSizeOption }}">{{ $pageSizeOption }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <button
                    type="button"
                    wire:click="clearFilters"
                    data-test="clear-group-item-filters"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    {{ __('public.actions.clear_filters') }}
                </button>
            </div>

            @if($categoryFilterEnabled)
                <div class="space-y-2" data-test="group-item-category-filters">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('public.filters.category') }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        @foreach($categoryOptions as $category)
                            @php($active = in_array($category->id, $categoryIds, true))

                            <button
                                type="button"
                                wire:click="toggleCategoryFilter({{ $category->id }})"
                                data-test="group-item-category-toggle"
                                aria-pressed="{{ $active ? 'true' : 'false' }}"
                                class="{{ $active
                                    ? 'border-primary-700 bg-primary-700 text-white dark:border-primary-300 dark:bg-primary-300 dark:text-primary-950'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-200'
                                }} rounded-md border px-3 py-1.5 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-primary-500"
                            >
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($items->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('public.empty.items') }}
        </div>
    @else
        <x-public.content-item-grid
            :items="$items"
            :card-options="$cardOptions"
            :layout="$itemsLayout"
            :columns="$gridColumns"
            :gap="$gridGap"
            :card-template="$cardTemplate"
        />

        @if($items->hasPages())
            <div class="mt-6" data-test="group-item-pagination">
                {{ $items->links() }}
            </div>
        @endif
    @endif
</div>
