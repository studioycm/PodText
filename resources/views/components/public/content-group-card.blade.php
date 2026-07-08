@props([
    'card',
    'cardTemplate' => null,
])

@php
    $templateAttributes = $card['template_attributes'];
    $presentation = $card['presentation'];
@endphp

<article
    {{ $attributes->merge(['class' => $presentation['article']]) }}
    data-test="content-group-card"
    data-card-density="{{ $presentation['density'] }}"
    data-card-image-size="{{ $presentation['image_size'] }}"
    data-card-image-fit="{{ $card['image']['fit'] }}"
    data-card-image-radius="{{ $card['image']['radius'] }}"
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
            class="block min-w-0 overflow-hidden bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-gray-800 {{ $presentation['image'] }} {{ $part['image']['radius_class'] }}"
            aria-label="{{ $part['title'] }}"
            data-card-part="{{ $part['type'] }}"
            data-card-part-source="{{ $part['source'] }}"
            data-card-part-attribute="{{ $part['attribute'] }}"
            data-card-part-order="{{ $part['order'] }}"
        >
            @if($part['image']['url'])
                <img
                    src="{{ $part['image']['url'] }}"
                    alt=""
                    class="h-full w-full {{ $part['image']['fit_class'] }}"
                    loading="lazy"
                    data-test="content-group-image"
                >
            @else
                <div class="flex h-full min-h-24 w-full items-center justify-center bg-gray-100 text-2xl font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-300" data-test="content-group-fallback">
                    {{ $part['image']['initials'] }}
                </div>
            @endif
        </a>
    @endforeach

    @if($card['body_parts'] !== [])
        <div class="{{ $presentation['body'] }}">
            @foreach($card['body_parts'] as $part)
                <x-public.content-group-card-part :part="$part" />
            @endforeach
        </div>
    @endif
</article>
