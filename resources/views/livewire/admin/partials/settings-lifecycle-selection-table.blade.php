<div class="space-y-4" data-test="settings-lifecycle-selection-table">
    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="filter" class="fi-select-input rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
            <option value="changed">{{ __('admin.settings_import.filters.changed') }}</option>
            <option value="added">{{ __('admin.settings_import.filters.added') }}</option>
            <option value="removed">{{ __('admin.settings_import.filters.removed') }}</option>
            <option value="all">{{ __('admin.settings_import.filters.all') }}</option>
        </select>
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('admin.placeholders.search_settings_units') }}"
            class="fi-input block min-w-64 rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white"
        >
    </div>

    @forelse($groupedRows as $group => $groupData)
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" wire:key="settings-import-group-{{ $group }}">
            <div class="flex flex-wrap items-center justify-between gap-3 bg-gray-50 px-4 py-3 dark:bg-white/5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $groupData['label'] }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.settings_import.group_summary', [
                            'changed' => collect($groupData['rows'])->where('state', 'changed')->count(),
                            'added' => collect($groupData['rows'])->where('state', 'added')->count(),
                        ]) }}
                    </p>
                </div>
                <x-filament::button wire:click="toggleGroup(@js($group))" size="xs" color="gray" data-test="settings-import-group-toggle">
                    {{ __('admin.settings_import.group_state.' . $this->groupState($group)) }}
                </x-filament::button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th class="w-12 px-4 py-3"></th>
                            <th class="w-56 px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('admin.fields.setting_unit') }}</th>
                            <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('admin.fields.current_value') }}</th>
                            <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('admin.fields.imported_value') }}</th>
                            <th class="w-40 px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('admin.fields.outcome') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($groupData['rows'] as $row)
                            <tr wire:key="settings-import-row-{{ $row['path'] }}" data-test="settings-import-row">
                                <td class="px-4 py-3 align-top">
                                    <input
                                        type="checkbox"
                                        @checked(in_array($row['path'], $selectedPaths, true))
                                        @disabled(! $row['selectable'])
                                        wire:click="toggleUnit(@js($row['path']))"
                                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 disabled:opacity-40 dark:border-white/10"
                                    >
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</div>
                                    <div class="break-all text-xs text-gray-500 dark:text-gray-400">{{ $row['path'] }}</div>
                                    @if($row['error'])
                                        <div class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $row['error'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">
                                    <div class="max-h-24 overflow-y-auto break-words">{{ $row['current_preview'] }}</div>
                                </td>
                                <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">
                                    <div class="max-h-24 overflow-y-auto break-words">{{ $row['imported_preview'] }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <x-filament::badge :color="$row['outcome'] === 'error' ? 'danger' : ($row['state'] === 'unchanged' ? 'gray' : 'info')">
                                        {{ __('admin.settings_import.outcomes.' . $row['outcome']) }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.messages.settings_import_no_rows') }}</p>
    @endforelse
</div>
