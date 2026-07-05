<div>
    <div class="mb-4 grid gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-[1fr_auto] md:items-end">
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

        <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
            <span>{{ __('public.filters.sort_items') }}</span>
            <select
                wire:model.live="sort"
                data-test="item-sort"
                class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
            >
                <option value="newest">{{ __('public.sort.newest') }}</option>
                <option value="title">{{ __('public.sort.title') }}</option>
            </select>
        </label>
    </div>

    @if ($items->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('public.empty.items') }}
        </div>
    @else
        <x-public.content-item-grid
            :items="$items"
            :card-options="$cardOptions"
            layout="rows"
            :card-template="$cardTemplate"
        />

        @if($items->hasPages())
            <div class="mt-6" data-test="group-item-pagination">
                {{ $items->links() }}
            </div>
        @endif
    @endif
</div>
