@props([
    'part',
])

@php
    $label = $part['label'] ?? null;
    $labelPosition = $part['label_position'] ?? 'hidden';
    $labelAlignment = $part['label_alignment'] ?? 'start';
    $iconKey = $part['icon'] ?? null;
    $iconPosition = $part['icon_position'] ?? 'hidden';
    $icon = \App\Support\PublicFront\Cards\PublicFrontCardIconResolver::resolve($iconKey);
    $hasLabel = filled($label) && $labelPosition !== 'hidden';
    $hasIcon = $icon !== null && in_array($iconPosition, ['inline_before', 'inline_after'], true);
    $labelClass = match ($labelAlignment) {
        'center' => 'text-center',
        'end' => 'text-end',
        default => 'text-start',
    };
    $inlineClass = match ($labelAlignment) {
        'center' => 'justify-center',
        'end' => 'justify-end',
        'between' => 'justify-between',
        default => 'justify-start',
    };
@endphp

<div
    {{ $attributes->class('min-w-0') }}
    data-card-part="{{ $part['type'] }}"
    data-card-part-source="{{ $part['source'] }}"
    data-card-part-attribute="{{ $part['attribute'] }}"
    data-card-part-order="{{ $part['order'] }}"
    data-card-part-label-position="{{ $labelPosition }}"
    data-card-part-label-alignment="{{ $labelAlignment }}"
    data-card-part-icon="{{ $iconKey ?? 'none' }}"
    data-card-part-icon-position="{{ $iconPosition }}"
>
    @if($hasLabel && $labelPosition === 'above')
        <span class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400 {{ $labelClass }}" data-card-part-label>
            {{ $label }}
        </span>
    @endif

    <div class="flex min-w-0 w-full items-center gap-1.5 {{ $inlineClass }}">
        @if($hasIcon && $iconPosition === 'inline_before')
            <x-filament::icon :icon="$icon" class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" data-card-part-icon-graphic />
        @endif

        @if($hasLabel && $labelPosition === 'inline_before')
            <span class="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400" data-card-part-label>
                {{ $label }}
            </span>
        @endif

        <div class="min-w-0 w-full">
            {{ $slot }}
        </div>

        @if($hasLabel && $labelPosition === 'inline_after')
            <span class="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400" data-card-part-label>
                {{ $label }}
            </span>
        @endif

        @if($hasIcon && $iconPosition === 'inline_after')
            <x-filament::icon :icon="$icon" class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" data-card-part-icon-graphic />
        @endif
    </div>

    @if($hasLabel && $labelPosition === 'below')
        <span class="mt-1 block text-xs font-medium text-gray-500 dark:text-gray-400 {{ $labelClass }}" data-card-part-label>
            {{ $label }}
        </span>
    @endif
</div>
