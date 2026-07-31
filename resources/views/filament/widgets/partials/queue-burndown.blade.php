@props([
    'remaining' => 0,
    'total' => 0,
    'forecast' => null,
])

@php
    $cleared = max(0, $total - $remaining);
    $percent = $total > 0 ? round(($cleared / $total) * 100) : 100;
@endphp

{{-- H7 · the queue's finish line. Inline elements only: this renders inside
     Filament's <p class="fi-ta-header-description">. --}}
<span class="inline-flex flex-wrap items-center gap-2" data-testid="queue-burndown">
    <span class="font-medium">
        {{ __('admin.dashboard.queue.burndown', ['remaining' => $remaining, 'total' => $total]) }}
    </span>

    <span
        class="inline-flex h-1.5 w-32 overflow-hidden rounded-full bg-gray-100 align-middle dark:bg-white/10"
        dir="ltr"
    >
        <span class="bg-success-500 inline-block h-1.5" style="width: {{ $percent }}%"></span>
    </span>

    @if ($forecast)
        <span>{{ __('admin.dashboard.gap.forecast', ['date' => $forecast]) }}</span>
    @endif
</span>
