@props([
    'activeCategoryIds' => [],
    'activeFilterCount' => 0,
    'activeTagIds' => [],
    'authorOptions' => [],
    'categoryOptions' => [],
    'contentGroupOptions' => [],
    'providerOptions' => [],
    'tagOptions' => [],
])

<div
    x-data="{ open: false }"
    x-on:open-public-filter-drawer.window="open = true"
    x-on:keydown.escape.window="open = false"
    data-test="filter-drawer-root"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-40 bg-gray-950/40"
        x-on:click="open = false"
        data-test="filter-drawer-backdrop"
    ></div>

    <aside
        x-show="open"
        x-cloak
        x-transition
        class="fixed inset-y-0 end-0 z-50 flex w-full max-w-md flex-col overflow-hidden border-s border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
        aria-label="{{ __('public.filters.drawer_title') }}"
        data-test="filter-drawer"
    >
        <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('public.filters.drawer_title') }}
                </h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-test="drawer-active-filter-count">
                    {{ trans_choice('public.filters.active_count', $activeFilterCount, ['count' => $activeFilterCount]) }}
                </p>
            </div>

            <button
                type="button"
                x-on:click="open = false"
                class="inline-flex size-9 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                data-test="close-filter-drawer"
                aria-label="{{ __('public.actions.close') }}"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto p-4">
            <section class="space-y-3" data-test="category-toggle-filter">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('public.filters.category') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($categoryOptions as $value => $label)
                        @php($active = in_array((int) $value, $activeCategoryIds, true))
                        <button
                            type="button"
                            wire:click="toggleCategoryFilter({{ (int) $value }})"
                            data-test="filter-category-toggle"
                            data-active="{{ $active ? 'true' : 'false' }}"
                            class="inline-flex items-center rounded-md border px-3 py-1.5 text-sm font-medium transition {{ $active ? 'border-primary-600 bg-primary-600 text-white dark:border-primary-300 dark:bg-primary-300 dark:text-primary-950' : 'border-gray-300 text-gray-700 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-200 dark:hover:border-primary-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="space-y-3" data-test="tag-toggle-filter">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('public.filters.tag') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($tagOptions as $value => $label)
                        @php($active = in_array((int) $value, $activeTagIds, true))
                        <button
                            type="button"
                            wire:click="toggleTagFilter({{ (int) $value }})"
                            data-test="filter-tag-toggle"
                            data-active="{{ $active ? 'true' : 'false' }}"
                            class="inline-flex items-center rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $active ? 'border-gray-950 bg-gray-950 text-white dark:border-gray-100 dark:bg-gray-100 dark:text-gray-950' : 'border-gray-300 text-gray-700 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-200 dark:hover:border-primary-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-3" data-test="drawer-select-filters">
                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.group') }}</span>
                    <select wire:model.live="filterContentGroupId" data-test="filter-group" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                        <option value="">{{ __('public.filters.any') }}</option>
                        @foreach($contentGroupOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.author') }}</span>
                    <select wire:model.live="filterAuthorId" data-test="filter-author" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                        <option value="">{{ __('public.filters.any') }}</option>
                        @foreach($authorOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.provider') }}</span>
                    <select wire:model.live="filterProvider" data-test="filter-provider" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                        <option value="">{{ __('public.filters.any') }}</option>
                        @foreach($providerOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.has_media') }}</span>
                    <select wire:model.live="filterHasMedia" data-test="filter-has-media" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                        <option value="">{{ __('public.filters.any') }}</option>
                        <option value="yes">{{ __('public.labels.yes') }}</option>
                        <option value="no">{{ __('public.labels.no') }}</option>
                    </select>
                </label>
            </section>

            <section class="grid gap-3 md:grid-cols-2" data-test="advanced-filters">
                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.effective_from') }}</span>
                    <input type="date" wire:model.live="filterEffectiveFrom" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.effective_until') }}</span>
                    <input type="date" wire:model.live="filterEffectiveUntil" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.original_from') }}</span>
                    <input type="date" wire:model.live="filterOriginalFrom" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.original_until') }}</span>
                    <input type="date" wire:model.live="filterOriginalUntil" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.duration_min') }}</span>
                    <input type="number" min="0" wire:model.live="filterDurationMin" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    <span>{{ __('public.filters.duration_max') }}</span>
                    <input type="number" min="0" wire:model.live="filterDurationMax" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                </label>
            </section>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-gray-200 p-4 dark:border-gray-800">
            <button
                type="button"
                wire:click="clearFilters"
                data-test="drawer-clear-filters"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                {{ __('public.actions.clear_filters') }}
            </button>

            <button
                type="button"
                x-on:click="open = false"
                class="inline-flex items-center justify-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400"
                data-test="apply-filter-drawer"
            >
                {{ __('public.actions.apply_filters') }}
            </button>
        </div>
    </aside>
</div>
