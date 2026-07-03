<x-filament-panels::page>
    <div class="space-y-8" dir="{{ __('public.meta.dir') }}">
        <div class="space-y-4">
            <p class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ __('public.pages.contributor.kicker') }}</p>
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

        <livewire:public.contributor-content-items :author="$author" />
    </div>
</x-filament-panels::page>
