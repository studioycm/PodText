<?php

namespace App\Support\SettingsLifecycle;

use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingsPackageImportAnalyzer
{
    public function __construct(
        private readonly SettingsLifecycleSchema $schema,
        private readonly SettingsLifecycleGroups $groups,
        private readonly PublicFrontConfigCache $cache,
        private readonly PublicFrontConfigValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $packageArray
     */
    public function analyzeArray(array $packageArray): SettingsPackageImportAnalysis
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

        return $this->analyze($package);
    }

    public function analyze(PublicSettingsPackage $package): SettingsPackageImportAnalysis
    {
        $errors = $this->packageErrors($package);

        if ($errors !== []) {
            return new SettingsPackageImportAnalysis($package, rows: [], errors: $errors);
        }

        $currentPayload = PublicSettingsPackage::fromCurrentSettings()->payload();
        $importedPayload = $package->payload();
        $schemaPayload = array_replace_recursive($currentPayload, $importedPayload);
        $warnings = $this->packageWarnings($package, $importedPayload);
        $rows = $this->rows($currentPayload, $importedPayload, $schemaPayload, $package->settingsGroup());
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
    private function rows(array $currentPayload, array $importedPayload, array $schemaPayload, string $group): array
    {
        return collect($this->schema->units($schemaPayload, $group))
            ->map(function (SettingsLifecycleUnit $unit) use ($currentPayload, $importedPayload): array {
                $currentExists = Arr::has($currentPayload, $unit->path);
                $importedExists = Arr::has($importedPayload, $unit->path);
                $currentValue = data_get($currentPayload, $unit->path);
                $importedValue = data_get($importedPayload, $unit->path);
                $state = $this->state($currentExists, $importedExists, $currentValue, $importedValue);
                $error = $this->rowError($unit, $importedExists, $importedValue);
                $selectable = $error === null && $state !== 'unchanged' && ($importedExists || str_contains($unit->path, '.'));

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
                    'outcome' => $error === null ? $this->outcome($state, $importedExists) : 'error',
                    'current_preview' => $this->preview($currentValue, $currentExists),
                    'imported_preview' => $this->preview($importedValue, $importedExists),
                    'selectable' => $selectable,
                    'selected' => $selectable && in_array($state, ['added', 'changed'], true),
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

    private function outcome(string $state, bool $importedExists): string
    {
        if ($state === 'unchanged') {
            return 'skip_unchanged';
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
