<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\SettingsImportMode;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingsPackageImportAnalyzer
{
    public function __construct(
        private readonly SettingsLifecycleSchema $schema,
        private readonly SettingsLifecycleGroups $groups,
        private readonly PublicFrontConfigCache $cache,
        private readonly PublicFrontConfigValidator $validator,
        private readonly SettingsImportLocks $locks,
        private readonly SettingsImportMergeEngine $mergeEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $packageArray
     */
    public function analyzeArray(array $packageArray, SettingsImportMode|string|null $mode = SettingsImportMode::Replace): SettingsPackageImportAnalysis
    {
        try {
            $package = PublicSettingsPackage::fromArray($packageArray);
        } catch (Throwable $exception) {
            $fallback = new PublicSettingsPackage(
                schemaVersion: 0,
                generatedAt: '',
                appVersion: '',
                settingsGroup: $this->groups->defaultGroup()->name,
                settingsMigrationWatermark: '',
                payload: [],
                checksum: '',
            );

            return new SettingsPackageImportAnalysis(
                package: $fallback,
                rows: [],
                errors: [$exception->getMessage()],
            );
        }

        return $this->analyze($package, $mode);
    }

    public function analyze(PublicSettingsPackage $package, SettingsImportMode|string|null $mode = SettingsImportMode::Replace): SettingsPackageImportAnalysis
    {
        $mode = SettingsImportMode::normalize($mode);
        $errors = $this->packageErrors($package);

        if ($errors !== []) {
            return new SettingsPackageImportAnalysis($package, rows: [], errors: $errors);
        }

        $currentPayload = PublicSettingsPackage::fromCurrentSettings()->payload();
        $importedPayload = $package->payload();
        $schemaPayload = array_replace_recursive($currentPayload, $importedPayload);
        $warnings = $this->packageWarnings($package, $importedPayload);
        $rows = $this->rows($currentPayload, $importedPayload, $schemaPayload, $package->settingsGroup(), $mode);
        $selectedPaths = collect($rows)
            ->filter(fn (array $row): bool => (bool) ($row['selected'] ?? false))
            ->pluck('path')
            ->values()
            ->all();

        return new SettingsPackageImportAnalysis(
            package: $package,
            rows: $rows,
            warnings: $warnings,
            selectedPaths: $selectedPaths,
        );
    }

    /**
     * @return array<int, string>
     */
    private function packageErrors(PublicSettingsPackage $package): array
    {
        if (! $package->checksumValid()) {
            return [__('admin.messages.settings_backup_checksum_invalid')];
        }

        if ($package->schemaVersion() > PublicSettingsPackage::SCHEMA_VERSION) {
            return [__('admin.messages.settings_backup_schema_unsupported')];
        }

        if (! in_array($package->settingsGroup(), $this->schema->managedGroups(), true)) {
            return [__('admin.messages.settings_backup_scope_invalid')];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function packageWarnings(PublicSettingsPackage $package, array $payload): array
    {
        $warnings = [];

        if ($package->settingsMigrationWatermark() !== $this->cache->settingsMigrationWatermark()) {
            $warnings[] = __('admin.messages.settings_import_watermark_mismatch');
        }

        foreach ($this->missingAssetPaths($payload) as $path => $value) {
            $warnings[] = __('admin.messages.settings_import_missing_file', [
                'path' => $path,
                'value' => $value,
            ]);
        }

        foreach ($this->normalizationWarnings($payload) as $warning) {
            $warnings[] = $warning;
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function missingAssetPaths(array $payload): array
    {
        $paths = $this->groups->get($this->groups->defaultGroup()->name)
            ->overlay
            ->semanticPaths('asset_path');

        return collect($paths)
            ->mapWithKeys(function (string $path) use ($payload): array {
                $value = data_get($payload, $path);

                if (! is_string($value) || blank($value) || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return [];
                }

                if (Storage::disk('public')->exists($value)) {
                    return [];
                }

                return [$path => $value];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function normalizationWarnings(array $payload): array
    {
        $settingGroups = array_intersect_key($payload, array_flip(PublicFrontConfigRegistry::settingsKeys()));

        if ($settingGroups === []) {
            return [];
        }

        return collect($this->validator->validate($settingGroups)->invalidConfigArray())
            ->map(fn (array $warning): string => __('admin.messages.settings_import_normalization_warning', [
                'path' => $warning['path'],
                'reason' => $warning['reason'],
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $currentPayload
     * @param  array<string, mixed>  $importedPayload
     * @param  array<string, mixed>  $schemaPayload
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $currentPayload, array $importedPayload, array $schemaPayload, string $group, SettingsImportMode $mode): array
    {
        $lockedPaths = $this->locks->lockedPaths();

        return collect($this->schema->units($schemaPayload, $group))
            ->map(function (SettingsLifecycleUnit $unit) use ($currentPayload, $importedPayload, $lockedPaths, $mode): array {
                $currentExists = $this->schema->valueExists($currentPayload, $unit->path);
                $importedExists = $this->schema->valueExists($importedPayload, $unit->path);
                $currentValue = $this->schema->value($currentPayload, $unit->path);
                $importedValue = $this->schema->value($importedPayload, $unit->path);
                $state = $this->state($currentExists, $importedExists, $currentValue, $importedValue);
                $error = $this->rowError($unit, $importedExists, $importedValue);
                $locked = in_array($unit->path, $lockedPaths, true);
                $outcome = $error === null ? $this->outcome($state, $importedExists, $mode, $locked, $currentExists, $currentValue, $importedValue) : 'error';
                $selectable = $error === null
                    && ! $locked
                    && $state !== 'unchanged'
                    && ($importedExists || ($mode === SettingsImportMode::Replace && str_contains($unit->path, '.')))
                    && $outcome !== 'skip_exists';

                return [
                    'group' => $unit->section,
                    'group_label' => $unit->sectionLabel,
                    'path' => $unit->path,
                    'label' => $unit->label,
                    'label_key' => $unit->labelKey,
                    'structural_type' => $unit->structuralType,
                    'expected_type' => $unit->expectedScalarType,
                    'semantics' => $unit->semantics,
                    'state' => $state,
                    'outcome' => $outcome,
                    'current_preview' => $this->preview($currentValue, $currentExists),
                    'imported_preview' => $this->preview($importedValue, $importedExists),
                    'selectable' => $selectable,
                    'selected' => $selectable && in_array($outcome, ['replace', 'add_new'], true),
                    'locked' => $locked,
                    'error' => $error,
                ];
            })
            ->values()
            ->all();
    }

    private function state(bool $currentExists, bool $importedExists, mixed $currentValue, mixed $importedValue): string
    {
        if (! $currentExists && $importedExists) {
            return 'added';
        }

        if ($currentExists && ! $importedExists) {
            return 'removed';
        }

        return $currentValue === $importedValue ? 'unchanged' : 'changed';
    }

    private function rowError(SettingsLifecycleUnit $unit, bool $importedExists, mixed $importedValue): ?string
    {
        if (! $importedExists && $unit->expectedScalarType !== null) {
            return __('admin.messages.settings_import_scalar_missing', ['path' => $unit->path]);
        }

        if (! $importedExists) {
            return null;
        }

        if ($this->schema->scalarTypeMatches($unit->expectedScalarType, $importedValue)) {
            return null;
        }

        return __('admin.messages.settings_import_scalar_type_mismatch', [
            'path' => $unit->path,
            'type' => $unit->expectedScalarType,
        ]);
    }

    private function outcome(string $state, bool $importedExists, SettingsImportMode $mode, bool $locked, bool $currentExists, mixed $currentValue, mixed $importedValue): string
    {
        if ($locked && $state !== 'unchanged') {
            return 'skip_locked';
        }

        if ($state === 'unchanged') {
            return 'skip_unchanged';
        }

        if ($mode === SettingsImportMode::AddOnly) {
            return $this->mergeEngine->shouldApplyAddOnly($currentValue, $currentExists, $importedValue, $importedExists)
                ? 'add_new'
                : 'skip_exists';
        }

        if (! $importedExists) {
            return 'remove';
        }

        return 'replace';
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
