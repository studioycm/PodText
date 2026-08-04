<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.intake.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium">
                <button
                    type="button"
                    wire:click="selectKind(null)"
                    @class([
                        'rounded-full px-2.5 py-0.5',
                        'bg-primary-600 text-white dark:bg-primary-500' => $activeKind === null,
                        'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeKind !== null,
                    ])
                >
                    {{ __('admin.dashboard.intake.chips_all') }} {{ \App\Support\UiFormats::number($counts['all']) }}
                </button>

                @foreach ($chipKinds as $chipKind)
                    <button
                        type="button"
                        wire:click="selectKind('{{ $chipKind->value }}')"
                        @class([
                            'rounded-full px-2.5 py-0.5',
                            'bg-primary-600 text-white dark:bg-primary-500' => $activeKind === $chipKind->value,
                            'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeKind !== $chipKind->value,
                        ])
                        data-testid="intake-chip-{{ $chipKind->value }}"
                    >
                        {{ $chipKind->getLabel() }}
                        {{ \App\Support\UiFormats::number($chipKind === \App\Enums\StreamEventType::Submission ? $counts['submissions'] : $counts['imports']) }}
                    </button>
                @endforeach
            </div>

            @if (count($rows) === 0 && $source !== null && $source !== \App\Enums\ImportConnectionProvider::Manual)
                {{-- A provider scope with no stamped rows: honest, and
                     user-unreachable for connected providers until WB
                     stamps real fetch imports (Q1 override). --}}
                <div class="mt-4">
                    @include('filament.widgets.partials.empty-state', [
                        'heading' => __('admin.dashboard.intake.empty_heading'),
                        'description' => __('admin.dashboard.intake.source_empty', ['source' => $source->getLabel()]),
                        'icon' => \Filament\Support\Icons\Heroicon::OutlinedInbox,
                        'testid' => 'intake-source-empty',
                    ])
                </div>
            @elseif (count($rows) === 0)
                <div class="mt-4">
                    @include('filament.widgets.partials.empty-state', [
                        'heading' => __('admin.dashboard.intake.empty_heading'),
                        'description' => __('admin.dashboard.intake.empty_description'),
                        'icon' => \Filament\Support\Icons\Heroicon::OutlinedInbox,
                        'testid' => 'intake-empty',
                    ])
                </div>
            @else
                <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="intake-row">
                            <span class="{{ $row['type']->chipClass() }} rounded-full px-2 py-0.5 text-xs font-medium">
                                {{ $row['type']->getLabel() }}
                            </span>

                            <a href="{{ $row['url'] }}" class="flex-1 truncate text-gray-800 hover:underline dark:text-gray-200">
                                {{ $row['title'] }}
                            </a>

                            @if ($row['subtitle'])
                                <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ __('admin.dashboard.intake.download_hint') }}">
                                    {{ $row['subtitle'] }}
                                </span>
                            @endif

                            <time
                                class="text-xs text-gray-500 tabular-nums dark:text-gray-400"
                                dir="ltr"
                                datetime="{{ $row['at']->toIso8601String() }}"
                            >
                                {{ $row['at']->copy()->timezone(\App\Support\UiTimezone::name())->format(\App\Support\UiFormats::dateTime()) }}
                            </time>
                        </li>
                    @endforeach
                </ul>

                @if ($counts['all'] > count($rows))
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-testid="intake-cap-note">
                        {{ __('admin.dashboard.intake.showing_latest', ['count' => count($rows), 'total' => $counts['all']]) }}
                        · <a href="{{ $submissionsUrl }}" class="hover:underline">{{ __('admin.dashboard.intake.view_new_submissions') }}</a>
                    </p>
                @endif
            @endif
    </x-filament::section>
</x-filament-widgets::widget>
