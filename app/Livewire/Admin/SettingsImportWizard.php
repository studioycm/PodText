<?php

namespace App\Livewire\Admin;

use App\Enums\SettingsImportMode;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsPackageImportAnalyzer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class SettingsImportWizard extends SettingsLifecycleSelectionTable
{
    use WithFileUploads;

    public mixed $packageFile = null;

    public ?int $selectedBackupId = null;

    public string $step = 'source';

    /**
     * @var array<string, mixed>
     */
    public array $packageArray = [];

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    /**
     * @var array<int, string>
     */
    public array $importErrors = [];

    public ?string $sourceLabel = null;

    public ?string $resultMessage = null;

    public string $importMode = 'replace';

    public function render(): View
    {
        return view('livewire.admin.settings-import-wizard', [
            'backups' => SettingsBackupVersion::query()
                ->latest('id')
                ->limit(50)
                ->get(),
            'groupedRows' => $this->groupedRows(),
        ]);
    }

    public function loadUploadedPackage(): void
    {
        $this->validate([
            'packageFile' => ['required', 'file', 'max:2048', 'mimetypes:application/json,text/plain'],
        ]);

        if (! $this->packageFile instanceof UploadedFile) {
            $this->importErrors = [__('admin.messages.settings_import_upload_required')];

            return;
        }

        $contents = file_get_contents($this->packageFile->getRealPath());

        if ($contents === false) {
            $this->importErrors = [__('admin.messages.settings_import_upload_unreadable')];

            return;
        }

        $this->loadPackageJson($contents, $this->packageFile->getClientOriginalName());
    }

    public function loadBackupPackage(): void
    {
        $this->validate([
            'selectedBackupId' => ['required', 'integer', 'exists:settings_backup_versions,id'],
        ]);

        $backup = SettingsBackupVersion::query()->findOrFail($this->selectedBackupId);

        $this->loadPackageJson(
            json: $backup->payload_json,
            sourceLabel: __('admin.labels.settings_import_backup_source', ['id' => $backup->getKey()]),
        );
    }

    public function applyImport(): void
    {
        if ($this->packageArray === []) {
            $this->importErrors = [__('admin.messages.settings_import_no_package_loaded')];
            $this->step = 'source';

            return;
        }

        try {
            $user = auth()->user();

            $appliedPaths = app(SettingsBackupManager::class)->import(
                PublicSettingsPackage::fromArray($this->packageArray),
                $this->selectedPaths,
                $user instanceof User ? $user : null,
                $this->importMode,
            );
        } catch (Throwable $exception) {
            $this->importErrors = [$exception->getMessage()];

            return;
        }

        $this->step = 'complete';
        $this->resultMessage = __('admin.messages.settings_import_applied', [
            'count' => count($appliedPaths),
        ]);
    }

    public function resetImport(): void
    {
        $this->reset([
            'packageFile',
            'selectedBackupId',
            'packageArray',
            'rows',
            'selectedPaths',
            'warnings',
            'importErrors',
            'sourceLabel',
            'resultMessage',
        ]);

        $this->importMode = SettingsImportMode::Replace->value;
        $this->step = 'source';
    }

    public function updatedImportMode(): void
    {
        $this->reanalyzePackage();
    }

    public function lockedRowsCount(): int
    {
        return collect($this->rows)
            ->where('locked', true)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dryRunSignature(): array
    {
        return collect($this->rows)
            ->map(fn (array $row): array => [
                'path' => $row['path'],
                'state' => $row['state'],
                'outcome' => $row['outcome'],
                'current_preview' => $row['current_preview'],
                'imported_preview' => $row['imported_preview'],
                'error' => $row['error'],
            ])
            ->values()
            ->all();
    }

    private function loadPackageJson(string $json, string $sourceLabel): void
    {
        try {
            $packageArray = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->importErrors = [$exception->getMessage()];
            $this->step = 'source';

            return;
        }

        if (! is_array($packageArray)) {
            $this->importErrors = [__('admin.messages.settings_import_package_invalid')];
            $this->step = 'source';

            return;
        }

        $analysis = app(SettingsPackageImportAnalyzer::class)->analyzeArray($packageArray, $this->importMode);

        $this->packageArray = $packageArray;
        $this->sourceLabel = $sourceLabel;
        $this->rows = $analysis->rows;
        $this->selectedPaths = $analysis->selectedPaths;
        $this->warnings = $analysis->warnings;
        $this->importErrors = $analysis->errors;
        $this->step = $analysis->refused() ? 'source' : 'dry-run';
    }

    private function reanalyzePackage(): void
    {
        if ($this->packageArray === [] || $this->step !== 'dry-run') {
            return;
        }

        $analysis = app(SettingsPackageImportAnalyzer::class)->analyzeArray($this->packageArray, $this->importMode);

        $this->rows = $analysis->rows;
        $this->selectedPaths = $analysis->selectedPaths;
        $this->warnings = $analysis->warnings;
        $this->importErrors = $analysis->errors;
        $this->step = $analysis->refused() ? 'source' : 'dry-run';
    }
}
