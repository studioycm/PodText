@php
    $podcastsPage = $this->pageConfig();
    $groupPageConfig = $podcastsPage['group_page'] ?? [];
    $categories = ($contentGroup->relationLoaded('categories') ? $contentGroup->categories : collect())
        ->where('is_visible', true)
        ->values();
    $publicItemsCount = (int) ($contentGroup->public_content_items_count ?? 0);
    $itemLabel = $publicItemsCount === 1
        ? $contentGroup->default_item_type_label_singular
        : $contentGroup->default_item_type_label_plural;
    $publicTranscriptionsCount = (int) ($contentGroup->public_transcriptions_count ?? 0);
    $publicTranscriberCount = (int) ($contentGroup->public_transcriber_count ?? 0);
    $totalWordCount = (int) ($contentGroup->public_total_word_count ?? 0);
    $totalReadingMinutes = $totalWordCount > 0 ? max(1, (int) ceil($totalWordCount / 200)) : 0;
    $latestTranscriptionDate = filled($contentGroup->public_latest_transcription_published_at ?? null)
        ? \Carbon\Carbon::parse($contentGroup->public_latest_transcription_published_at)->timezone('Asia/Jerusalem')->format('d/m/Y')
        : null;
    $initials = str($contentGroup->title)->squish()->substr(0, 2)->upper();
    $pageImage = $this->pageImage();
@endphp

<x-filament-panels::page>
    <article class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <a
            href="{{ \App\Filament\Public\Pages\BrowsePublicContentGroups::getUrl(panel: 'public') }}"
            class="inline-flex text-sm font-medium text-primary-700 hover:text-primary-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-300 dark:hover:text-primary-100"
        >
            {{ __('public.actions.back_to_podcasts', ['label' => $podcastsPage['group_label_plural'] ?? __('public.labels.podcasts')]) }}
        </a>

        <header class="grid gap-6 md:grid-cols-[16rem_1fr] md:items-start">
            @if ($pageImage['url'])
                <span
                    class="block aspect-square w-full overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10"
                    data-test="content-group-detail-image"
                    data-content-group-image-source="{{ $pageImage['source'] }}"
                >
                    <img
                        src="{{ $pageImage['url'] }}"
                        alt="{{ $pageImage['alt'] ?? '' }}"
                        class="h-full w-full object-cover"
                    >
                </span>
            @else
                <div class="flex aspect-square w-full items-center justify-center rounded-lg bg-gray-100 text-4xl font-semibold text-gray-500 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10" data-test="content-group-detail-fallback">
                    {{ $initials }}
                </div>
            @endif

            <div class="space-y-4">
                <x-public.type-label :label="$podcastsPage['group_label_singular'] ?? $contentGroup->group_type_label_singular" />

                <h1 class="text-3xl font-semibold leading-tight text-gray-950 dark:text-white">
                    {{ $contentGroup->title }}
                </h1>

                <dl class="flex flex-wrap gap-2 text-sm font-medium text-gray-700 dark:text-gray-200" data-test="content-group-public-stats">
                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="content-group-public-count">
                        <dt class="sr-only">{{ __('public.labels.items') }}</dt>
                        <dd>{{ __('public.labels.public_group_items_count', ['count' => $publicItemsCount, 'label' => $itemLabel]) }}</dd>
                    </div>

                    @if($totalReadingMinutes > 0)
                        <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="content-group-total-reading-time">
                            <dt class="sr-only">{{ __('public.labels.reading_time') }}</dt>
                            <dd>{{ trans_choice('public.labels.public_group_reading_minutes_count', $totalReadingMinutes, ['count' => $totalReadingMinutes]) }}</dd>
                        </div>
                    @endif

                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="content-group-transcription-count">
                        <dt class="sr-only">{{ __('public.labels.transcriptions') }}</dt>
                        <dd>{{ trans_choice('public.labels.public_transcriptions_count', $publicTranscriptionsCount, ['count' => $publicTranscriptionsCount]) }}</dd>
                    </div>

                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="content-group-transcriber-count">
                        <dt class="sr-only">{{ __('public.labels.transcribers') }}</dt>
                        <dd>{{ trans_choice('public.labels.public_transcribers_count', $publicTranscriberCount, ['count' => $publicTranscriberCount]) }}</dd>
                    </div>

                    @if($latestTranscriptionDate)
                        <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="content-group-latest-transcription-date">
                            <dt class="sr-only">{{ __('public.labels.published_at') }}</dt>
                            <dd>{{ __('public.labels.public_group_latest_transcription_date', ['date' => $latestTranscriptionDate]) }}</dd>
                        </div>
                    @endif
                </dl>

                @if(($groupPageConfig['show_categories'] ?? true) && $categories->isNotEmpty())
                    <div class="flex flex-wrap gap-2" data-test="content-group-categories">
                        @foreach($categories as $category)
                            <a
                                href="{{ \App\Filament\Public\Pages\BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public') }}"
                                class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300"
                            >
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @if (($groupPageConfig['show_description'] ?? true) && filled($contentGroup->description_markdown))
                    <div x-data="{ expanded: false }" class="space-y-3">
                        <div x-bind:class="expanded ? '' : 'max-h-48 overflow-hidden'">
                            <x-public.markdown-content :markdown="$contentGroup->description_markdown" />
                        </div>

                        <button
                            type="button"
                            x-on:click="expanded = ! expanded"
                            class="text-sm font-medium text-primary-700 hover:text-primary-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-300 dark:hover:text-primary-100"
                        >
                            <span x-show="! expanded">{{ __('public.actions.expand') }}</span>
                            <span x-show="expanded">{{ __('public.actions.collapse') }}</span>
                        </button>
                    </div>
                @endif
            </div>
        </header>

        <section class="space-y-4" aria-labelledby="group-items-heading">
            <h2 id="group-items-heading" class="text-xl font-semibold text-gray-950 dark:text-white">
                {{ __('public.pages.group.items_heading', ['label' => $itemLabel]) }}
            </h2>

            <livewire:public.content-item-browser :content-group="$contentGroup" />
        </section>
    </article>
</x-filament-panels::page>
