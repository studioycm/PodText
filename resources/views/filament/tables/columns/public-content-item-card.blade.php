@php
    /** @var \App\Models\ContentItem $record */
    $record = $getRecord();
    $cardOptions ??= \App\Support\PublicContent\PublicContentCardOptions::fromSettings();
@endphp

<x-public.content-item-card :item="$record" :options="$cardOptions" />
