<x-filament-panels::page>
    <div class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <div class="space-y-3">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ __('public.pages.category.kicker') }}</p>
            <h1 class="text-3xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ $category->name }}
            </h1>
            @if(filled($category->description_markdown))
                <div class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300">
                    <x-public.markdown-content :markdown="$category->description_markdown" />
                </div>
            @endif
        </div>

        <livewire:public.content-item-search context="category" :category-id="$category->id" />
    </div>
</x-filament-panels::page>
