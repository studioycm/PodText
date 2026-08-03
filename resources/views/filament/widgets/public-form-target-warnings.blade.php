<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.form_targets.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ __('admin.dashboard.form_targets.description') }}
        </p>

        <ul class="mt-4 space-y-2" data-testid="form-target-warnings">
            @foreach ($rows as $row)
                <li
                    class="flex flex-wrap items-center justify-between gap-3"
                    data-testid="form-target-warning-{{ $row['key'] }}"
                >
                    <span class="flex items-center gap-2 text-sm">
                        <x-filament::badge color="warning">{{ $row['count'] }}</x-filament::badge>
                        {{ $row['label'] }}
                    </span>

                    <x-filament::link :href="$row['url']" size="sm"> {{ $row['link_label'] }} </x-filament::link>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
