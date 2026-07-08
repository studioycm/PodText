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
                @switch($part['type'])
                    @case('entity_attribute')
                        <div
                            class="{{ $part['class'] }}"
                            data-test="{{ $part['test'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            @if($part['test'] === 'content-group-type-label')
                                <x-public.type-label :label="$part['text']" />
                            @else
                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    {{ $part['text'] }}
                                </span>
                            @endif
                        </div>
                        @break

                    @case('title')
                        <h2
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            <a href="{{ $part['url'] }}" data-test="content-group-title">
                                {{ $part['text'] }}
                            </a>
                        </h2>
                        @break

                    @case('description')
                        <p
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            {{ $part['text'] }}
                        </p>
                        @break

                    @case('metadata_row')
                        <div
                            class="{{ $part['class'] }}"
                            data-card-part="{{ $part['type'] }}"
                            data-card-part-source="{{ $part['source'] }}"
                            data-card-part-attribute="{{ $part['attribute'] }}"
                            data-card-part-order="{{ $part['order'] }}"
                        >
                            @foreach($part['badges'] as $badge)
                                <span data-test="{{ $badge['test'] }}">{{ $badge['label'] }}</span>
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
                                <a href="{{ $link['url'] }}" class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300">
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
                                data-test="content-group-action-link"
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
