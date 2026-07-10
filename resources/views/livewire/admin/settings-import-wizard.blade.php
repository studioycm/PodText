<div class="space-y-6" dir="{{ app()->getLocale() === 'he' ? 'rtl' : 'ltr' }}">
    @if($step === 'source')
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_import.source_heading') }}</x-slot>
            <x-slot name="description">{{ __('admin.settings_import.source_description') }}</x-slot>

            <div class="grid gap-6 lg:grid-cols-2">
                <form wire:submit="loadUploadedPackage" class="space-y-4">
                    <label class="block text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('admin.fields.settings_import_upload') }}
                    </label>
                    <input type="file" wire:model="packageFile" accept="application/json,.json" class="block w-full text-sm text-gray-700 dark:text-gray-200">
                    @error('packageFile')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                        {{ __('admin.actions.validate_import_package') }}
                    </x-filament::button>
                </form>

                <form wire:submit="loadBackupPackage" class="space-y-4">
                    <label class="block text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('admin.fields.settings_import_backup') }}
                    </label>
                    <select wire:model="selectedBackupId" class="fi-select-input block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        <option value="">{{ __('admin.placeholders.choose_backup') }}</option>
                        @foreach($backups as $backup)
                            <option value="{{ $backup->getKey() }}">
                                #{{ $backup->getKey() }} · {{ $backup->source?->getLabel() }} · {{ $backup->created_at?->timezone('Asia/Jerusalem')->format('d/m/Y H:i') }}
                            </option>
                        @endforeach
                    </select>
                    @error('selectedBackupId')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                    <x-filament::button type="submit" color="gray" icon="heroicon-o-archive-box">
                        {{ __('admin.actions.validate_backup_package') }}
                    </x-filament::button>
                </form>
            </div>
        </x-filament::section>
    @endif

    @if($importErrors !== [])
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_import.errors_heading') }}</x-slot>
            <ul class="list-disc space-y-1 ps-5 text-sm text-danger-600 dark:text-danger-400">
                @foreach($importErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if($warnings !== [])
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_import.warnings_heading') }}</x-slot>
            <ul class="list-disc space-y-1 ps-5 text-sm text-warning-700 dark:text-warning-300">
                @foreach($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if($step === 'dry-run')
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_import.dry_run_heading') }}</x-slot>
            <x-slot name="description">{{ __('admin.settings_import.dry_run_description', ['source' => $sourceLabel]) }}</x-slot>

            @include('livewire.admin.partials.settings-lifecycle-selection-table', ['groupedRows' => $groupedRows])

            <div class="mt-6 flex flex-wrap gap-3">
                <x-filament::button wire:click="applyImport" icon="heroicon-o-check">
                    {{ __('admin.actions.apply_settings_import') }}
                </x-filament::button>
                <x-filament::button wire:click="resetImport" color="gray">
                    {{ __('admin.actions.start_over') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if($step === 'complete')
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.settings_import.complete_heading') }}</x-slot>
            <p class="text-sm text-gray-700 dark:text-gray-200">{{ $resultMessage }}</p>
            <div class="mt-4">
                <x-filament::button wire:click="resetImport" color="gray">
                    {{ __('admin.actions.import_another_package') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</div>
