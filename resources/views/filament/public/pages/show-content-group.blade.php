<x-filament-panels::page>
    <article class="space-y-8">
        <a
            href="{{ \App\Filament\Public\Pages\BrowseContentGroups::getUrl(panel: 'public') }}"
            class="inline-flex text-sm font-medium text-primary-700 hover:text-primary-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-300 dark:hover:text-primary-100"
        >
            {{ __('public.actions.back_to_groups') }}
        </a>

        <header class="grid gap-6 md:grid-cols-[16rem_1fr] md:items-start">
            @if ($contentGroup->cover_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($contentGroup->cover_path) }}"
                    alt=""
                    class="aspect-square w-full rounded-lg object-cover ring-1 ring-gray-950/10 dark:ring-white/10"
                >
            @endif

            <div class="space-y-4">
                <x-public.type-label :label="$contentGroup->group_type_label_singular" />

                <h1 class="text-3xl font-semibold leading-tight text-gray-950 dark:text-white">
                    {{ $contentGroup->title }}
                </h1>

                @if (filled($contentGroup->description_markdown))
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
                {{ __('public.pages.group.items_heading') }}
            </h2>

            <livewire:public.content-item-browser :content-group="$contentGroup" />
        </section>
    </article>
</x-filament-panels::page>
