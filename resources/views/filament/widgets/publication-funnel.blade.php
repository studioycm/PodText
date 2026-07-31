<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.funnel.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stages as $stage => $data)
                <div
                    @class([
                        'rounded-xl border p-3',
                        'border-primary-500 dark:border-primary-400' => $data['active'],
                        'border-gray-200 dark:border-white/10' => ! $data['active'],
                    ])
                    data-testid="funnel-segment-{{ $stage }}"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <a
                            href="{{ $data['url'] }}"
                            class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-300"
                        >
                            {{ $data['stage']->getLabel() }}
                        </a>
                        <span class="text-xl font-semibold text-gray-950 tabular-nums dark:text-white">
                            {{ number_format($data['count']) }}
                        </span>
                    </div>

                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" dir="ltr">
                        <div
                            class="{{ $data['bar'] }} h-1.5 rounded-full"
                            style="width: {{ round(($data['count'] / $total) * 100, 2) }}%"
                        ></div>
                    </div>

                    <div class="text-gray-400 dark:text-gray-500">
                        @include('filament.widgets.partials.sparkline', [
                            'points' => $data['series'],
                            'testid' => "funnel-spark-{$stage}",
                        ])
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.dashboard.funnel.movement', ['count' => (int) $data['row']->value]) }}
                        <span
                            @class([
                                'font-medium tabular-nums',
                                'text-success-600 dark:text-success-400' => $data['delta'] > 0,
                                'text-danger-600 dark:text-danger-400' => $data['delta'] < 0,
                            ])
                            dir="ltr"
                        >{{ $data['delta'] > 0 ? '+' : '' }}{{ $data['delta'] }}</span>
                    </p>

                    <button
                        type="button"
                        wire:click="selectStage('{{ $stage }}')"
                        class="text-primary-600 dark:text-primary-400 mt-1 text-xs hover:underline"
                    >
                        {{ __('admin.dashboard.funnel.focus') }}
                    </button>
                </div>
            @endforeach
        </div>

        @if ($gap > 0)
            <button
                type="button"
                wire:click="openBlockers"
                class="{{ \App\Enums\DashboardTier::Invisible->bandClass() }} mt-4 w-full rounded-lg px-3 py-2 text-start text-xs font-medium hover:underline"
                data-testid="funnel-gap"
            >
                {{ __('admin.dashboard.funnel.gap', ['count' => $gap]) }}
            </button>
        @else
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" data-testid="funnel-gap">
                {{ __('admin.dashboard.funnel.no_gap') }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
