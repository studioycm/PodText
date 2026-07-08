@php
    $durationSeconds = $contentItem->media_duration_seconds ?: $contentItem->duration_seconds;
    $duration = $durationSeconds
        ? gmdate($durationSeconds >= 3600 ? 'H:i:s' : 'i:s', $durationSeconds)
        : null;
    $publishedDate = $contentItem->published_at?->timezone('Asia/Jerusalem')->format('d/m/Y');
    $categories = $contentItem->effectiveCategories()
        ->where('is_visible', true)
        ->values();
    $tags = $contentItem->relationLoaded('enabledContentTags') ? $contentItem->enabledContentTags : collect();
    $effectiveTranscription = $contentItem->effectiveTranscription();
    $transcribers = $effectiveTranscription?->relationLoaded('authors') ? $effectiveTranscription->authors : collect();
    $publicTranscriptionsCount = (int) ($contentItem->public_transcriptions_count ?? 0);
    $showTranscriptionCount = app(\App\Support\PublicContent\PublicTranscriptionPolicy::class)->countModeCountsAllPublished()
        && $publicTranscriptionsCount > 1;
@endphp

<x-filament-panels::page>
    <article class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <nav aria-label="{{ __('public.labels.breadcrumbs') }}" class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <a
                href="{{ \App\Filament\Public\Pages\BrowseContentGroups::getUrl(panel: 'public') }}"
                class="font-medium text-primary-700 hover:text-primary-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-300 dark:hover:text-primary-100"
            >
                {{ __('public.pages.browse.title') }}
            </a>
            <span aria-hidden="true">/</span>
            <a
                href="{{ \App\Filament\Public\Pages\ShowContentGroup::getUrl(['contentGroupSlug' => $contentGroup->slug], panel: 'public') }}"
                class="font-medium text-primary-700 hover:text-primary-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-300 dark:hover:text-primary-100"
            >
                {{ $contentGroup->title }}
            </a>
        </nav>

        <header class="space-y-4">
            <h1 class="text-3xl font-semibold leading-tight text-gray-950 dark:text-white">
                {{ $contentItem->title }}
            </h1>

            <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
{{--                @if ($transcribers->isNotEmpty())--}}
{{--                    <div>--}}
{{--                        <dt class="sr-only">{{ __('public.labels.transcribers') }}</dt>--}}
{{--                        <dd class="flex flex-wrap gap-2">--}}
{{--                            @foreach($transcribers as $author)--}}
{{--                                <a--}}
{{--                                    href="{{ \App\Filament\Public\Pages\ShowContributor::getUrl(['authorSlug' => $author->slug], panel: 'public') }}"--}}
{{--                                    class="font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"--}}
{{--                                    data-test="item-transcriber-link"--}}
{{--                                >--}}
{{--                                    {{ $author->name }}--}}
{{--                                </a>--}}
{{--                            @endforeach--}}
{{--                        </dd>--}}
{{--                    </div>--}}
{{--                @endif--}}

                @if ($duration)
                    <div>
                        <dt class="sr-only">{{ __('public.labels.duration') }}</dt>
                        <dd data-test="item-duration">{{ __('public.labels.duration_value', ['duration' => $duration]) }}</dd>
                    </div>
                @endif

                @if ($publishedDate)
                    <div>
                        <dt class="sr-only">{{ __('public.labels.published_at') }}</dt>
                        <dd>{{ $publishedDate }}</dd>
                    </div>
                @endif

                @if ($showTranscriptionCount)
                    <div>
                        <dt class="sr-only">{{ __('public.labels.transcriptions') }}</dt>
                        <dd data-test="item-transcription-count">
                            {{ trans_choice('public.labels.public_transcriptions_count', $publicTranscriptionsCount, ['count' => $publicTranscriptionsCount]) }}
                        </dd>
                    </div>
                @endif
            </dl>
        </header>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <aside class="space-y-5 lg:sticky lg:top-6 lg:order-2">
                <x-public.media-embed
                    :media-url="$contentItem->media_url"
                    :embed-url="$contentItem->embed_url"
                    :title="$contentItem->title"
                    :provider="$contentItem->embed_provider"
                    :source-title="$contentItem->external_title"
                    :source-description="$contentItem->external_description"
                    :duration-seconds="$durationSeconds"
                    :published-at="$contentItem->external_published_at"
                />

                <div
                    class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                    x-data="{
                        copied: false,
                        copyLink() {
                            navigator.clipboard?.writeText(window.location.href)
                            this.copied = true
                            setTimeout(() => this.copied = false, 1800)
                        },
                        shareLink() {
                            if (navigator.share) {
                                navigator.share({ title: @js($contentItem->title), url: window.location.href })
                                return
                            }

                            this.copyLink()
                        },
                    }"
                    data-test="item-share-actions"
                >
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('public.labels.share') }}
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            x-on:click="copyLink()"
                            class="rounded-md border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-primary-300 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-gray-700 dark:text-gray-200 dark:hover:border-primary-500"
                            data-test="copy-item-link"
                        >
                            <span x-show="! copied">{{ __('public.actions.copy_link') }}</span>
                            <span x-show="copied">{{ __('public.actions.copied') }}</span>
                        </button>
                        <button
                            type="button"
                            x-on:click="shareLink()"
                            class="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400"
                            data-test="share-item-link"
                        >
                            {{ __('public.actions.share') }}
                        </button>
                    </div>
                </div>
            </aside>

            <div class="space-y-8 lg:order-1">
                @if($categories->isNotEmpty() || $tags->isNotEmpty())
                    <section class="space-y-3" aria-label="{{ __('public.labels.taxonomy') }}">
                        @if($categories->isNotEmpty())
                            <div class="flex flex-wrap gap-2" data-test="item-categories">
                                @foreach($categories as $category)
                                    <a
                                        href="{{ \App\Filament\Public\Pages\BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public') }}"
                                        class="rounded-md border border-gray-200 px-2.5 py-1 text-sm text-gray-700 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-primary-500"
                                    >
                                        {{ $category->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if($tags->isNotEmpty())
                            <div class="flex flex-wrap gap-2" data-test="item-tags">
                                @foreach($tags as $tag)
                                    <a
                                        href="{{ \App\Filament\Public\Pages\BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public') }}"
                                        class="rounded-md bg-gray-950 px-2.5 py-1 text-sm text-white hover:bg-primary-700 dark:bg-gray-100 dark:text-gray-950"
                                    >
                                        {{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                @if (filled($contentItem->description_markdown))
                    <section class="space-y-3" aria-labelledby="item-description-heading">
{{--                        <h2 id="item-description-heading" class="text-xl font-semibold text-gray-950 dark:text-white">--}}
{{--                            {{ __('public.pages.item.description_heading') }}--}}
{{--                        </h2>--}}

                        <x-public.markdown-content :markdown="$contentItem->description_markdown" />
                    </section>
                @endif

                <livewire:public.content-item-transcript-viewer :content-item="$contentItem" />
            </div>
        </div>
    </article>
</x-filament-panels::page>
