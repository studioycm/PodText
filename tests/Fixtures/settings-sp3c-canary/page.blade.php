<x-filament-panels::page>
    @if ($restricted)
        <x-filament::section data-sp3c-canary-restricted>
            {{ __('admin.settings_sp3c.canary.restricted') }}
        </x-filament::section>
    @else
        <form wire:submit="confirmDraft">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('admin.settings_sp3c.canary.confirm') }}
            </x-filament::button>
        </form>
    @endif

</x-filament-panels::page>
