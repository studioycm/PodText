<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.stream.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag', ['flow' => true])
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium">
            <button
                type="button"
                wire:click="selectType(null)"
                @class([
                    'rounded-full px-2.5 py-0.5',
                    'bg-primary-600 text-white dark:bg-primary-500' => $activeType === null,
                    'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeType !== null,
                ])
            >
                {{ __('admin.dashboard.stream.types.all') }}
            </button>

            @foreach ($types as $type)
                <button
                    type="button"
                    wire:click="selectType('{{ $type }}')"
                    @class([
                        'rounded-full px-2.5 py-0.5',
                        'bg-primary-600 text-white dark:bg-primary-500' => $activeType === $type,
                        'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeType !== $type,
                    ])
                >
                    {{ __("admin.dashboard.stream.types.{$type}") }}
                </button>
            @endforeach

            @if ($day)
                <span class="text-gray-500 dark:text-gray-400" data-testid="stream-day">
                    {{ __('admin.dashboard.stream.on_day', ['date' => \Illuminate\Support\Carbon::parse($day)->format('d/m/Y')]) }}
                </span>
            @endif
        </div>

        @if (count($events) === 0)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.stream.empty') }}</p>
        @else
            <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($events as $event)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="stream-row">
                        <span class="{{ $badges[$event['type']] }} rounded-full px-2 py-0.5 text-xs font-medium">
                            {{ __("admin.dashboard.stream.types.{$event['type']}") }}
                        </span>

                        @if ($event['url'])
                            <a
                                href="{{ $event['url'] }}"
                                class="flex-1 truncate text-gray-800 hover:underline dark:text-gray-200"
                            >
                                {{ $event['title'] }}
                            </a>
                        @else
                            <span class="flex-1 truncate text-gray-800 dark:text-gray-200">{{ $event['title'] }}</span>
                        @endif

                        @if ($event['subtitle'])
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $event['subtitle'] }}</span>
                        @endif

                        <time
                            class="text-xs text-gray-500 tabular-nums dark:text-gray-400"
                            dir="ltr"
                            datetime="{{ $event['at']->toIso8601String() }}"
                        >
                            {{ $event['at']->copy()->timezone('Asia/Jerusalem')->format('d/m/Y H:i') }}
                        </time>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
