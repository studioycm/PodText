<x-filament-panels::page>
    <section class="space-y-6">
        <div class="max-w-3xl space-y-2">
            <p class="text-sm font-medium text-primary-700 dark:text-primary-300">
                {{ __('public.pages.browse.kicker') }}
            </p>

            <p class="text-base leading-7 text-gray-600 dark:text-gray-300">
                {{ __('public.pages.browse.description') }}
            </p>
        </div>

        <livewire:public.content-group-browser />
    </section>
</x-filament-panels::page>
