@props([
    'author',
    'fullPageUrl',
    'selected' => false,
    'selectable' => false,
    'cardTemplate' => null,
    'card' => null,
    'compact' => false,
    'previewMode' => false,
])

@php
    if ($card === null) {
        $templateRenderer = app(\App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::class);
        $cardTemplate ??= $templateRenderer->resolve('contributor');
        $card = app(\App\Support\PublicFront\Cards\PublicContributorCardPresenter::class)
            ->present($author, $fullPageUrl, $cardTemplate, $compact, $selected);
    }

    $templateAttributes = $card['template_attributes'];
    $presentation = $card['presentation'];
    $cardClasses = trim($presentation['article'].' '.$card['selected_classes']);
@endphp

@if($compact)
    @if($previewMode)
        <div
            {{ $attributes->merge(['class' => $cardClasses]) }}
            aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}"
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
                <x-public.contributor-card-part :part="$part" :presentation="$presentation" :compact="true" :preview-mode="true" />
            @endforeach
        </div>
    @else
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
                <x-public.contributor-card-part :part="$part" :presentation="$presentation" :compact="true" :preview-mode="false" />
            @endforeach
        </button>
    @endif
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
            <x-public.contributor-card-part :part="$part" :presentation="$presentation" :compact="false" :preview-mode="$previewMode" />
        @endforeach
    </div>

    @if($selectable && ! $previewMode)
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
