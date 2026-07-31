<x-filament-panels::page>
    <div class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <div class="space-y-3">
            <p class="text-primary-600 dark:text-primary-400 text-sm font-medium">
                {{ __('public.pages.tag.kicker') }}
            </p>
            <h1 class="text-3xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ $contentTag->name }}
            </h1>
        </div>

        <livewire:public.content-item-search context="tag" :tag-id="$contentTag->id" />
    </div>
</x-filament-panels::page>
