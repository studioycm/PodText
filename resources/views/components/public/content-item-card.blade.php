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
                @switch($part['type'])
                    @case('group_identity')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            <x-public.content-group-badge
                                :group="$part['group']"
                                :mode="$part['mode']"
                                :main-image-source="$part['main_image_source']"
                                :allow-duplicate-thumbnail="$part['allow_duplicate_thumbnail']"
                            />
                        </div>
                        @break

                    @case('title')
                        <h3
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            <a href="{{ $part['url'] }}" data-test="content-item-title">
                                {{ $part['text'] }}
                            </a>
                        </h3>
                        @break

                    @case('description')
                        <p
                            class="{{ $part['class'] }}"
                            data-test="item-description"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            {{ $part['text'] }}
                        </p>
                        @break

                    @case('transcriber_line')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            @foreach($part['badges'] as $badge)
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="item-transcriber">{{ $badge['label'] }}</span>
                            @endforeach
                        </div>
                        @break

                    @case('date_read_time')
                    @case('metadata_row')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            @foreach($part['badges'] as $badge)
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="{{ $badge['test'] }}">{{ $badge['label'] }}</span>
                            @endforeach
                        </div>
                        @break

                    @case('taxonomy')
                        <div
                            class="{{ $part['class'] }}"
                            data-test="{{ $part['test'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            @foreach($part['links'] as $link)
                                <a href="{{ $link['url'] }}" class="{{ $part['link_class'] }}">
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                        @break

                    @case('action_link')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            <a
                                href="{{ $part['url'] }}"
                                @if($part['target']) target="{{ $part['target'] }}" rel="noopener noreferrer" @endif
                                class="inline-flex text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                                data-test="content-item-action-link"
                            >
                                {{ $part['text'] }}
                            </a>
                        </div>
                        @break

                    @case('custom_text')
                        <p
                            class="{{ $part['class'] }}"
                            data-test="card-custom-text"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            {{ $part['text'] }}
                        </p>
                        @break

                    @case('divider')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        ></div>
                        @break

                    @case('spacer')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        ></div>
                        @break
                @endswitch
            @endforeach
        </div>
    @endif
</article>
