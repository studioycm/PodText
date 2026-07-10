<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\SettingsBackupSource;
use App\Enums\SettingsImportMode;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SettingsBackupManager
{
    public function __construct(
        private readonly PublicFrontConfigCache $cache,
        private readonly PublicFrontConfigValidator $validator,
        private readonly SettingsBackupSnapshotManager $snapshots,
        private readonly SettingsLifecycleSchema $schema,
        private readonly SettingsImportMergeEngine $mergeEngine,
    ) {}

    /**
     * @param  array<int, string>|null  $snapshotFormats
     * @param  array<int, string>|null  $snapshotThemes
     */
    public function createManual(?string $label = null, ?User $user = null, ?array $snapshotFormats = null, ?array $snapshotThemes = null): SettingsBackupVersion
    {
        return $this->create(SettingsBackupSource::Manual, $label, $user, $snapshotFormats, $snapshotThemes);
    }

    public function createSystem(): ?SettingsBackupVersion
    {
        return $this->create(SettingsBackupSource::System);
    }

    public function createBeforeRestore(SettingsBackupVersion $backup, ?User $user = null): SettingsBackupVersion
    {
        return $this->create(
            source: SettingsBackupSource::BeforeRestore,
            label: __('admin.messages.settings_backup_before_restore_label', ['id' => $backup->getKey()]),
            user: $user,
        );
    }

    public function createBeforeImport(?User $user = null): SettingsBackupVersion
    {
        return $this->create(
            source: SettingsBackupSource::BeforeImport,
            label: __('admin.messages.settings_backup_before_import_label'),
            user: $user,
        );
    }

    /**
     * @param  array<int, string>|null  $snapshotFormats
     * @param  array<int, string>|null  $snapshotThemes
     */
    public function create(SettingsBackupSource $source, ?string $label = null, ?User $user = null, ?array $snapshotFormats = null, ?array $snapshotThemes = null): ?SettingsBackupVersion
    {
        if (! Schema::hasTable('settings_backup_versions')) {
            if ($source === SettingsBackupSource::System) {
                return null;
            }

            throw new RuntimeException('The settings_backup_versions table does not exist.');
        }

        $package = PublicSettingsPackage::fromCurrentSettings();
        $payloadHash = $package->payloadHash();

        if ($source === SettingsBackupSource::System && $this->shouldSkipSystemBackup($package->settingsGroup(), $payloadHash)) {
            return null;
        }

        $backup = SettingsBackupVersion::query()->create([
            'scope' => $package->settingsGroup(),
            'label' => filled($label) ? trim((string) $label) : null,
            'payload_json' => $package->toJson(),
            'checksum' => $package->checksum(),
            'payload_hash' => $payloadHash,
            'source' => $source,
            'created_by_user_id' => $user?->getKey(),
        ]);

        if (! $this->shouldSkipSnapshots($source, $package, $backup)) {
            $this->snapshots->scheduleForBackup($backup, $snapshotFormats, $snapshotThemes);
        }

        $this->prune($package->settingsGroup());

        return $backup;
    }

    public function compare(SettingsBackupVersion $backup): SettingsPackageDiff
    {
        return SettingsPackageDiff::between(
            $backup->package()->payload(),
            PublicSettingsPackage::fromCurrentSettings()->payload(),
        );
    }

    public function restore(SettingsBackupVersion $backup, ?User $user = null): void
    {
        $package = $backup->package();

        $this->validatePackageForRestore($package);

        DB::transaction(function () use ($backup, $package, $user): void {
            $this->createBeforeRestore($backup, $user);
            $this->applyPayload($package->payload());
        });

        $this->forgetPublicFrontState();
    }

    /**
     * @param  array<int, string>  $selectedPaths
     */
    public function import(
        PublicSettingsPackage $package,
        array $selectedPaths,
        ?User $user = null,
        SettingsImportMode|string|null $mode = SettingsImportMode::Replace,
        ?string $sourceLabel = null,
    ): SettingsImportReport {
        $mode = SettingsImportMode::normalize($mode);
        $this->validatePackageForRestore($package);
        $analysis = app(SettingsPackageImportAnalyzer::class)->analyze($package, $mode);

        if ($analysis->refused()) {
            throw new RuntimeException(implode(' ', $analysis->errors));
        }

        $allowedPaths = $analysis->selectablePaths();
        $selectedPaths = array_values(array_intersect($selectedPaths, $allowedPaths));
        $appliedPaths = [];
        $report = null;

        DB::transaction(function () use ($analysis, $package, $selectedPaths, $user, $mode, $sourceLabel, &$appliedPaths, &$report): void {
            $beforeImportBackup = $this->createBeforeImport($user);
            $appliedPaths = $this->applySelectedPayload($package->payload(), $selectedPaths, $mode);
            $report = SettingsImportReport::fromAnalysis(
                analysis: $analysis,
                selectedPaths: $selectedPaths,
                appliedPaths: $appliedPaths,
                beforeImportBackup: $beforeImportBackup,
                mode: $mode,
                sourceLabel: $sourceLabel,
            );

            $beforeImportBackup->update([
                'import_report' => $report->toArray(),
            ]);
        });

        $this->forgetPublicFrontState();

        if (! $report instanceof SettingsImportReport) {
            throw new RuntimeException('The settings import report was not created.');
        }

        return $report;
    }

    public function prune(string $scope): void
    {
        $retention = max(1, (int) config('settings-backups.retention', 25));
        $idsToPrune = SettingsBackupVersion::query()
            ->where('scope', $scope)
            ->where('source', SettingsBackupSource::System->value)
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($retention)
            ->values();

        if ($idsToPrune->isEmpty()) {
            return;
        }

        SettingsBackupVersion::query()
            ->whereKey($idsToPrune)
            ->delete();

        DB::afterCommit(fn () => $this->snapshots->deleteFilesForBackupIds($idsToPrune));
    }

    private function shouldSkipSystemBackup(string $scope, string $payloadHash): bool
    {
        $latest = SettingsBackupVersion::query()
            ->where('scope', $scope)
            ->latest('id')
            ->first();

        if ($latest?->payload_hash === $payloadHash) {
            return true;
        }

        return SettingsBackupVersion::query()
            ->where('scope', $scope)
            ->where('payload_hash', $payloadHash)
            ->exists();
    }

    private function shouldSkipSnapshots(SettingsBackupSource $source, PublicSettingsPackage $package, SettingsBackupVersion $backup): bool
    {
        if ($source !== SettingsBackupSource::System) {
            return false;
        }

        $latest = SettingsBackupVersion::query()
            ->where('scope', $package->settingsGroup())
            ->whereKeyNot($backup->getKey())
            ->latest('id')
            ->first();

        if (! $latest) {
            return false;
        }

        return PublicSettingsPackage::canonicalPayloadJson($this->payloadWithoutImportLocks($latest->package()->payload()))
            === PublicSettingsPackage::canonicalPayloadJson($this->payloadWithoutImportLocks($package->payload()));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadWithoutImportLocks(array $payload): array
    {
        unset($payload['import_locks']);

        return $payload;
    }

    private function validatePackageForRestore(PublicSettingsPackage $package): void
    {
        if (! $package->checksumValid()) {
            throw new RuntimeException(__('admin.messages.settings_backup_checksum_invalid'));
        }

        if ($package->schemaVersion() > PublicSettingsPackage::SCHEMA_VERSION) {
            throw new RuntimeException(__('admin.messages.settings_backup_schema_unsupported'));
        }

        if ($package->settingsGroup() !== PublicContentSettings::group()) {
            throw new RuntimeException(__('admin.messages.settings_backup_scope_invalid'));
        }
    }

    private function applyPayload(array $payload): void
    {
        $settings = app(PublicContentSettings::class);

        foreach ($this->normalizePayloadForApply($payload) as $property => $value) {
            if (property_exists($settings, $property)) {
                $settings->{$property} = $value;
            }
        }

        $settings->save();
    }

    /**
     * @param  array<string, mixed>  $importedPayload
     * @param  array<int, string>  $selectedPaths
     * @return array<int, string>
     */
    private function applySelectedPayload(array $importedPayload, array $selectedPaths, SettingsImportMode $mode): array
    {
        $currentPayload = PublicSettingsPackage::fromCurrentSettings()->payload();
        $appliedPaths = [];

        foreach (array_values(array_unique($selectedPaths)) as $path) {
            $currentExists = $this->schema->valueExists($currentPayload, $path);
            $importedExists = $this->schema->valueExists($importedPayload, $path);

            if ($importedExists) {
                $currentValue = $this->schema->value($currentPayload, $path);
                $importedValue = $this->schema->value($importedPayload, $path);
                $mergedValue = $this->mergeEngine->merge($mode, $currentValue, $currentExists, $importedValue, $importedExists);

                if ($mergedValue === $currentValue) {
                    continue;
                }

                $this->schema->setValue($currentPayload, $path, $mergedValue);
                $appliedPaths[] = $path;

                continue;
            }

            if ($mode === SettingsImportMode::Replace && str_contains($path, '.')) {
                $this->schema->forgetValue($currentPayload, $path);
                $appliedPaths[] = $path;
            }
        }

        $this->applyPayload($currentPayload);

        return $appliedPaths;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayloadForApply(array $payload): array
    {
        $settingsKeys = PublicFrontConfigRegistry::settingsKeys();
        $settingGroups = array_intersect_key($payload, array_flip($settingsKeys));

        if ($settingGroups === []) {
            return $payload;
        }

        $normalizedGroups = $this->validator->validate($settingGroups)->config();

        foreach (array_keys($settingGroups) as $key) {
            $payload[$key] = $normalizedGroups[$key] ?? PublicFrontConfigRegistry::defaults()[$key];
        }

        return $payload;
    }

    private function forgetPublicFrontState(): void
    {
        $this->cache->forget();
        app()->forgetInstance(PublicContentSettings::class);
        app()->forgetInstance(PublicFrontRenderContext::class);
        app()->forgetInstance(PublicTranscriptionPolicy::class);
    }
}
