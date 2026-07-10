<?php

namespace App\Livewire\Admin;

use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use App\Support\SettingsLifecycle\SettingsLifecycleUnit;
use Illuminate\Contracts\View\View;

class SettingsImportLocksManager extends SettingsLifecycleSelectionTable
{
    public string $tableMode = 'locks';

    public string $filter = 'all';

    public ?string $resultMessage = null;

    public function mount(): void
    {
        $schema = app(SettingsLifecycleSchema::class);
        $locks = app(SettingsImportLocks::class);
        $payload = $schema->payloadForGroup();

        $this->rows = collect($schema->units($payload))
            ->map(fn (SettingsLifecycleUnit $unit): array => [
                'group' => $unit->section,
                'group_label' => $unit->sectionLabel,
                'path' => $unit->path,
                'label' => $unit->label,
                'label_key' => $unit->labelKey,
                'structural_type' => $unit->structuralType,
                'expected_type' => $unit->expectedScalarType,
                'semantics' => $unit->semantics,
                'state' => 'unchanged',
                'outcome' => 'lockable',
                'current_preview' => $this->preview($schema->value($payload, $unit->path), $schema->valueExists($payload, $unit->path)),
                'imported_preview' => '',
                'selectable' => true,
                'selected' => false,
                'locked' => false,
                'error' => null,
            ])
            ->values()
            ->all();

        $this->selectedPaths = $locks->lockedPaths();
    }

    public function render(): View
    {
        return view('livewire.admin.settings-import-locks-manager', [
            'groupedRows' => $this->groupedRows(),
        ]);
    }

    public function saveLocks(): void
    {
        $this->selectedPaths = app(SettingsImportLocks::class)->save($this->selectedPaths);
        $this->resultMessage = __('admin.messages.settings_import_locks_saved', [
            'count' => count($this->selectedPaths),
        ]);
    }

    public function lockAllFrontTexts(): void
    {
        $locks = app(SettingsImportLocks::class);

        $this->selectedPaths = $locks->normalize([
            ...$this->selectedPaths,
            ...$locks->frontTextLockPaths(),
        ]);
    }

    public function unlockAll(): void
    {
        $this->selectedPaths = [];
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
