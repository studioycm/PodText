<x-filament-panels::page>
    <div data-sp3c-template-editor-page>
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_sp3c.import_locks.heading') }}</x-slot>
            <x-slot name="description">{{ __('admin.settings_sp3c.import_locks.description') }}</x-slot>

            <x-filament::badge :color="$familyImportLocked ? 'warning' : 'gray'">
                {{ $familyImportLocked ? __('admin.settings_sp3c.import_locks.locked') : __('admin.settings_sp3c.import_locks.unlocked') }}
            </x-filament::badge>
        </x-filament::section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
