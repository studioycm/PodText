<div>
    <div class="mb-4 flex justify-end">
        <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
            <span>{{ __('public.filters.sort_items') }}</span>
            <select
                wire:model.live="sort"
                data-test="item-sort"
                class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option value="newest">{{ __('public.sort.newest') }}</option>
                <option value="title">{{ __('public.sort.title') }}</option>
            </select>
        </label>
    </div>

    @if ($this->items->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('public.empty.items') }}
        </div>
    @else
        <div class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            @foreach ($this->items as $item)
                <x-public.content-item-row :item="$item" wire:key="public-item-{{ $item->id }}" />
            @endforeach
        </div>
    @endif
</div>
