@props([
    'points' => [],
    'testid' => null,
    'stroke' => null,
])

@php
    $values = array_values(array_map('floatval', $points));
    $count = count($values);
    $min = $count > 0 ? min($values) : 0.0;
    $span = ($count > 0 ? max($values) : 0.0) - $min;
    $step = $count > 1 ? 100 / ($count - 1) : 0;

    // Time runs left to right even inside the RTL board.
    //
    // Normalised over max−min inside the [2, 22] band of the 24-high box —
    // against the peak alone, [8, 9, 10] rendered almost flat. A spanless
    // series (all equal, all zero, or a single point) sits on the midline
    // rather than hugging an edge.
    $polyline = collect($values)
        ->map(fn (float $value, int $index): string => sprintf(
            '%.2f,%.2f',
            $index * $step,
            $span > 0 ? 22 - (($value - $min) / $span) * 20 : 12,
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
            @if ($stroke) class="{{ $stroke }}" @endif
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    @endif
</svg>
