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
                    wire:click="selectType('{{ $type->value }}')"
                    @class([
                        'rounded-full px-2.5 py-0.5',
                        'bg-primary-600 text-white dark:bg-primary-500' => $activeType === $type->value,
                        'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeType !== $type->value,
                    ])
                >
                    {{ $type->getLabel() }}
                </button>
            @endforeach

            @if ($day)
                <span class="text-gray-500 dark:text-gray-400" data-testid="stream-day">
                    {{ __('admin.dashboard.stream.on_day', ['date' => \Illuminate\Support\Carbon::parse($day)->format(\App\Support\UiFormats::date())]) }}
                </span>
            @endif
        </div>

        @if (count($events) === 0)
            <div class="mt-4">
                @include('filament.widgets.partials.empty-state', [
                    'heading' => __('admin.dashboard.stream.empty'),
                    'description' => __('admin.dashboard.stream.empty_description'),
                    'icon' => \Filament\Support\Icons\Heroicon::OutlinedBolt,
                    'testid' => 'stream-empty',
                ])
            </div>
        @else
            <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($events as $event)
                    @php($eventType = \App\Enums\StreamEventType::from($event['type']))
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="stream-row">
                        <span class="{{ $eventType->chipClass() }} rounded-full px-2 py-0.5 text-xs font-medium">
                            {{ $eventType->getLabel() }}
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
                            {{ $event['at']->copy()->timezone(\App\Support\UiTimezone::name())->format(\App\Support\UiFormats::dateTime()) }}
                        </time>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
