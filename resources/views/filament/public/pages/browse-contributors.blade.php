<x-filament-panels::page>
    <div class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <div class="space-y-3">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ __('public.pages.contributors.kicker') }}</p>
            <h1 class="text-3xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ $contributorsConfig['title'] ?? __('public.pages.contributors.title') }}
            </h1>
            <p class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300">
                {{ $contributorsConfig['description'] ?? __('public.pages.contributors.description') }}
            </p>
        </div>

        <livewire:public.contributor-directory />
    </div>
</x-filament-panels::page>
