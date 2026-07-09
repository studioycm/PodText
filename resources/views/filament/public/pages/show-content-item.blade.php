@php
    $mediaDurationSeconds = $this->mediaDurationSeconds();
    $pageImage = $this->pageImage();
    $podcastIdentity = $this->podcastIdentity();
    $infoParts = $this->infoParts();
@endphp

<x-filament-panels::page>
    <article class="space-y-8" dir="{{ __('public.meta.dir') }}">
        @if($this->showBreadcrumbs())
            <nav
                aria-label="{{ __('public.labels.breadcrumbs') }}"
                class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300"
                data-test="item-breadcrumbs"
            >
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
        @endif

        <header class="grid gap-6 md:grid-cols-[14rem_minmax(0,1fr)] md:items-start">
            @if($pageImage)
                <span
                    class="block aspect-square w-full overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10"
                    data-test="item-page-image"
                    data-item-page-image-source="{{ $pageImage['source'] }}"
                >
                    <img
                        src="{{ $pageImage['url'] }}"
                        alt=""
                        class="h-full w-full object-cover"
                    >
                </span>
            @endif

            <div class="min-w-0 space-y-4">
                @if($podcastIdentity)
                    @php
                        $podcastIcon = \App\Support\PublicFront\Cards\PublicFrontCardIconResolver::resolve($podcastIdentity['icon'] ?? null);
                        $podcastIconPosition = $podcastIdentity['icon_position'] ?? 'hidden';
                    @endphp

                    <a
                        href="{{ $podcastIdentity['url'] }}"
                        class="{{ $podcastIdentity['class'] }}"
                        data-test="item-podcast-identity"
                        data-podcast-identity-mode="{{ $podcastIdentity['mode'] }}"
                        data-podcast-identity-icon="{{ $podcastIdentity['icon'] ?? 'none' }}"
                        data-podcast-identity-icon-position="{{ $podcastIconPosition }}"
                    >
                        @if($podcastIcon && $podcastIconPosition === 'inline_before')
                            <x-filament::icon :icon="$podcastIcon" class="h-4 w-4 shrink-0" />
                        @endif

                        <span class="min-w-0 truncate">{{ $podcastIdentity['label'] }}</span>

                        @if($podcastIcon && $podcastIconPosition === 'inline_after')
                            <x-filament::icon :icon="$podcastIcon" class="h-4 w-4 shrink-0" />
                        @endif
                    </a>
                @endif

                <h1 class="text-3xl font-semibold leading-tight text-gray-950 dark:text-white" data-test="item-page-title">
                    {{ $contentItem->title }}
                </h1>

                @if($infoParts !== [])
                    <dl class="flex flex-wrap items-center gap-2" data-test="item-info-line">
                        @foreach($infoParts as $infoPart)
                            @php
                                $key = $infoPart['key'];
                                $value = $infoPart['value'];
                                $isLinkList = in_array($key, ['categories', 'tags', 'transcribers'], true);
                            @endphp

                            <div>
                                <dt class="sr-only">{{ $infoPart['part']['label'] ?? __("public.item_page.info_fields.{$key}_long") }}</dt>
                                <dd>
                                    <x-public.card-part-shell
                                        :part="$infoPart['part']"
                                        class="{{ $infoPart['class'] }}"
                                        data-test="{{ $infoPart['data_test'] }}"
                                    >
                                        @if($isLinkList)
                                            <span
                                                class="flex min-w-0 flex-wrap items-center gap-1.5"
                                                @if($key === 'categories') data-test="item-categories" @endif
                                                @if($key === 'tags') data-test="item-tags" @endif
                                            >
                                                @foreach($value as $link)
                                                    <a
                                                        href="{{ $link['url'] }}"
                                                        class="font-medium underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                                        @if($key === 'transcribers') data-test="item-transcriber-link" @endif
                                                    >
                                                        {{ $link['label'] }}
                                                    </a>
                                                @endforeach
                                            </span>
                                        @else
                                            <span
                                                class="block min-w-0 truncate"
                                                @if($key === 'duration') data-test="item-duration" @endif
                                                @if($key === 'reading_time') data-test="reading-time" @endif
                                                @if($key === 'word_count') data-test="transcript-length" @endif
                                                @if($key === 'transcription_count') data-test="item-transcription-count" @endif
                                            >
                                                @if($key === 'duration')
                                                    {{ __('public.labels.duration_value', ['duration' => $value]) }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </span>
                                        @endif
                                    </x-public.card-part-shell>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
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
                    :duration-seconds="$mediaDurationSeconds"
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
                @if (filled($contentItem->description_markdown))
                    <section class="space-y-3" aria-label="{{ __('public.pages.item.description_heading') }}">
                        <x-public.markdown-content :markdown="$contentItem->description_markdown" />
                    </section>
                @endif

                <livewire:public.content-item-transcript-viewer :content-item="$contentItem" />
            </div>
        </div>
    </article>
</x-filament-panels::page>
