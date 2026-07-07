@props([
    'author',
    'fullPageUrl',
    'selected' => false,
    'selectable' => false,
    'cardTemplate' => null,
    'compact' => false,
])

@php
    $templateRenderer = app(\App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::class);
    $cardTemplate ??= $templateRenderer->resolve('contributor');
    $card = app(\App\Support\PublicFront\Cards\PublicContributorCardPresenter::class)
        ->present($author, $fullPageUrl, $cardTemplate, $compact, $selected);
    $templateAttributes = $card['template_attributes'];
    $presentation = $card['presentation'];
    $cardClasses = trim($presentation['article'].' '.$card['selected_classes']);
@endphp

@if($compact)
    <button
        type="button"
        wire:click="selectContributor({{ $author->id }})"
        {{ $attributes->merge(['class' => $cardClasses]) }}
        data-test="contributor-card"
        data-contributor-id="{{ $author->id }}"
        data-card-density="{{ $presentation['density'] }}"
        data-card-image-size="{{ $presentation['image_size'] }}"
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
        @foreach($card['body_parts'] as $part)
            @switch($part['type'])
                @case('image')
                    <div
                        class="{{ $part['class'] }}"
                        data-card-part="{{ $part['type'] }}"
                        data-card-part-source="{{ $part['source'] }}"
                        data-card-part-attribute="{{ $part['attribute'] }}"
                        data-card-part-order="{{ $part['order'] }}"
                    >
                        <div class="{{ $presentation['avatar'] }}">{{ $part['initial'] }}</div>
                    </div>
                    @break

                @case('title')
                    <span
                        class="{{ $part['class'] }}"
                        data-test="contributor-name"
                        data-card-part="{{ $part['type'] }}"
                        data-card-part-source="{{ $part['source'] }}"
                        data-card-part-attribute="{{ $part['attribute'] }}"
                        data-card-part-order="{{ $part['order'] }}"
                    >
                        {{ $part['text'] }}
                    </span>
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
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-800 dark:bg-primary-950 dark:text-primary-100"
                                title="{{ $badge['title'] ?? $badge['label'] }}"
                                data-test="{{ $badge['test'] }}"
                            >
                                {{ $badge['label'] }}
                            </span>
                        @endforeach
                    </div>
                    @break

                @case('entity_attribute')
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
    </button>
@else
<article
    {{ $attributes->merge(['class' => $cardClasses]) }}
    data-test="contributor-card"
    data-contributor-id="{{ $author->id }}"
    data-card-density="{{ $presentation['density'] }}"
    data-card-image-size="{{ $presentation['image_size'] }}"
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
    <div class="{{ $presentation['body'] }}">
        @foreach($card['body_parts'] as $part)
            @switch($part['type'])
                @case('image')
                    <div
                        class="{{ $part['class'] }}"
                        data-card-part="{{ $part['type'] }}"
                        data-card-part-source="{{ $part['source'] }}"
                        data-card-part-attribute="{{ $part['attribute'] }}"
                        data-card-part-order="{{ $part['order'] }}"
                    >
                        <div class="{{ $presentation['avatar'] }}">{{ $part['initial'] }}</div>
                    </div>
                    @break

                @case('title')
                    <div
                        @class(['flex items-start gap-3' => $part['show_avatar']])
                        data-card-part="{{ $part['type'] }}"
                        data-card-part-source="{{ $part['source'] }}"
                        data-card-part-attribute="{{ $part['attribute'] }}"
                        data-card-part-order="{{ $part['order'] }}"
                    >
                        @if($part['show_avatar'])
                            <div class="{{ $presentation['avatar'] }}">{{ $part['initial'] }}</div>
                        @endif

                        <h3 class="{{ $part['class'] }}" data-test="contributor-name">
                            @if($part['url'])
                                <a href="{{ $part['url'] }}">{{ $part['text'] }}</a>
                            @else
                                {{ $part['text'] }}
                            @endif
                        </h3>
                    </div>
                    @break

                @case('description')
                    <p
                        class="{{ $part['class'] }}"
                        data-test="contributor-bio-preview"
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
                            <span
                                class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800"
                                title="{{ $badge['title'] ?? $badge['label'] }}"
                                data-test="{{ $badge['test'] }}"
                            >
                                {{ $badge['label'] }}
                            </span>
                        @endforeach
                    </div>
                    @break

                @case('entity_attribute')
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
                            data-test="contributor-link"
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

    @if($selectable)
        <div class="mt-auto flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="selectContributor({{ $author->id }})"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                data-test="select-contributor"
            >
                {{ __('public.actions.preview_contributor') }}
            </button>
        </div>
    @endif
</article>
@endif
