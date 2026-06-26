<x-filament-panels::page>
    <article class="space-y-8">
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
            <x-public.type-label :label="$contentItem->effectiveTypeLabelSingular()" />

            <h1 class="text-3xl font-semibold leading-tight text-gray-950 dark:text-white">
                {{ $contentItem->title }}
            </h1>

            <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
                @if ($contentItem->authors->isNotEmpty())
                    <div>
                        <dt class="sr-only">{{ __('public.labels.authors') }}</dt>
                        <dd>{{ $contentItem->authors->pluck('name')->join(', ') }}</dd>
                    </div>
                @endif

                @if ($contentItem->duration_seconds)
                    <div>
                        <dt class="sr-only">{{ __('public.labels.duration') }}</dt>
                        <dd>{{ __('public.labels.duration_value', ['duration' => gmdate('H:i:s', $contentItem->duration_seconds)]) }}</dd>
                    </div>
                @endif

                @if ($contentItem->published_at)
                    <div>
                        <dt class="sr-only">{{ __('public.labels.published_at') }}</dt>
                        <dd>{{ $contentItem->published_at->toFormattedDateString() }}</dd>
                    </div>
                @endif
            </dl>
        </header>

        <x-public.media-embed
            :media-url="$contentItem->media_url"
            :embed-url="$contentItem->embed_url"
            :title="$contentItem->title"
        />

        @if (filled($contentItem->description_markdown))
            <section class="space-y-3" aria-labelledby="item-description-heading">
                <h2 id="item-description-heading" class="text-xl font-semibold text-gray-950 dark:text-white">
                    {{ __('public.pages.item.description_heading') }}
                </h2>

                <x-public.markdown-content :markdown="$contentItem->description_markdown" />
            </section>
        @endif

        <section class="space-y-3" aria-labelledby="item-transcript-heading">
            <h2 id="item-transcript-heading" class="text-xl font-semibold text-gray-950 dark:text-white">
                {{ __('public.pages.item.transcript_heading') }}
            </h2>

            <x-public.markdown-content :markdown="$contentItem->transcript_markdown" />
        </section>
    </article>
</x-filament-panels::page>
