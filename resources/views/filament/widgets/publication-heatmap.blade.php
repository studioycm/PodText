<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.heatmap.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag', ['flow' => true])
        </div>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>

        {{-- Time axis runs left to right inside the RTL board. --}}
        <div class="mt-4 flex flex-wrap gap-1" dir="ltr">
            @foreach ($days as $day)
                <button
                    type="button"
                    wire:click="selectDay('{{ $day['day'] }}')"
                    title="{{ __('admin.dashboard.heatmap.day', ['count' => $day['count'], 'date' => $day['label']]) }}"
                    @class([
                        'size-5 rounded-sm transition',
                        'bg-gray-100 dark:bg-white/5' => $day['level'] === 0,
                        'bg-primary-200 dark:bg-primary-500/30' => $day['level'] === 1,
                        'bg-primary-300 dark:bg-primary-500/50' => $day['level'] === 2,
                        'bg-primary-400 dark:bg-primary-500/70' => $day['level'] === 3,
                        'bg-primary-600 dark:bg-primary-400' => $day['level'] >= 4,
                        'ring-primary-600 dark:ring-primary-400 ring-2 ring-offset-1' => $selectedDay === $day['day'],
                    ])
                    data-testid="heatmap-day-{{ $day['day'] }}"
                >
                    <span class="sr-only">
                        {{ __('admin.dashboard.heatmap.day', ['count' => $day['count'], 'date' => $day['label']]) }}
                    </span>
                </button>
            @endforeach
        </div>

        @if ($selectedDay)
            <x-filament::link
                tag="button"
                size="sm"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
                wire:click="selectDay(null)"
                class="mt-3"
            >
                {{ __('admin.dashboard.heatmap.clear') }}
            </x-filament::link>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
