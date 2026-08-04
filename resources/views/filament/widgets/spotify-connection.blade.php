<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.connection.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        @if (count($connections) === 0)
            <div class="mt-4">
                @include('filament.widgets.partials.empty-state', [
                    'heading' => __('admin.dashboard.connection.none_heading'),
                    'description' => __('admin.dashboard.connection.none_description'),
                    'icon' => \Filament\Support\Icons\Heroicon::OutlinedSignalSlash,
                    'testid' => 'connection-empty',
                ])
            </div>
        @else
            <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($connections as $connection)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="connection-row">
                        <span class="flex-1 truncate text-gray-800 dark:text-gray-200">{{ $connection['name'] }}</span>

                        <x-filament::badge :color="$connection['status']->getColor()">
                            {{ $connection['status']->getLabel() }}
                        </x-filament::badge>

                        @if ($connection['last_tested_at'])
                            <time
                                class="text-xs text-gray-500 tabular-nums dark:text-gray-400"
                                dir="ltr"
                                datetime="{{ $connection['last_tested_at']->toIso8601String() }}"
                                data-testid="connection-tested-at"
                            >
                                {{ __('admin.dashboard.connection.last_tested', ['date' => $connection['last_tested_at']->copy()->timezone(\App\Support\UiTimezone::name())->format(\App\Support\UiFormats::dateTime())]) }}
                            </time>
                        @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.connection.never_tested') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($reduced)
                <p class="text-warning-600 dark:text-warning-400 mt-2 text-xs" data-testid="connection-reduced">
                    {{ __('admin.dashboard.connection.reduced_note') }}
                </p>
            @endif
        @endif

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ $manageUrl }}" class="hover:underline">{{ __('admin.dashboard.connection.manage') }}</a>
            ·
            <a href="{{ $fetcherUrl }}" class="hover:underline">{{ __('admin.dashboard.connection.open_fetcher') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
