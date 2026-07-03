@props([
    'author',
    'fullPageUrl',
    'selected' => false,
    'selectable' => false,
])

@php
    $initial = \Illuminate\Support\Str::of($author->name)->substr(0, 1);
    $transcriptionsCount = (int) ($author->public_transcriptions_count ?? 0);
    $contentItemsCount = (int) ($author->public_content_items_count ?? 0);
    $bioPreview = filled($author->bio_markdown)
        ? str($author->bio_markdown)->stripTags()->squish()->limit(130)
        : null;
    $cardClasses = $selected
        ? 'border-primary-400 ring-2 ring-primary-200 dark:border-primary-500 dark:ring-primary-900'
        : 'border-gray-200 hover:border-primary-300 dark:border-gray-800 dark:hover:border-primary-700';
@endphp

<article
    {{ $attributes->merge(['class' => "flex h-full flex-col gap-4 rounded-lg border bg-white p-4 shadow-sm transition dark:bg-gray-900 {$cardClasses}"]) }}
    data-test="contributor-card"
    data-contributor-id="{{ $author->id }}"
>
    <div class="flex items-start gap-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100 text-lg font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-100">
            {{ $initial }}
        </div>

        <div class="min-w-0 flex-1 space-y-1">
            <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white" data-test="contributor-name">
                {{ $author->name }}
            </h3>
            <div class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="public-transcriptions-count">
                    {{ trans_choice('public.labels.public_transcriptions_count', $transcriptionsCount, ['count' => $transcriptionsCount]) }}
                </span>
                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="public-content-items-count">
                    {{ trans_choice('public.labels.public_content_items_count', $contentItemsCount, ['count' => $contentItemsCount]) }}
                </span>
            </div>
        </div>
    </div>

    @if($bioPreview)
        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300" data-test="contributor-bio-preview">
            {{ $bioPreview }}
        </p>
    @endif

    <div class="mt-auto flex flex-wrap gap-2">
        @if($selectable)
            <button
                type="button"
                wire:click="selectContributor({{ $author->id }})"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                data-test="select-contributor"
            >
                {{ __('public.actions.preview_contributor') }}
            </button>
        @endif

        <a
            href="{{ $fullPageUrl }}"
            class="inline-flex items-center justify-center rounded-md bg-gray-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-primary-200"
            data-test="contributor-link"
        >
            {{ __('public.actions.view_contributor') }}
        </a>
    </div>
</article>
