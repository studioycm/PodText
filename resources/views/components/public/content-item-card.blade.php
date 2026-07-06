@props([
    'item',
    'options' => \App\Support\PublicContent\PublicContentCardOptions::fromSettings(),
    'layout' => 'cards',
    'cardTemplate' => null,
])

@php
    $templateRenderer = app(\App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::class);
    $cardTemplate ??= $templateRenderer->resolve('content_item');
    $templateAttributes = $templateRenderer->compatibilityAttributes($cardTemplate);
    $presentation = $templateRenderer->contentItemPresentation($cardTemplate, $layout);
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
    $imageSize = $cardTemplate->imageSize;
    $groupCoverUrl = $item->contentGroup->cover_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->contentGroup->cover_path)
        : null;
    $imageUrl = $item->external_thumbnail_url ?: $groupCoverUrl;
    $imageSource = $item->external_thumbnail_url ? 'item' : ($groupCoverUrl ? 'group' : 'fallback');
    $imageFitClass = $options->imageFitClass();
    $imageRadiusClass = $options->imageRadiusClass();
    $titleText = $options->groupBadgeMode === 'combined_title'
        ? $item->contentGroup->title.$options->groupTitleSeparator.$item->title
        : $item->title;
@endphp

<article
    {{ $attributes->merge(['class' => $presentation['article']]) }}
    data-test="content-item-card"
    data-card-density="{{ $presentation['density'] }}"
    data-card-image-size="{{ $imageSize }}"
    data-card-image-fit="{{ $options->imageFit }}"
    data-card-image-radius="{{ $options->imageRadius }}"
    data-card-title-size="{{ $presentation['title_size'] }}"
    data-result-layout="{{ $presentation['layout'] }}"
    data-card-template-family="{{ $templateAttributes['data-card-template-family'] }}"
    data-card-template-key="{{ $templateAttributes['data-card-template-key'] }}"
    data-card-template-layout="{{ $templateAttributes['data-card-template-layout'] }}"
    data-card-template-parts="{{ $templateAttributes['data-card-template-parts'] }}"
    data-card-renderer-parts="{{ implode(',', $presentation['controlled_parts']) }}"
    data-card-title-clamp="{{ $presentation['title_clamp'] }}"
    data-card-description-clamp="{{ $presentation['description_clamp'] }}"
>
    @if($imageSize !== 'hidden')
        <a
            href="{{ $itemUrl }}"
            class="block min-w-0 overflow-hidden bg-gray-100 dark:bg-gray-800 {{ $presentation['image'] }} {{ $imageRadiusClass }}"
            aria-label="{{ $titleText }}"
            data-test="content-item-image"
            data-card-image-source="{{ $imageSource }}"
        >
            @if($imageUrl)
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    class="h-full w-full {{ $imageFitClass }}"
                    loading="lazy"
                >
            @else
                <div class="flex h-full min-h-24 w-full items-center justify-center bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ $item->effectiveTypeLabelSingular() }}
                </div>
            @endif
        </a>
    @endif

    <div class="{{ $presentation['body'] }}">
        <div class="min-w-0 space-y-2">
            @if($options->showGroupBadge && $options->groupBadgeMode !== 'combined_title')
                <x-public.content-group-badge
                    :group="$item->contentGroup"
                    :mode="$options->groupBadgeMode"
                    :main-image-source="$imageSource"
                    :allow-duplicate-thumbnail="$options->groupBadgeDuplicateThumbnail"
                />
            @endif

            <h3 class="{{ $presentation['title'] }}">
                <a href="{{ $itemUrl }}" data-test="content-item-title">
                    {{ $titleText }}
                </a>
            </h3>
        </div>

        @if($options->showDescription && $options->descriptionLines > 0 && filled($item->description_markdown))
            <p class="{{ $presentation['description'] }}" data-test="item-description">
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
