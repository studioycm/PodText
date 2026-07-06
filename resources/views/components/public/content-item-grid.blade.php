@props([
    'items',
    'cardOptions',
    'layout' => 'cards',
    'columns' => 3,
    'gap' => 'comfortable',
    'cardTemplate' => null,
])

@php
    $resolvedLayout = $cardTemplate?->imageSize === 'large'
        ? 'cards'
        : ($cardTemplate?->layout === 'rows' ? 'rows' : $layout);
    $resolvedColumns = max(1, min(4, (int) $columns));
    $gapClass = match ($gap) {
        'compact' => 'gap-3',
        'spacious' => 'gap-6',
        default => 'gap-4',
    };
    $cardGridClasses = match ($resolvedColumns) {
        1 => 'grid grid-cols-1',
        2 => 'grid grid-cols-1 md:grid-cols-2',
        4 => 'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4',
        default => 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
    };
    $gridClasses = $resolvedLayout === 'rows'
        ? "grid grid-cols-1 {$gapClass}"
        : "{$cardGridClasses} {$gapClass}";
@endphp

<div
    {{ $attributes->merge(['class' => $gridClasses]) }}
    data-test="content-item-grid"
    data-result-layout="{{ $resolvedLayout }}"
    data-grid-columns="{{ $resolvedColumns }}"
    data-grid-gap="{{ $gap }}"
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
