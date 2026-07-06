<div class="space-y-4" data-test="contributor-content-items">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ __('public.pages.contributor.items_heading') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300" data-test="contributor-items-count">
                {{ trans_choice('public.results.count', $items->total(), ['count' => $items->total()]) }}
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end" data-test="contributor-page-item-controls">
            @if($config['page']['search_enabled'] ?? true)
                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.search_related_items') }}</span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        data-test="contributor-page-item-search"
                        placeholder="{{ __('public.filters.search_related_items_placeholder') }}"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 sm:w-64"
                    >
                </label>
            @endif

            <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                <span>{{ __('public.filters.sort') }}</span>
                <select
                    wire:model.live="sort"
                    data-test="contributor-page-item-sort"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                >
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                <span>{{ __('public.filters.per_page') }}</span>
                <select
                    wire:model.live="perPage"
                    data-test="contributor-page-item-page-size"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                >
                    @foreach($pageSizeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button
                type="button"
                wire:click="clearItemSearch"
                data-test="clear-contributor-page-item-search"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                {{ __('public.actions.clear_filters') }}
            </button>
        </div>
    </div>

    @if($items->isNotEmpty())
        <x-public.contributor-item-grid
            :items="$items"
            :card-options="$cardOptions"
            :card-template="$cardTemplate"
            :columns="$config['page']['grid_columns'] ?? 3"
            :gap="$config['page']['grid_gap'] ?? 'comfortable'"
            layout="cards"
        />

        @if($items->hasPages())
            <div data-test="contributor-items-pagination">
                {{ $items->links() }}
            </div>
        @endif
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-contributor-items">
            {{ __('public.empty.contributor_items') }}
        </div>
    @endif
</div>
