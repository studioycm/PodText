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
                    <input type="file" wire:model="packageFile" accept="application/json,text/plain,.json" class="block w-full text-sm text-gray-700 dark:text-gray-200">
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

            <div class="mb-5 flex flex-wrap items-center gap-4">
                <label class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('admin.fields.settings_import_mode') }}
                </label>
                <select wire:model.live="importMode" class="fi-select-input rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                    <option value="replace">{{ __('admin.settings_import.modes.replace') }}</option>
                    <option value="add_only">{{ __('admin.settings_import.modes.add_only') }}</option>
                </select>
            </div>

            <div class="mb-5 flex flex-wrap items-center gap-2" data-test="settings-import-dry-run-summary">
                <x-filament::badge color="info">
                    {{ __('admin.settings_import.summary.selected', ['count' => $dryRunSummary['selected']]) }}
                </x-filament::badge>
                <x-filament::badge color="success">
                    {{ __('admin.settings_import.summary.added', ['count' => $dryRunSummary['added']]) }}
                </x-filament::badge>
                <x-filament::badge color="info">
                    {{ __('admin.settings_import.summary.changed', ['count' => $dryRunSummary['changed']]) }}
                </x-filament::badge>
                <x-filament::badge :color="$dryRunSummary['locked'] > 0 ? 'warning' : 'gray'" icon="heroicon-o-lock-closed">
                    {{ __('admin.settings_import.summary.locked', ['count' => $dryRunSummary['locked']]) }}
                </x-filament::badge>
                <x-filament::badge :color="$dryRunSummary['errors'] > 0 ? 'danger' : 'gray'">
                    {{ __('admin.settings_import.summary.errors', ['count' => $dryRunSummary['errors']]) }}
                </x-filament::badge>
                @if($importMode === 'add_only')
                    <x-filament::badge color="gray">
                        {{ __('admin.settings_import.summary.skip_exists', ['count' => $dryRunSummary['skip_exists']]) }}
                    </x-filament::badge>
                @endif
            </div>

            @if($lockedExcludedRows !== [])
                <details class="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-900 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-100" data-test="settings-import-locked-excluded">
                    <summary class="cursor-pointer font-medium">
                        {{ __('admin.settings_import.locked_summary', ['count' => count($lockedExcludedRows)]) }}
                    </summary>
                    <ul class="mt-3 list-disc space-y-1 ps-5">
                        @foreach($lockedExcludedRows as $row)
                            <li>{{ $row['label'] }} <span class="text-xs opacity-80">({{ $row['path'] }})</span></li>
                        @endforeach
                    </ul>
                    <a href="{{ $importLocksUrl }}" class="mt-3 inline-flex text-sm font-medium underline">
                        {{ __('admin.actions.manage_import_locks') }}
                    </a>
                </details>
            @endif

            @include('livewire.admin.partials.settings-lifecycle-selection-table', [
                'groupedRows' => $groupedRows,
                'tableMode' => 'import',
            ])

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
            <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                <a href="{{ \App\Filament\Resources\SettingsBackups\SettingsBackupResource::getUrl() }}" class="font-medium underline">
                    {{ __('admin.settings_import.report.backup_link', ['id' => $structuredImportReport->beforeImportBackupId]) }}
                </a>
            </div>

            <div class="mt-5 space-y-4" data-test="settings-import-completion-report">
                <div class="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <div class="font-medium text-gray-950 dark:text-white">{{ __('admin.settings_import.report.mode') }}</div>
                        <div class="text-gray-600 dark:text-gray-300">{{ __('admin.settings_import.modes.' . $structuredImportReport->mode) }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-950 dark:text-white">{{ __('admin.settings_import.report.source') }}</div>
                        <div class="text-gray-600 dark:text-gray-300">{{ $structuredImportReport->sourceLabel ?? __('admin.placeholders.empty') }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-950 dark:text-white">{{ __('admin.settings_import.report.generated_at') }}</div>
                        <div class="text-gray-600 dark:text-gray-300">{{ $structuredImportReport->generatedAtLabel() }}</div>
                    </div>
                </div>

                @if($structuredImportReport->warnings !== [])
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.settings_import.report.warnings') }}</h3>
                        <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-warning-700 dark:text-warning-300">
                            @foreach($structuredImportReport->warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @foreach(\App\Support\SettingsLifecycle\SettingsImportReport::OUTCOME_GROUPS as $outcome)
                    @php($rows = $structuredImportReport->outcomeRows($outcome))
                    @if($rows !== [])
                        <div>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ __('admin.settings_import_report.outcomes.' . $outcome) }}
                            </h3>
                            <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-200">
                                @foreach($rows as $row)
                                    <li>
                                        {{ $row['label'] }}
                                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ $row['path'] }})</span>
                                        @if(filled($row['reason'] ?? null))
                                            <span class="text-xs text-gray-500 dark:text-gray-400">- {{ $row['reason'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="mt-4">
                <x-filament::button wire:click="resetImport" color="gray">
                    {{ __('admin.actions.import_another_package') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</div>
