<div>
    <div class="mb-6 space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
            @if($pageConfig['search_enabled'] ?? true)
                <label class="grid min-w-0 gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.search_groups') }}</span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        data-test="group-search"
                        class="w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        placeholder="{{ __('public.filters.search_podcasts_placeholder') }}"
                    >
                </label>
            @endif

            <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                <span>{{ __('public.filters.sort') }}</span>
                <select
                    wire:model.live="sort"
                    data-test="group-sort"
                    class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                >
                    <option value="newest">{{ __('public.sort.newest') }}</option>
                    <option value="title">{{ __('public.sort.title') }}</option>
                </select>
            </label>

            <button
                type="button"
                wire:click="clearFilters"
                data-test="clear-group-filters"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                {{ __('public.actions.clear_filters') }}
            </button>
        </div>

        @if(($pageConfig['category_filter_enabled'] ?? true) && $categoryOptions->isNotEmpty())
            <div class="space-y-2" data-test="podcast-category-filters">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ __('public.filters.category') }}
                </p>

                <div class="flex flex-wrap gap-2">
                    @foreach($categoryOptions as $category)
                        @php($active = in_array($category->id, $categoryIds, true))

                        <button
                            type="button"
                            wire:click="toggleCategoryFilter({{ $category->id }})"
                            data-test="podcast-category-toggle"
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

    @php($groups = $this->groups)

    <div class="mb-4 flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300 sm:flex-row sm:items-center sm:justify-between">
        <p class="font-medium text-gray-900 dark:text-gray-100" data-test="podcast-result-count">
            {{ trans_choice('public.results.podcasts_count', $groups->total(), ['count' => $groups->total(), 'label' => $pageConfig['group_label_plural'] ?? __('public.labels.podcasts')]) }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('public.results.public_podcasts_only') }}
        </p>
    </div>

    @if ($groups->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('public.empty.groups') }}
        </div>
    @else
        @php($groupCards = app(\App\Support\PublicFront\Cards\PublicContentGroupCardPresenter::class)->presentMany($groups, $cardTemplate, $pageConfig))

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($groupCards as $card)
                <x-public.content-group-card
                    :card="$card"
                    :card-template="$cardTemplate"
                    wire:key="public-group-{{ $card['id'] }}"
                />
            @endforeach
        </div>

        <div class="mt-6">
            {{ $groups->links() }}
        </div>
    @endif
</div>
