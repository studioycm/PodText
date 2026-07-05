@php($podcastsPage = $this->pageConfig())

<x-filament-panels::page>
    <div class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <div class="space-y-3">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                {{ $podcastsPage['group_label_plural'] ?? __('public.labels.podcasts') }}
            </p>

            <h1 class="text-3xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ $podcastsPage['title'] ?? __('public.pages.podcasts.title') }}
            </h1>

            @if(filled($podcastsPage['description'] ?? null))
                <p class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300">
                    {{ $podcastsPage['description'] }}
                </p>
            @endif
        </div>

        <livewire:public.content-group-browser />
    </div>
</x-filament-panels::page>
