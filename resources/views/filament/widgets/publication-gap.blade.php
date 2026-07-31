<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.dashboard.gap.heading') }}</h2>
            <span class="rounded-full border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                {{ __('admin.dashboard.tags.stock') }}
            </span>
        </div>

        <div class="mt-4 flex h-6 overflow-hidden rounded-lg text-xs font-semibold" dir="rtl" data-testid="gap-bar">
            @if ($visible > 0)
                <div
                    class="bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300 flex items-center justify-center"
                    style="flex: {{ $visible }}"
                >
                    {{ __('admin.dashboard.legend.visible') }} {{ $visible }}
                </div>
            @endif
            @if ($blocked > 0)
                <div
                    class="bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300 flex items-center justify-center"
                    style="flex: {{ $blocked }}"
                >
                    {{ __('admin.dashboard.gap.blocked') }} {{ $blocked }}
                </div>
            @endif
            @if ($visible === 0 && $blocked === 0)
                <div class="flex w-full items-center justify-center bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    {{ __('admin.dashboard.gap.nothing_published') }}
                </div>
            @endif
        </div>

        <dl class="mt-4 space-y-2 text-sm">
            @foreach ($reasons as $reason => $data)
                <div class="flex items-center gap-3" data-testid="gap-reason-{{ $reason }}">
                    <dt class="w-44 shrink-0 text-gray-600 dark:text-gray-300">
                        {{ __("admin.dashboard.reasons.{$reason}") }}
                    </dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" dir="ltr">
                            @if ($data['count'] > 0)
                                <div
                                    class="bg-{{ $data['color'] }}-500 h-2.5 rounded-full"
                                    style="width: {{ min(100, $data['count'] * 10) }}%"
                                ></div>
                            @endif
                        </div>
                        <span class="w-6 text-end font-semibold tabular-nums">{{ $data['count'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($forecast)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" data-testid="gap-forecast">
                {{ __('admin.dashboard.gap.forecast', ['date' => $forecast]) }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
