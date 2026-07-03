<div class="space-y-6" data-test="contributor-directory">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                <span>{{ __('public.filters.search') }}</span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    data-test="contributor-search"
                    placeholder="{{ __('public.filters.search_contributors_placeholder') }}"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                >
            </label>

            <button
                type="button"
                wire:click="clearSearch"
                data-test="clear-contributor-search"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                {{ __('public.actions.clear_filters') }}
            </button>
        </div>

        <div class="mt-4 flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-medium text-gray-900 dark:text-gray-100" data-test="contributor-result-count">
                {{ trans_choice('public.results.contributors_count', $contributors->total(), ['count' => $contributors->total()]) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('public.results.public_contributors_only') }}
            </p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(19rem,24rem)]">
        <div class="space-y-4">
            @if($contributors->isNotEmpty())
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2" data-test="contributor-grid">
                    @foreach($contributors as $author)
                        <x-public.contributor-card
                            :author="$author"
                            :full-page-url="$this->contributorUrl($author)"
                            :selected="$selectedContributor?->is($author) ?? false"
                            selectable
                            wire:key="contributor-card-{{ $author->id }}"
                        />
                    @endforeach
                </div>

                @if($contributors->hasPages())
                    <div data-test="contributor-pagination">
                        {{ $contributors->links() }}
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-contributors">
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('public.empty.contributors') }}</p>
                    <p class="mt-1">{{ __('public.empty.contributors_description') }}</p>
                </div>
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start" data-test="contributor-preview">
            @if($selectedContributor)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                        {{ __('public.pages.contributors.preview_kicker') }}
                    </p>
                    <h2 class="mt-1 text-lg font-semibold tracking-normal text-gray-950 dark:text-white" data-test="selected-contributor-name">
                        {{ $selectedContributor->name }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('public.pages.contributors.preview_description') }}
                    </p>
                    <a
                        href="{{ $this->contributorUrl($selectedContributor) }}"
                        class="mt-4 inline-flex items-center justify-center rounded-md bg-gray-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-primary-200"
                        data-test="selected-contributor-link"
                    >
                        {{ __('public.actions.view_all_contributor_items') }}
                    </a>
                </div>

                @if($previewItems->isNotEmpty())
                    <x-public.content-item-grid
                        :items="$previewItems"
                        :card-options="$cardOptions"
                        layout="rows"
                    />
                @else
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-contributor-preview">
                        {{ __('public.empty.contributor_preview') }}
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-contributor-preview">
                    {{ __('public.empty.contributor_preview') }}
                </div>
            @endif
        </aside>
    </div>
</div>
