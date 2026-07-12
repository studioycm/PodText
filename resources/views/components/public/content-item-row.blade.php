@props(['item'])

@php
    $itemUrl = \App\Filament\Public\Pages\ShowContentItem::getUrl([
        'contentGroupSlug' => $item->contentGroup->slug,
        'contentItemSlug' => $item->slug,
    ], panel: 'public');
    $transcriberNames = $item->effectiveTranscription()?->transcriberNames() ?? [];
    $displayTitle = app(\App\Support\PublicFront\ContentItemDisplayTitle::class)->combined($item);
@endphp

<article {{ $attributes->merge(['class' => 'p-4 transition hover:bg-primary-50/60 dark:hover:bg-primary-400/5']) }}>
    <a href="{{ $itemUrl }}" class="grid gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 md:grid-cols-[1fr_auto] md:items-center">
        <div class="space-y-2">
            <x-public.type-label :label="$item->effectiveTypeLabelSingular()" />

            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ $displayTitle }}
            </h3>

            @if ($transcriberNames !== [])
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ collect($transcriberNames)->join(', ') }}
                </p>
            @endif
        </div>

        @if ($item->duration_seconds)
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ gmdate('H:i:s', $item->duration_seconds) }}
            </p>
        @endif
    </a>
</article>
