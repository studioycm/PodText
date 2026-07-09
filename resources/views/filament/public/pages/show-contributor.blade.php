@php
    $pageImage = $this->pageImage();
    $initial = str($author->name)->squish()->substr(0, 1)->upper();
@endphp

<x-filament-panels::page>
    <div class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <div class="grid gap-6 md:grid-cols-[8rem_minmax(0,1fr)] md:items-start">
            @if($pageImage['url'])
                <span
                    class="block aspect-square w-full overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10"
                    data-test="contributor-page-image"
                    data-contributor-image-source="{{ $pageImage['source'] }}"
                >
                    <img
                        src="{{ $pageImage['url'] }}"
                        alt=""
                        class="h-full w-full object-cover"
                    >
                </span>
            @else
                <div class="flex aspect-square w-full items-center justify-center rounded-full bg-primary-100 text-4xl font-semibold text-primary-800 ring-1 ring-gray-950/10 dark:bg-primary-900 dark:text-primary-100 dark:ring-white/10" data-test="contributor-page-fallback">
                    {{ $initial }}
                </div>
            @endif

            <div class="space-y-4">
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                    {{ $contributorsConfig['label_singular'] ?? __('public.pages.contributor.kicker') }}
                </p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-normal text-gray-950 dark:text-white" data-test="contributor-page-name">
                        {{ $author->name }}
                    </h1>
                    <div class="flex flex-wrap gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="public-transcriptions-count">
                            {{ trans_choice('public.labels.public_transcriptions_count', (int) $author->public_transcriptions_count, ['count' => (int) $author->public_transcriptions_count]) }}
                        </span>
                        <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="public-content-items-count">
                            {{ trans_choice('public.labels.public_content_items_count', (int) $author->public_content_items_count, ['count' => (int) $author->public_content_items_count]) }}
                        </span>
                    </div>
                </div>

                @if(filled($author->bio_markdown))
                    <x-public.markdown-content
                        :markdown="$author->bio_markdown"
                        class="max-w-3xl"
                        data-test="contributor-bio"
                    />
                @endif
            </div>
        </div>

        <livewire:public.contributor-content-items :author="$author" />
    </div>
</x-filament-panels::page>
