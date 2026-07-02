@php
    /** @var \App\Models\ContentItem $record */
    $record = $getRecord();
    $cardOptions ??= \App\Support\PublicContent\PublicContentCardOptions::fromSettings();
    $itemUrl = \App\Filament\Public\Pages\ShowContentItem::getUrl([
        'contentGroupSlug' => $record->contentGroup->slug,
        'contentItemSlug' => $record->slug,
    ], panel: 'public');
    $groupUrl = \App\Filament\Public\Pages\ShowContentGroup::getUrl([
        'contentGroupSlug' => $record->contentGroup->slug,
    ], panel: 'public');
    $effectiveTranscription = $record->effectiveTranscription();
    $effectiveDate = $effectiveTranscription?->published_at?->timezone('Asia/Jerusalem')->format('d/m/Y');
    $categories = $record->effectiveCategories()
        ->where('is_visible', true)
        ->values();
    $tags = $record->relationLoaded('enabledContentTags') ? $record->enabledContentTags : $record->publicTags();
    $duration = $record->duration_seconds
        ? gmdate($record->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $record->duration_seconds)
        : null;
@endphp

<article
    class="flex h-full flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 {{ $cardOptions->cardPaddingClass() }}"
    data-test="content-item-card"
    data-card-density="{{ $cardOptions->density }}"
    data-card-image-size="{{ $cardOptions->imageSize }}"
    data-card-title-size="{{ $cardOptions->titleSize }}"
>
    @if($cardOptions->imageSize !== 'hidden')
        <a
            href="{{ $itemUrl }}"
            class="block overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800 {{ $cardOptions->imageClass() }}"
            aria-label="{{ $record->title }}"
        >
            @if($record->external_thumbnail_url)
                <img
                    src="{{ $record->external_thumbnail_url }}"
                    alt=""
                    class="h-full w-full object-cover"
                    loading="lazy"
                >
            @else
                <div class="flex h-full w-full items-center justify-center bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ $record->effectiveTypeLabelSingular() }}
                </div>
            @endif
        </a>
    @endif

    <div class="flex flex-1 flex-col gap-3">
        <div class="space-y-2">
            @if($cardOptions->showGroupBadge)
                <a
                    href="{{ $groupUrl }}"
                    class="inline-flex max-w-full items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950 dark:text-primary-200 dark:ring-primary-800"
                    data-test="group-badge"
                >
                    <span class="truncate">{{ $record->contentGroup->title }}</span>
                </a>
            @endif

            <h3 class="font-semibold leading-7 text-gray-950 dark:text-white {{ $cardOptions->titleClass() }}">
                <a href="{{ $itemUrl }}" data-test="content-item-title">
                    {{ $record->title }}
                </a>
            </h3>
        </div>

        @if($cardOptions->showDescription && $cardOptions->descriptionLines > 0 && filled($record->description_markdown))
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300 {{ $cardOptions->descriptionClass() }}" data-test="item-description">
                {{ str($record->description_markdown)->stripTags()->squish() }}
            </p>
        @endif

        <div class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
            @if($cardOptions->showAuthors)
                @foreach($record->authors as $author)
                    <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="item-author">{{ $author->name }}</span>
                @endforeach
            @endif

            @if($cardOptions->showEffectiveDate && $effectiveDate)
                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="effective-date">{{ $effectiveDate }}</span>
            @endif

            @if($cardOptions->showDuration && $duration)
                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="duration">{{ $duration }}</span>
            @endif
        </div>

        @if($cardOptions->showCategories && $categories->isNotEmpty())
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

        @if($cardOptions->showTags && $tags->isNotEmpty())
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
