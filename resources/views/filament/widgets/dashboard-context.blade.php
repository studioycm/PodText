<x-filament-widgets::widget>
    <div class="flex flex-wrap items-center justify-between gap-3" data-testid="dashboard-context">
        <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.context.legend') }}</span>
            <a
                href="{{ $indexUrl }}"
                class="rounded-full border border-gray-300 px-2.5 py-0.5 text-gray-600 dark:border-white/10 dark:text-gray-300"
            >
                ● {{ __('admin.dashboard.legend.draft') }} {{ $funnel['draft'] }}
            </a>
            <a
                href="{{ $indexUrl }}"
                class="bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300 rounded-full px-2.5 py-0.5"
            >
                ● {{ __('admin.dashboard.legend.published') }} {{ $funnel['published'] }}
            </a>
            <a
                href="{{ $indexUrl }}"
                class="bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300 rounded-full px-2.5 py-0.5"
            >
                ● {{ __('admin.dashboard.legend.transcribed') }} {{ $funnel['transcribed'] }}
            </a>
            <a
                href="{{ $indexUrl }}"
                class="bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300 rounded-full px-2.5 py-0.5"
            >
                ● {{ __('admin.dashboard.legend.visible') }} {{ $funnel['visible'] }}
            </a>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400" data-testid="dashboard-scope-echo">
            {{ __('admin.dashboard.context.showing') }}: {{ $lens->getLabel() }}
            @if ($range)
                · {{ $range->getLabel() }}
            @endif
            · {{ __('admin.dashboard.context.as_of', ['time' => $generatedAt]) }}
        </p>
    </div>
</x-filament-widgets::widget>
