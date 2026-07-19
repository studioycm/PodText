@props([
    'card',
    'options',
    'cardTemplate' => null,
    'previewMode' => false,
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
    data-card-part-flow="{{ $presentation['ordered_stack'] ? 'ordered-stack' : 'media-leading' }}"
>
    @if($presentation['ordered_stack'])
        <div class="{{ $presentation['body'] }}">
            @foreach($card['parts'] as $part)
                <x-public.content-item-card-part :part="$part" :presentation="$presentation" :preview-mode="$previewMode" />
            @endforeach
        </div>
    @else
        @foreach($card['media_parts'] as $part)
            <x-public.content-item-card-image-part :part="$part" :presentation="$presentation" :preview-mode="$previewMode" />
        @endforeach

        @if($card['body_parts'] !== [])
            <div class="{{ $presentation['body'] }}">
                @foreach($card['body_parts'] as $part)
                    <x-public.content-item-card-part :part="$part" :presentation="$presentation" :preview-mode="$previewMode" />
                @endforeach
            </div>
        @endif
    @endif
</article>
