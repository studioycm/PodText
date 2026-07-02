<div class="space-y-6" data-test="content-item-search">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100" data-test="result-count">
                    {{ trans_choice('public.results.count', $this->resultCount(), ['count' => $this->resultCount()]) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('public.results.public_items_only') }}
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
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

        {{ $this->table }}
    </div>
</div>
