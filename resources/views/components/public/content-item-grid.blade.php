@props([
    'items',
    'cardOptions',
    'layout' => 'cards',
    'cardTemplate' => null,
])

@php
    $resolvedLayout = $cardTemplate?->imageSize === 'large'
        ? 'cards'
        : ($cardTemplate?->layout === 'rows' ? 'rows' : $layout);
    $gridClasses = $resolvedLayout === 'rows'
        ? 'grid grid-cols-1 gap-4'
        : 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3';
@endphp

<div
    {{ $attributes->merge(['class' => $gridClasses]) }}
    data-test="content-item-grid"
    data-result-layout="{{ $resolvedLayout }}"
>
    @foreach($items as $item)
        <x-public.content-item-card
            :item="$item"
            :options="$cardOptions"
            :layout="$resolvedLayout"
            :card-template="$cardTemplate"
            wire:key="content-item-card-{{ $item->id }}"
        />
    @endforeach
</div>
