<div>
    <div class="mb-6 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
        <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
            <span>{{ __('public.filters.search_groups') }}</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                data-test="group-search"
                class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                placeholder="{{ __('public.filters.search_placeholder') }}"
            >
        </label>

        <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
            <span>{{ __('public.filters.sort') }}</span>
            <select
                wire:model.live="sort"
                data-test="group-sort"
                class="rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option value="newest">{{ __('public.sort.newest') }}</option>
                <option value="title">{{ __('public.sort.title') }}</option>
            </select>
        </label>
    </div>

    @php($groups = $this->groups)

    @if ($groups->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('public.empty.groups') }}
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $group)
                <x-public.content-group-card :group="$group" wire:key="public-group-{{ $group->id }}" />
            @endforeach
        </div>

        <div class="mt-6">
            {{ $groups->links() }}
        </div>
    @endif
</div>
