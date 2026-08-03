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
                    class="{{ \App\Enums\FunnelStage::Visible->bandClass() }} flex items-center justify-center"
                    style="flex: {{ $visible }}"
                >
                    {{ __('admin.dashboard.legend.visible') }} {{ $visible }}
                </div>
            @endif
            @if ($invisible > 0)
                <div
                    class="{{ \App\Enums\DashboardTier::Invisible->bandClass() }} flex items-center justify-center"
                    style="flex: {{ $invisible }}"
                >
                    {{ \App\Enums\DashboardTier::Invisible->getLabel() }} {{ $invisible }}
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
            {{ \App\Enums\DashboardTier::Invisible->getLabel() }}
        </h3>
        <dl class="mt-2 space-y-2 text-sm">
            @foreach ($gapReasons as $reason)
                <div class="flex items-center gap-3" data-testid="gap-reason">
                    <dt class="w-44 shrink-0">
                        <x-filament::link
                            tag="button"
                            size="sm"
                            color="gray"
                            :icon="\App\Enums\DashboardReason::from($reason->meta('reason'))->getIcon()"
                            wire:click="selectReason('{{ $reason->meta('reason') }}')"
                        >
                            {{ $reason->label }}
                        </x-filament::link>
                    </dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" dir="ltr">
                            @if ($reason->value > 0)
                                <div
                                    class="{{ $reason->meta('bar', 'bg-info-500') }} h-2.5 rounded-full"
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
            {{ \App\Enums\DashboardTier::Attention->getLabel() }} ({{ $attention }})
        </h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ \App\Enums\DashboardTier::Attention->getDescription() }}
        </p>
        <dl class="mt-2 space-y-2 text-sm">
            @foreach ($attentionReasons as $reason)
                <div class="flex items-center gap-3" data-testid="attention-reason">
                    <dt class="w-44 shrink-0">
                        <x-filament::link
                            tag="button"
                            size="sm"
                            color="gray"
                            :icon="\App\Enums\DashboardReason::from($reason->meta('reason'))->getIcon()"
                            wire:click="selectReason('{{ $reason->meta('reason') }}')"
                        >
                            {{ $reason->label }}
                        </x-filament::link>
                    </dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" dir="ltr">
                            @if ($reason->value > 0)
                                <div
                                    class="{{ $reason->meta('bar', 'bg-info-500') }} h-2.5 rounded-full"
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
