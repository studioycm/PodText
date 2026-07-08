<div class="space-y-4" data-test="top-transcribers-section" data-section-key="{{ $sectionKey }}">
    @if($contributors->isNotEmpty())
        <div
            class="flex snap-x gap-3 overflow-x-auto pb-2"
            data-test="top-transcribers-selector"
            data-layout="{{ $config['top_transcribers']['layout'] ?? 'horizontal' }}"
        >
            @foreach($contributors as $author)
                <div class="min-w-[14rem] max-w-[18rem] snap-start">
                    <x-public.contributor-card
                        :author="$author"
                        :full-page-url="$this->contributorUrl($author)"
                        :selected="$selectedContributor?->is($author) ?? false"
                        :card-template="$cardTemplate"
                        compact
                        selectable
                        wire:key="top-transcriber-selector-{{ $sectionKey }}-{{ $author->id }}"
                    />
                </div>
            @endforeach
        </div>

        <section
            class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            data-test="top-transcriber-preview"
        >
            @if($selectedContributor)
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                            {{ __('public.pages.contributors.top_preview_kicker') }}
                        </p>
                        <h3 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white" data-test="top-transcriber-preview-name">
                            {{ $selectedContributor->name }}
                        </h3>

                        @if($config['top_transcribers']['show_count_badge'] ?? true)
                            <div class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300" data-test="top-transcriber-preview-counts">
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="public-transcriptions-count">
                                    {{ trans_choice('public.labels.public_transcriptions_count', (int) $selectedContributor->public_transcriptions_count, ['count' => (int) $selectedContributor->public_transcriptions_count]) }}
                                </span>
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="public-content-items-count">
                                    {{ trans_choice('public.labels.public_content_items_count', (int) $selectedContributor->public_content_items_count, ['count' => (int) $selectedContributor->public_content_items_count]) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('public.filters.per_page') }}</span>
                            <select
                                wire:model.live="previewPerPage"
                                data-test="top-transcriber-preview-page-size"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                            >
                                @foreach($pageSizeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if($config['top_transcribers']['show_full_page_link'] ?? true)
                            <a
                                href="{{ $this->contributorUrl($selectedContributor) }}"
                                class="inline-flex items-center justify-center rounded-md bg-gray-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-primary-200"
                                data-test="top-transcriber-full-page-link"
                            >
                                {{ __('public.actions.view_all_contributor_items') }}
                            </a>
                        @endif
                    </div>
                </div>

                @if($previewItems->count() > 0)
                    <div data-test="top-transcriber-preview-items-grid">
                        <x-public.contributor-item-grid
                            :items="$previewItems"
                            :card-options="$cardOptions"
                            :card-template="$contentItemCardTemplate"
                            :contributor-context="$selectedContributor"
                            :columns="$config['top_transcribers']['preview_grid_columns'] ?? 3"
                            layout="cards"
                        />
                    </div>

                    @if($previewItems->lastPage() > 1)
                        <div class="flex items-center justify-center gap-2" data-test="top-transcriber-preview-pagination">
                            <button
                                type="button"
                                wire:click="previousPreviewPage"
                                @disabled($previewItems->currentPage() <= 1)
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                data-test="top-transcriber-preview-previous"
                            >
                                {{ __('public.actions.previous') }}
                            </button>

                            <span class="text-xs text-gray-500 dark:text-gray-400" data-test="top-transcriber-preview-page-indicator">
                                {{ $previewItems->currentPage() }} / {{ $previewItems->lastPage() }}
                            </span>

                            <button
                                type="button"
                                wire:click="nextPreviewPage({{ $previewItems->lastPage() }})"
                                @disabled($previewItems->currentPage() >= $previewItems->lastPage())
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                data-test="top-transcriber-preview-next"
                            >
                                {{ __('public.actions.next') }}
                            </button>
                        </div>
                    @endif
                @else
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300" data-test="empty-top-transcriber-preview">
                        {{ __('public.empty.contributor_preview') }}
                    </div>
                @endif
            @endif
        </section>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="homepage-section-empty">
            {{ __('public.empty.contributors') }}
        </div>
    @endif
</div>
