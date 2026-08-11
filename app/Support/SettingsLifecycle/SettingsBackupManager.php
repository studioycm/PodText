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
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\LaravelSettings\SettingsContainer;

class SettingsBackupManager
{
    public function __construct(
        private readonly PublicFrontConfigCache $cache,
        private readonly PublicFrontConfigValidator $validator,
        private readonly SettingsBackupSnapshotManager $snapshots,
        private readonly SettingsLifecycleSchema $schema,
        private readonly SettingsImportMergeEngine $mergeEngine,
        private readonly PublicContentSettingsWriteCoordinator $writeCoordinator,
        private readonly SettingsPackageImportAnalyzer $importAnalyzer,
        private readonly SettingsMediaIdentityProjector $mediaProjector,
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

    public function createBeforeImport(?User $user = null, bool $withSnapshots = true): SettingsBackupVersion
    {
        return $this->create(
            source: SettingsBackupSource::BeforeImport,
            label: __('admin.messages.settings_backup_before_import_label'),
            user: $user,
            withSnapshots: $withSnapshots,
        );
    }

    /**
     * @param  array<int, string>|null  $snapshotFormats
     * @param  array<int, string>|null  $snapshotThemes
     */
    public function create(SettingsBackupSource $source, ?string $label = null, ?User $user = null, ?array $snapshotFormats = null, ?array $snapshotThemes = null, bool $withSnapshots = true): ?SettingsBackupVersion
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

        if ($withSnapshots && ! $this->shouldSkipSnapshots($source, $package, $backup)) {
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

        $this->writeCoordinator->transaction(function () use ($backup, $package, $user): void {
            $this->forgetFreshSettingsInstance();
            $this->createBeforeRestore($backup, $user);
            $this->applyPayload($package->payloadForApplication(), $user);
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

        return $this->writeCoordinator->transaction(fn (): SettingsImportReport => $this->importWithinCoordinatedTransaction(
            $package,
            $selectedPaths,
            $user,
            $mode,
            $sourceLabel,
        ));
    }

    /**
     * @param  array<int, string>  $selectedPaths
     */
    private function importWithinCoordinatedTransaction(
        PublicSettingsPackage $package,
        array $selectedPaths,
        ?User $user,
        SettingsImportMode $mode,
        ?string $sourceLabel,
    ): SettingsImportReport {
        $this->forgetFreshSettingsInstance();
        $analysis = $this->importAnalyzer->analyze($package, $mode);

        if ($analysis->refused()) {
            throw new RuntimeException(implode(' ', $analysis->errors));
        }

        $allowedPaths = $analysis->selectablePaths();
        $selectedPaths = $this->mediaProjector->expandSelectedPaths($selectedPaths);
        $selectedPaths = array_values(array_intersect($selectedPaths, $allowedPaths));

        /*
         * The merge is pure array work on in-transaction state, so computing
         * it first does not change what the BeforeImport backup captures.
         * Candidate-empty is a strict no-op (the definitive applied list is a
         * post-normalization subset of the candidates): keep the audit backup
         * row + report, but schedule no snapshots and skip the unconditional
         * save→SettingsSaved→createSystem cycle.
         */
        $merge = $this->mergeSelectedPayload($package->payloadForApplication(), $selectedPaths, $mode);
        $isNoOp = $merge['candidate_paths'] === [];
        $beforeImportBackup = $this->createBeforeImport($user, withSnapshots: ! $isNoOp);
        $appliedPaths = $isNoOp ? [] : $this->applyMergedSelection($merge, $user);
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

        $this->forgetPublicFrontState();

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

        return PublicSettingsPackage::canonicalPayloadJsonWithoutImportLocks($latest->package()->payload())
            === PublicSettingsPackage::canonicalPayloadJsonWithoutImportLocks($package->payload());
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

    /**
     * Raw assignment, no cast layer: safe only while every
     * PublicContentSettings property is raw-assignable from its JSON payload —
     * the invariant SettingsRowInvariantTest pins. An enum-typed property here
     * would TypeError, which is why this writer must not widen to
     * AdminUxSettings.
     */
    private function applyPayload(array $payload, ?User $user): array
    {
        $this->forgetFreshSettingsInstance();
        $settings = app(PublicContentSettings::class);
        $payload = MultiTranscriptionSurfaces::overlayUnauthorizedSettings(
            $this->normalizePayloadForApply($payload),
            PublicContentSettings::class,
            $user,
        );

        foreach ($payload as $property => $value) {
            if (property_exists($settings, $property)) {
                $settings->{$property} = $value;
            }
        }

        $settings->save();

        return $payload;
    }

    /**
     * Pure array work on in-transaction state: computes the merged payload
     * and the pre-normalization candidate applied paths without touching
     * settings — which is what licenses running it before createBeforeImport.
     *
     * @param  array<string, mixed>  $importedPayload
     * @param  array<int, string>  $selectedPaths
     * @return array{payload: array<string, mixed>, original_payload: array<string, mixed>, candidate_paths: array<int, string>}
     */
    private function mergeSelectedPayload(array $importedPayload, array $selectedPaths, SettingsImportMode $mode): array
    {
        $currentPayload = PublicSettingsPackage::fromCurrentSettings()->payload();
        $originalPayload = $currentPayload;
        $candidatePaths = [];

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
                $candidatePaths[] = $path;

                continue;
            }

            if ($mode === SettingsImportMode::Replace && str_contains($path, '.')) {
                $this->schema->forgetValue($currentPayload, $path);
                $candidatePaths[] = $path;
            }
        }

        return [
            'payload' => $currentPayload,
            'original_payload' => $originalPayload,
            'candidate_paths' => $candidatePaths,
        ];
    }

    /**
     * @param  array{payload: array<string, mixed>, original_payload: array<string, mixed>, candidate_paths: array<int, string>}  $merge
     * @return array<int, string>
     */
    private function applyMergedSelection(array $merge, ?User $user): array
    {
        $appliedPayload = $this->applyPayload($merge['payload'], $user);

        return collect($merge['candidate_paths'])
            ->filter(fn (string $path): bool => $this->schema->value($merge['original_payload'], $path) !== $this->schema->value($appliedPayload, $path))
            ->values()
            ->all();
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

    private function forgetFreshSettingsInstance(): void
    {
        app()->forgetInstance(PublicContentSettings::class);
        app(SettingsContainer::class)->clearCache();
    }
}
