@props([
    'tiers' => [],
])

{{-- H7 · the queue's finish lines, one per tier. Inline elements only: this
     renders inside Filament's <p class="fi-ta-header-description">. --}}
<span class="inline-flex flex-col gap-1" data-testid="queue-burndown">
    @foreach ($tiers as $key => $tier)
        @php
            $data = $tier['data'];
            $percent = $data->goalValue > 0 ? round(($data->currentValue / $data->goalValue) * 100) : 100;
        @endphp

        <span class="inline-flex flex-wrap items-center gap-2" data-testid="queue-burndown-{{ $key }}">
            <span class="font-medium">{{ $data->description }}</span>

            <span
                class="inline-flex h-1.5 w-32 overflow-hidden rounded-full bg-gray-100 align-middle dark:bg-white/10"
                dir="ltr"
            >
                <span
                    @class([
                        'inline-block h-1.5',
                        'bg-success-500' => $key === 'invisible',
                        'bg-warning-500' => $key !== 'invisible',
                    ])
                    style="width: {{ $percent }}%"
                ></span>
            </span>

            @if ($data->projectionLabel)
                <span>{{ __('admin.dashboard.gap.forecast', ['date' => $data->projectionLabel]) }}</span>
            @endif
        </span>
    @endforeach
</span>
