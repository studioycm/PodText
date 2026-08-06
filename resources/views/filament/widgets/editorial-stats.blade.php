<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.stats.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($cards as $card)
                @php
                    $total = max(1, collect($card['segments'])->sum('value'));
                @endphp

                <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        {{ __("admin.dashboard.stats.{$card['key']}") }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 tabular-nums dark:text-white">
                        {{ \App\Support\UiFormats::number($card['value']) }}
                    </p>

                    <div
                        class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                        dir="ltr"
                        data-testid="stat-composition-{{ $card['key'] }}"
                    >
                        @foreach ($card['segments'] as $segment)
                            @if ($segment['value'] > 0)
                                <div
                                    class="{{ $segment['bar'] }} h-1.5"
                                    style="width: {{ round(($segment['value'] / $total) * 100, 2) }}%"
                                    title="{{ __("admin.dashboard.stats.segments.{$segment['key']}") }}: {{ \App\Support\UiFormats::number($segment['value']) }}"
                                ></div>
                            @endif
                        @endforeach
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{
                            collect($card['segments'])
                                ->filter(fn (array $segment): bool => $segment['value'] > 0)
                                ->map(fn (array $segment): string => __("admin.dashboard.stats.segments.{$segment['key']}").' '.\App\Support\UiFormats::number($segment['value']))
                                ->implode(' · ')
                        }}
                    </p>

                    @if (isset($card['action']))
                        <x-filament::link
                            tag="button"
                            size="sm"
                            :icon="$card['icon']"
                            wire:click="{{ $card['action'] }}"
                            class="mt-2"
                        >
                            {{ __('admin.dashboard.stats.open') }}
                        </x-filament::link>
                    @elseif (filled($card['url'] ?? null))
                        <x-filament::link :href="$card['url']" size="sm" :icon="$card['icon']" class="mt-2">
                            {{ __('admin.dashboard.stats.open') }}
                        </x-filament::link>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
