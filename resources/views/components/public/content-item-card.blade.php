@props([
    'item',
    'options' => \App\Support\PublicContent\PublicContentCardOptions::fromSettings(),
    'layout' => 'cards',
])

@php
    $itemUrl = \App\Filament\Public\Pages\ShowContentItem::getUrl([
        'contentGroupSlug' => $item->contentGroup->slug,
        'contentItemSlug' => $item->slug,
    ], panel: 'public');
    $effectiveTranscription = $item->effectiveTranscription();
    $effectiveDate = $effectiveTranscription?->published_at?->timezone('Asia/Jerusalem')->format('d/m/Y');
    $categories = $item->effectiveCategories()
        ->where('is_visible', true)
        ->values();
    $tags = $item->relationLoaded('enabledContentTags') ? $item->enabledContentTags : $item->publicTags();
    $duration = $item->duration_seconds
        ? gmdate($item->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $item->duration_seconds)
        : null;
    $isRow = $layout === 'rows';
    $articleClasses = $isRow
        ? 'grid gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 md:grid-cols-[minmax(12rem,16rem)_1fr]'
        : 'flex h-full flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 '.$options->cardPaddingClass();
    $imageClasses = $isRow
        ? 'aspect-[16/10] md:h-full'
        : $options->imageClass();
@endphp

<article
    {{ $attributes->merge(['class' => $articleClasses]) }}
    data-test="content-item-card"
    data-card-density="{{ $options->density }}"
    data-card-image-size="{{ $options->imageSize }}"
    data-card-title-size="{{ $options->titleSize }}"
    data-result-layout="{{ $layout }}"
>
    @if($options->imageSize !== 'hidden')
        <a
            href="{{ $itemUrl }}"
            class="block overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800 {{ $imageClasses }}"
            aria-label="{{ $item->title }}"
        >
            @if($item->external_thumbnail_url)
                <img
                    src="{{ $item->external_thumbnail_url }}"
                    alt=""
                    class="h-full w-full object-cover"
                    loading="lazy"
                >
            @else
                <div class="flex h-full min-h-24 w-full items-center justify-center bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ $item->effectiveTypeLabelSingular() }}
                </div>
            @endif
        </a>
    @endif

    <div class="flex flex-1 flex-col gap-3">
        <div class="space-y-2">
            @if($options->showGroupBadge)
                <x-public.content-group-badge :group="$item->contentGroup" />
            @endif

            <h3 class="font-semibold leading-7 text-gray-950 dark:text-white {{ $options->titleClass() }}">
                <a href="{{ $itemUrl }}" data-test="content-item-title">
                    {{ $item->title }}
                </a>
            </h3>
        </div>

        @if($options->showDescription && $options->descriptionLines > 0 && filled($item->description_markdown))
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300 {{ $options->descriptionClass() }}" data-test="item-description">
                {{ str($item->description_markdown)->stripTags()->squish() }}
            </p>
        @endif

        <div class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
            @if($options->showAuthors)
                @foreach($item->authors as $author)
                    <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="item-author">{{ $author->name }}</span>
                @endforeach
            @endif

            @if($options->showEffectiveDate && $effectiveDate)
                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="effective-date">{{ $effectiveDate }}</span>
            @endif

            @if($options->showDuration && $duration)
                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="duration">{{ $duration }}</span>
            @endif
        </div>

        @if($options->showCategories && $categories->isNotEmpty())
            <div class="flex flex-wrap gap-2" data-test="item-categories">
                @foreach($categories as $category)
                    <a
                        href="{{ \App\Filament\Public\Pages\BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public') }}"
                        class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($options->showTags && $tags->isNotEmpty())
            <div class="flex flex-wrap gap-2" data-test="item-tags">
                @foreach($tags as $tag)
                    <a
                        href="{{ \App\Filament\Public\Pages\BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public') }}"
                        class="rounded-md bg-gray-950 px-2 py-1 text-xs text-white hover:bg-primary-700 dark:bg-gray-100 dark:text-gray-950"
                    >
                        {{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</article>
