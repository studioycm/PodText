@php
    /** @var \App\Models\ContentItem $record */
    $record = $getRecord();
    $cardOptions ??= app(\App\Support\PublicFront\PublicFrontRenderContext::class)->cardOptions();
    $cardTemplate = app(\App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::class)->resolve('content_item');
    $card = app(\App\Support\PublicFront\Cards\PublicContentItemCardPresenter::class)
        ->present($record, $cardOptions, $cardTemplate);
@endphp

<x-public.content-item-card :card="$card" :options="$cardOptions" :card-template="$cardTemplate" />
