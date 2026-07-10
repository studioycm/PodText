<div class="space-y-6" dir="{{ app()->getLocale() === 'he' ? 'rtl' : 'ltr' }}">
    <x-filament::section>
        <x-slot name="heading">{{ __('admin.settings_import_locks.heading') }}</x-slot>
        <x-slot name="description">{{ __('admin.settings_import_locks.description') }}</x-slot>

        @if($resultMessage)
            <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300">
                {{ $resultMessage }}
            </div>
        @endif

        <div class="mb-5 flex flex-wrap gap-3">
            <x-filament::button wire:click="saveLocks" icon="heroicon-o-lock-closed">
                {{ __('admin.actions.save_import_locks') }}
            </x-filament::button>
            <x-filament::button wire:click="lockAllFrontTexts" color="gray" icon="heroicon-o-language">
                {{ __('admin.actions.lock_all_front_texts') }}
            </x-filament::button>
            <x-filament::button wire:click="unlockAll" color="gray" icon="heroicon-o-lock-open">
                {{ __('admin.actions.unlock_all') }}
            </x-filament::button>
        </div>

        @include('livewire.admin.partials.settings-lifecycle-selection-table', [
            'groupedRows' => $groupedRows,
            'tableMode' => 'locks',
        ])
    </x-filament::section>
</div>
