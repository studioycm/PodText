<div class="flex flex-wrap items-center gap-2" data-card-template-import-lock-metadata>
    <span>{{ __('admin.settings_sp3c.import_locks.heading') }}</span>

    <x-filament::badge :color="$familyImportLocked ? 'warning' : 'gray'">
        {{ $familyImportLocked ? __('admin.settings_sp3c.import_locks.locked') : __('admin.settings_sp3c.import_locks.unlocked') }}
    </x-filament::badge>

    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-question-mark-circle"
        size="sm"
        :label="__('admin.settings_sp3c.import_locks.help')"
        :tooltip="__('admin.settings_sp3c.import_locks.description')"
        data-test="card-template-import-lock-help"
    />
</div>
