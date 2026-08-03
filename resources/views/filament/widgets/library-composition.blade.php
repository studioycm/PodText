<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.composition.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2">
            @foreach ($chips as $chip)
                <x-filament::link :href="$chip['url']" size="sm" color="gray" :icon="$chip['icon']">
                    {{ __("admin.dashboard.composition.{$chip['key']}") }}
                    <span class="font-semibold tabular-nums">{{ $chip['value'] }}</span>
                </x-filament::link>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('admin.dashboard.composition.health_heading') }}
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('admin.dashboard.composition.health_hint') }}
                </p>

                @if (count($health) === 0)
                    <div class="mt-3">
                        @include('filament.widgets.partials.empty-state', [
                            'heading' => __('admin.dashboard.composition.health_empty'),
                            'description' => __('admin.dashboard.composition.health_empty_description'),
                            'icon' => \Filament\Support\Icons\Heroicon::OutlinedChartBar,
                            'testid' => 'composition-health-empty',
                        ])
                    </div>
                @else
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach ($health as $podcast)
                            @php
                                $visible = (int) $podcast->value;
                                $stuck = $podcast->remainder();
                                $percent = $podcast->percent();
                            @endphp

                            <div class="flex items-center gap-3" data-testid="podcast-health-row">
                                <dt class="w-40 shrink-0 truncate">
                                    @if ($podcast->url)
                                        <a
                                            href="{{ $podcast->url }}"
                                            class="text-gray-700 hover:underline dark:text-gray-200"
                                        >
                                            {{ $podcast->label }}
                                        </a>
                                    @else
                                        {{-- The rolled-up "other" row spans several podcasts, so it has no single doorway. --}}
                                        <span class="text-gray-700 dark:text-gray-200">{{ $podcast->label }}</span>
                                    @endif
                                </dt>
                                <dd class="flex flex-1 items-center gap-2">
                                    <div
                                        class="flex h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5"
                                        dir="ltr"
                                    >
                                        @if ($visible > 0)
                                            <div
                                                class="{{ \App\Enums\FunnelStage::Visible->barClass() }} h-2.5"
                                                style="width: {{ $percent }}%"
                                            ></div>
                                        @endif
                                        @if ($stuck > 0)
                                            <div
                                                class="{{ \App\Enums\DashboardTier::Invisible->barClass() }} h-2.5"
                                                style="width: {{ 100 - $percent }}%"
                                            ></div>
                                        @endif
                                    </div>
                                    <span class="w-36 shrink-0 text-end text-xs text-gray-500 tabular-nums dark:text-gray-400">
                                        {{
                                            __('admin.dashboard.composition.health_value', [
                                                'percent' => $percent,
                                                'blocked' => $stuck,
                                            ])
                                        }}
                                    </span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            <div>
                <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('admin.dashboard.composition.transcribers_heading') }}
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('admin.dashboard.composition.transcribers_hint') }}
                </p>

                @if (count($transcribers) === 0)
                    <div class="mt-3">
                        @include('filament.widgets.partials.empty-state', [
                            'heading' => __('admin.dashboard.composition.transcribers_empty'),
                            'description' => __('admin.dashboard.composition.transcribers_empty_description'),
                            'icon' => \Filament\Support\Icons\Heroicon::OutlinedUsers,
                            'testid' => 'composition-transcribers-empty',
                        ])
                    </div>
                @else
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($transcribers as $transcriber)
                            @php
                                $item = $transcriber;
                                $delta = $item->delta();
                            @endphp

                            <li class="flex items-center gap-3" data-testid="transcriber-row">
                                @if ($item->url)
                                    <a
                                        href="{{ $item->url }}"
                                        class="w-32 shrink-0 truncate text-gray-700 hover:underline dark:text-gray-200"
                                    >
                                        {{ $item->label }}
                                    </a>
                                @else
                                    {{-- The rolled-up "other" row spans several transcribers, so it has no single doorway. --}}
                                    <span class="w-32 shrink-0 truncate text-gray-700 dark:text-gray-200">{{ $item->label }}</span>
                                @endif
                                <span class="flex-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{
                                        __('admin.dashboard.composition.transcriber_value', [
                                            'count' => (int) $item->value,
                                            'words' => \App\Support\UiFormats::number((int) $item->meta('words', 0)),
                                        ])
                                    }}
                                </span>
                                <span
                                    class="text-xs font-medium tabular-nums {{ $item->trend()?->textClass() }}"
                                    dir="ltr"
                                >
                                    {{ $delta > 0 ? '+' : '' }}{{ $delta }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
