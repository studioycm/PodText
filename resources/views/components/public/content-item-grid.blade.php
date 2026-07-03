@props([
    'items',
    'cardOptions',
    'layout' => 'cards',
])

@php
    $gridClasses = $layout === 'rows'
        ? 'grid grid-cols-1 gap-4'
        : 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3';
@endphp

<div
    {{ $attributes->merge(['class' => $gridClasses]) }}
    data-test="content-item-grid"
    data-result-layout="{{ $layout }}"
>
    @foreach($items as $item)
        <x-public.content-item-card
            :item="$item"
            :options="$cardOptions"
            :layout="$layout"
            wire:key="content-item-card-{{ $item->id }}"
        />
    @endforeach
</div>
