@props([
    'authorOptions' => [],
    'categoryOptions' => [],
    'contentGroupOptions' => [],
    'providerOptions' => [],
    'tagOptions' => [],
])

<div
    x-data="{ advancedOpen: false }"
    class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    data-test="filter-panel"
>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
            <span>{{ __('public.filters.category') }}</span>
            <select wire:model.live="filterCategoryId" data-test="filter-category" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('public.filters.any') }}</option>
                @foreach($categoryOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
            <span>{{ __('public.filters.tag') }}</span>
            <select wire:model.live="filterTagId" data-test="filter-tag" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('public.filters.any') }}</option>
                @foreach($tagOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

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
    </div>

    <div class="mt-4">
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            x-on:click="advancedOpen = ! advancedOpen"
            :aria-expanded="advancedOpen.toString()"
            data-test="advanced-filters-toggle"
        >
            {{ __('public.filters.advanced') }}
        </button>
    </div>

    <div x-show="advancedOpen" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4" data-test="advanced-filters">
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
    </div>
</div>
