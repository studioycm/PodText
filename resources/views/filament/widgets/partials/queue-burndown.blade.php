@props([
    'tiers' => [],
])

{{-- H7 · the queue's finish lines, one per tier. Inline elements only: this
     renders inside Filament's <p class="fi-ta-header-description">. --}}
<span class="inline-flex flex-col gap-1" data-testid="queue-burndown">
    @foreach ($tiers as $key => $tier)
        <span class="inline-flex flex-wrap items-center gap-2" data-testid="queue-burndown-{{ $key }}">
            <span class="font-medium">{{ $tier->description }}</span>

            <span
                class="inline-flex h-1.5 w-32 overflow-hidden rounded-full bg-gray-100 align-middle dark:bg-white/10"
                dir="ltr"
            >
                <span
                    class="{{ $tier->tier->barClass() }} inline-block h-1.5"
                    style="width: {{ $tier->percent() }}%"
                ></span>
            </span>

            @if ($tier->forecast)
                <span>
                    {{ __('admin.dashboard.gap.forecast', ['date' => $tier->forecast->forDisplay(\App\Support\UiFormats::date())]) }}
                </span>
            @endif
        </span>
    @endforeach
</span>
