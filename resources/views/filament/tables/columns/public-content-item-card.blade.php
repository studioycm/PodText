@php
    /** @var \App\Models\ContentItem $record */
    $record = $getRecord();
    $cardOptions ??= app(\App\Support\PublicFront\PublicFrontRenderContext::class)->cardOptions();
@endphp

<x-public.content-item-card :item="$record" :options="$cardOptions" />
