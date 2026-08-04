<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.media_findings.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        @if (! $rate->isEmpty())
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-testid="media-clean-rate">
                {{ __('admin.dashboard.media_findings.rate', ['percent' => $rate->percent()]) }}
            </p>
        @endif

        @if ($rate->isEmpty())
            <div class="mt-4">
                @include('filament.widgets.partials.empty-state', [
                    'heading' => __('admin.dashboard.media_findings.no_media'),
                    'icon' => \Filament\Support\Icons\Heroicon::OutlinedPhoto,
                    'testid' => 'media-no-media',
                ])
            </div>
        @elseif (count($rows) === 0)
            <div class="mt-4">
                @include('filament.widgets.partials.empty-state', [
                    'heading' => __('admin.dashboard.media_findings.empty'),
                    'icon' => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
                    'testid' => 'media-findings-empty',
                ])
            </div>
        @else
            @php($peak = max(array_map(fn ($row): float => $row->value, $rows)))
            <dl class="mt-4 space-y-2 text-sm">
                @foreach ($rows as $row)
                    @php($reason = \App\Enums\MediaDiagnosticReason::from($row->meta('reason')))
                    <div class="flex items-center gap-3" data-testid="media-finding-row">
                        <dt class="flex w-44 shrink-0 items-center gap-1.5 text-gray-600 dark:text-gray-300">
                            <x-filament::icon :icon="$reason->getIcon()" class="h-4 w-4 shrink-0" />
                            <a href="{{ $row->url }}" class="truncate hover:underline">{{ $row->label }}</a>
                        </dt>
                        <dd class="flex flex-1 items-center gap-2">
                            <div
                                class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5"
                                dir="ltr"
                            >
                                <div
                                    class="{{ $row->meta('bar', 'bg-info-500') }} h-2.5 rounded-full"
                                    style="width: {{ max(6, (int) round(($row->value / $peak) * 100)) }}%"
                                ></div>
                            </div>
                            <span class="w-8 text-end font-semibold tabular-nums">{{ \App\Support\UiFormats::number((int) $row->value) }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.dashboard.media_findings.caption') }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
