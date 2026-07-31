@props([
    'points' => [],
    'testid' => null,
])

@php
    $values = array_values(array_map('floatval', $points));
    $count = count($values);
    $peak = $count > 0 ? max($values) : 0.0;
    $step = $count > 1 ? 100 / ($count - 1) : 0;

    // Time runs left to right even inside the RTL board.
    $polyline = collect($values)
        ->map(fn (float $value, int $index): string => sprintf(
            '%.2f,%.2f',
            $index * $step,
            $peak > 0 ? 24 - ($value / $peak) * 22 : 23,
        ))
        ->implode(' ');
@endphp

<svg
    dir="ltr"
    viewBox="0 0 100 24"
    preserveAspectRatio="none"
    class="h-6 w-full"
    aria-hidden="true"
    @if ($testid) data-testid="{{ $testid }}" @endif
>
    @if ($count > 1)
        <polyline
            points="{{ $polyline }}"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    @endif
</svg>
