<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('admin.dashboard.form_targets.heading')"
        :description="__('admin.dashboard.form_targets.description')"
    >
        <ul class="space-y-2" data-testid="form-target-warnings">
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
