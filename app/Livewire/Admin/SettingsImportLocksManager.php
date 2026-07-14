<?php

namespace App\Livewire\Admin;

use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsImportLockSurfaceRegistry;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use Illuminate\Contracts\View\View;

class SettingsImportLocksManager extends SettingsLifecycleSelectionTable
{
    public string $tableMode = 'locks';

    public string $filter = 'all';

    public ?string $resultMessage = null;

    /** @var array<int, string> */
    public array $retiredLockedPaths = [];

    public function mount(): void
    {
        $schema = app(SettingsLifecycleSchema::class);
        $locks = app(SettingsImportLocks::class);
        $registry = app(SettingsImportLockSurfaceRegistry::class);
        $payload = $schema->payloadForGroup();

        $this->rows = collect($registry->surfaces($payload))
            ->map(fn (array $surface): array => [
                'group' => $surface['group'],
                'group_label' => $surface['group_label'],
                'path' => $surface['id'],
                'label' => $surface['label'],
                'label_key' => '',
                'structural_type' => $surface['type'],
                'expected_type' => null,
                'semantics' => [],
                'state' => 'unchanged',
                'outcome' => 'lockable',
                'current_preview' => __('admin.settings_import_locks.surface_unit_count', ['count' => count($surface['unit_paths'])]),
                'imported_preview' => '',
                'selectable' => true,
                'selected' => false,
                'locked' => false,
                'error' => null,
            ])
            ->values()
            ->all();

        $lockedPaths = $locks->lockedPaths();
        $this->selectedPaths = $registry->selectedSurfaceIds($lockedPaths, $payload);
        $this->retiredLockedPaths = $registry->retiredLockedPaths($lockedPaths, $payload);
    }

    public function render(): View
    {
        return view('livewire.admin.settings-import-locks-manager', [
            'groupedRows' => $this->groupedRows(),
        ]);
    }

    public function saveLocks(): void
    {
        $registry = app(SettingsImportLockSurfaceRegistry::class);
        $lockedPaths = app(SettingsImportLocks::class)->save([
            ...$registry->unitPathsForSurfaceIds($this->selectedPaths),
            ...$this->retiredLockedPaths,
        ]);
        $this->selectedPaths = $registry->selectedSurfaceIds($lockedPaths);
        $this->retiredLockedPaths = $registry->retiredLockedPaths($lockedPaths);
        $this->resultMessage = __('admin.messages.settings_import_locks_saved', [
            'count' => count($lockedPaths),
        ]);
    }

    public function lockAllFrontTexts(): void
    {
        $this->selectedPaths = array_values(array_unique([
            ...$this->selectedPaths,
            ...app(SettingsImportLockSurfaceRegistry::class)->surfaceIdsForFrontText(),
        ]));
    }

    public function unlockAll(): void
    {
        $this->selectedPaths = [];
        $this->retiredLockedPaths = [];
    }

    private function preview(mixed $value, bool $exists): string
    {
        if (! $exists) {
            return __('admin.labels.settings_import_missing');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return PublicSettingsPackage::canonicalPayloadJson($value);
        }

        return str((string) $value)->limit(120)->toString();
    }
}
