@props([
    'card',
    'options',
    'cardTemplate' => null,
])

@php
    $templateAttributes = $card['template_attributes'];
    $presentation = $card['presentation'];
    $imageSize = $cardTemplate->imageSize;
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
    @foreach($card['media_parts'] as $part)
        <a
            href="{{ $part['url'] }}"
            class="block min-w-0 overflow-hidden bg-gray-100 dark:bg-gray-800 {{ $presentation['image'] }} {{ $part['image']['radius_class'] }}"
            aria-label="{{ $part['title'] }}"
            data-test="content-item-image"
            data-card-part="{{ $part['type'] }}"
            data-card-part-source="{{ $part['source'] }}"
            data-card-part-attribute="{{ $part['attribute'] }}"
            data-card-part-order="{{ $part['order'] }}"
            data-card-image-source="{{ $part['image']['source'] }}"
        >
            @if($part['image']['url'])
                <img
                    src="{{ $part['image']['url'] }}"
                    alt=""
                    class="h-full w-full {{ $part['image']['fit_class'] }}"
                    loading="lazy"
                >
            @else
                <div class="flex h-full min-h-24 w-full items-center justify-center bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ $part['type_label'] }}
                </div>
            @endif
        </a>
    @endforeach

    @if($card['body_parts'] !== [])
        <div class="{{ $presentation['body'] }}">
            @foreach($card['body_parts'] as $part)
                <x-public.content-item-card-part :part="$part" />
            @endforeach
        </div>
    @endif
</article>
