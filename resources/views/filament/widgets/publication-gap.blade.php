@php
    $barColour = fn (?string $color): string => match ($color) {
        'danger' => 'bg-danger-500',
        'warning' => 'bg-warning-500',
        'violet' => 'bg-violet-500',
        default => 'bg-info-500',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.dashboard.gap.heading') }}</h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-testid="gap-rate">
            {{ __('admin.dashboard.gap.rate', ['percent' => $rate->percent()]) }}
        </p>

        <div class="mt-4 flex h-6 overflow-hidden rounded-lg text-xs font-semibold" dir="rtl" data-testid="gap-bar">
            @if ($visible > 0)
                <div
                    class="bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300 flex items-center justify-center"
                    style="flex: {{ $visible }}"
                >
                    {{ __('admin.dashboard.legend.visible') }} {{ $visible }}
                </div>
            @endif
            @if ($invisible > 0)
                <div
                    class="bg-danger-100 text-danger-800 dark:bg-danger-500/20 dark:text-danger-300 flex items-center justify-center"
                    style="flex: {{ $invisible }}"
                >
                    {{ __('admin.dashboard.gap.invisible') }} {{ $invisible }}
                </div>
            @endif
            @if ($visible === 0 && $invisible === 0)
                <div class="flex w-full items-center justify-center bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    {{ __('admin.dashboard.gap.nothing_published') }}
                </div>
            @endif
        </div>

        {{-- Tier 1 · the public cannot see these at all. --}}
        <h3 class="mt-5 text-xs font-semibold text-gray-700 dark:text-gray-200">
            {{ __('admin.dashboard.gap.invisible_heading') }}
        </h3>
        <dl class="mt-2 space-y-2 text-sm">
            @foreach ($gapReasons as $reason)
                <div class="flex items-center gap-3" data-testid="gap-reason">
                    <dt class="w-44 shrink-0 text-gray-600 dark:text-gray-300">
                        <a href="{{ $reason->url }}" class="hover:underline">{{ $reason->label }}</a>
                    </dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" dir="ltr">
                            @if ($reason->value > 0)
                                <div
                                    class="{{ $barColour($reason->color) }} h-2.5 rounded-full"
                                    style="width: {{ min(100, $reason->value * 10) }}%"
                                ></div>
                            @endif
                        </div>
                        <span class="w-6 text-end font-semibold tabular-nums">{{ (int) $reason->value }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>

        {{-- Tier 2 · publicly visible, but incomplete. Never called invisible. --}}
        <h3 class="mt-5 text-xs font-semibold text-gray-700 dark:text-gray-200">
            {{ __('admin.dashboard.gap.attention_heading', ['count' => $attention]) }}
        </h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.gap.attention_hint') }}</p>
        <dl class="mt-2 space-y-2 text-sm">
            @foreach ($attentionReasons as $reason)
                <div class="flex items-center gap-3" data-testid="attention-reason">
                    <dt class="w-44 shrink-0 text-gray-600 dark:text-gray-300">
                        <a href="{{ $reason->url }}" class="hover:underline">{{ $reason->label }}</a>
                    </dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" dir="ltr">
                            @if ($reason->value > 0)
                                <div
                                    class="{{ $barColour($reason->color) }} h-2.5 rounded-full"
                                    style="width: {{ min(100, $reason->value * 10) }}%"
                                ></div>
                            @endif
                        </div>
                        <span class="w-6 text-end font-semibold tabular-nums">{{ (int) $reason->value }}</span>
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
