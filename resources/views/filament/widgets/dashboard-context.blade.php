<x-filament-widgets::widget>
    <div class="flex flex-wrap items-center justify-between gap-3" data-testid="dashboard-context">
        <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.context.legend') }}</span>

            @foreach ($stages as $stage)
                <button
                    type="button"
                    wire:click="selectStatus('{{ $stage->value }}')"
                    @class([
                        'rounded-full px-2.5 py-0.5',
                        $stage->chipClass(),
                        'ring-primary-600 dark:ring-primary-400 ring-2' => $status === $stage->value,
                    ])
                    data-testid="legend-chip-{{ $stage->value }}"
                >
                    ● {{ $stage->getLabel() }} {{ $funnel[$stage->value] }}
                </button>
            @endforeach
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400" data-testid="dashboard-scope-echo">
            {{ __('admin.dashboard.context.showing') }}: {{ $lens->getLabel() }}
            @if ($range)
                · {{ $range->getLabel() }}
            @endif
            · {{ $podcast ?? __('admin.dashboard.filters.all_podcasts') }}
            @if ($status)
                · {{ __('admin.dashboard.context.status_scope', ['status' => \App\Enums\FunnelStage::from($status)->getLabel()]) }}
            @endif
            · {{ __('admin.dashboard.context.as_of', ['time' => $generatedAt]) }}
        </p>
    </div>
</x-filament-widgets::widget>
