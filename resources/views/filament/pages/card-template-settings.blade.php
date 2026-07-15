<x-filament-panels::page>
    <div data-sp3c-template-library>
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_sp3c.import_locks.heading') }}</x-slot>
            <x-slot name="description">{{ __('admin.settings_sp3c.import_locks.description') }}</x-slot>

            <div class="flex flex-wrap gap-2">
                @foreach ($familyLocks as $family => $locked)
                    <x-filament::badge :color="$locked ? 'warning' : 'gray'">
                        {{ __('admin.card_template_families.'.$family) }}:
                        {{ $locked ? __('admin.settings_sp3c.import_locks.locked') : __('admin.settings_sp3c.import_locks.unlocked') }}
                    </x-filament::badge>
                @endforeach
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
