<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\SettingsBackupSource;
use App\Jobs\SettingsBackupSnapshotJob;
use App\Models\SettingsBackupSnapshot;
use App\Models\SettingsBackupVersion;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class SettingsBackupSnapshotManager
{
    private const PROCESS_TIMEOUT = 120;

    private const THUMBNAIL_HEIGHT = 900;

    private const VIEWPORT_HEIGHT = 900;

    private const VIEWPORT_WIDTH = 1440;

    public function __construct(
        private readonly SettingsBackupSnapshotManifest $manifest,
    ) {}

    /**
     * @param  array<int, string>|null  $formats
     * @param  array<int, string>|null  $themes
     */
    public function scheduleForBackup(SettingsBackupVersion $backup, ?array $formats = null, ?array $themes = null): void
    {
        if (! Schema::hasTable('settings_backup_snapshots')) {
            return;
        }

        $settings = $this->settingsBackupsConfig($backup);
        $resolvedThemes = $this->resolveThemes($themes, $settings);
        $resolvedFormats = $this->resolveFormats($formats, $settings);
        $thumbnailTheme = $resolvedThemes[0] ?? 'light';

        $rows = collect($this->manifest->thumbnailTargets())
            ->map(fn (array $target): SettingsBackupSnapshot => $this->upsertPendingSnapshot(
                backup: $backup,
                target: $target,
                theme: $thumbnailTheme,
                kind: SettingsBackupSnapshot::KIND_THUMBNAIL,
                format: SettingsBackupSnapshot::FORMAT_PNG,
            ));

        if ($this->sourceGetsFullSnapshots($backup->source)) {
            $fullRows = collect($this->manifest->fullTargets())
                ->flatMap(fn (array $target): Collection => collect($resolvedThemes)
                    ->flatMap(fn (string $theme): Collection => collect($resolvedFormats)
                        ->map(fn (string $format): SettingsBackupSnapshot => $this->upsertPendingSnapshot(
                            backup: $backup,
                            target: $target,
                            theme: $theme,
                            kind: SettingsBackupSnapshot::KIND_FULL,
                            format: $format,
                        ))));

            $rows = $rows->merge($fullRows);
        }

        if ($rows->isEmpty()) {
            return;
        }

        SettingsBackupSnapshotJob::dispatch($backup->getKey());
    }

    public function retry(SettingsBackupSnapshot $snapshot): void
    {
        $snapshot->forceFill([
            'status' => SettingsBackupSnapshot::STATUS_PENDING,
            'error' => null,
        ])->save();

        SettingsBackupSnapshotJob::dispatch($snapshot->backup_id, [$snapshot->getKey()]);
    }

    public function processSnapshot(SettingsBackupSnapshot $snapshot): void
    {
        $snapshot->loadMissing('backup');

        $path = $this->outputPath($snapshot);
        $snapshot->forceFill([
            'path' => $path,
            'status' => SettingsBackupSnapshot::STATUS_PENDING,
            'error' => null,
        ])->save();

        $jobPath = $this->jobPath($snapshot);
        $this->disk()->makeDirectory(dirname($path));
        $this->disk()->makeDirectory(dirname($jobPath));
        $this->disk()->put($jobPath, json_encode($this->processPayload($snapshot, $path), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        try {
            $result = Process::path(base_path())
                ->timeout((int) config('settings-backups.snapshot_process_timeout', self::PROCESS_TIMEOUT))
                ->run([
                    'node',
                    'scripts/settings-snapshots.mjs',
                    $this->disk()->path($jobPath),
                ]);
        } catch (Throwable $exception) {
            $snapshot->forceFill([
                'status' => SettingsBackupSnapshot::STATUS_FAILED,
                'error' => str($exception->getMessage())->limit(2000)->toString(),
            ])->save();

            return;
        }

        if ($result->successful()) {
            $snapshot->forceFill([
                'status' => SettingsBackupSnapshot::STATUS_DONE,
                'error' => null,
            ])->save();

            return;
        }

        $snapshot->forceFill([
            'status' => SettingsBackupSnapshot::STATUS_FAILED,
            'error' => str(trim($result->errorOutput()) ?: trim($result->output()) ?: 'Snapshot process failed.')->limit(2000)->toString(),
        ])->save();
    }

    /**
     * @param  iterable<int|string>  $backupIds
     */
    public function deleteFilesForBackupIds(iterable $backupIds): void
    {
        collect($backupIds)
            ->filter(fn (int|string|null $backupId): bool => filled($backupId))
            ->each(fn (int|string $backupId): bool => $this->disk()->deleteDirectory($this->backupDirectory((int) $backupId)));
    }

    public function deleteBackup(SettingsBackupVersion $backup): void
    {
        $this->deleteFilesForBackupIds([$backup->getKey()]);
        $backup->delete();
    }

    public function zipResponse(SettingsBackupVersion $backup): BinaryFileResponse
    {
        $snapshots = $backup->snapshots()
            ->where('status', SettingsBackupSnapshot::STATUS_DONE)
            ->whereNotNull('path')
            ->orderBy('screen_key')
            ->orderBy('theme')
            ->orderBy('kind')
            ->orderBy('format')
            ->get()
            ->filter(fn (SettingsBackupSnapshot $snapshot): bool => $this->disk()->exists((string) $snapshot->path));

        abort_if($snapshots->isEmpty(), 404);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'settings-backup-snapshots-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary snapshot zip.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the temporary snapshot zip.');
        }

        $snapshots->each(function (SettingsBackupSnapshot $snapshot) use ($zip): void {
            $zip->addFile(
                $this->disk()->path((string) $snapshot->path),
                "{$snapshot->screen_key}/{$snapshot->theme}/{$snapshot->kind}-{$snapshot->viewport}.{$snapshot->format}",
            );
        });

        $zip->close();

        return response()
            ->download($temporaryPath, "settings-backup-{$backup->getKey()}-snapshots.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * @return array<int, string>
     */
    private function resolveFormats(?array $formats, array $settings): array
    {
        return $this->finiteList(
            $formats ?? $settings['snapshot_formats'] ?? [],
            PublicFrontConfigRegistry::settingsBackupSnapshotFormats(),
            PublicFrontConfigRegistry::defaults()['settings_backups']['snapshot_formats'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolveThemes(?array $themes, array $settings): array
    {
        return $this->finiteList(
            $themes ?? $settings['snapshot_themes'] ?? [],
            PublicFrontConfigRegistry::settingsBackupSnapshotThemes(),
            PublicFrontConfigRegistry::defaults()['settings_backups']['snapshot_themes'],
        );
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function finiteList(array $values, array $allowed, array $fallback): array
    {
        $resolved = collect($values)
            ->map(fn (mixed $value): string => (string) $value)
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $resolved === [] ? $fallback : $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsBackupsConfig(SettingsBackupVersion $backup): array
    {
        return $backup->package()->payload()['settings_backups']
            ?? PublicFrontConfigRegistry::defaults()['settings_backups'];
    }

    private function sourceGetsFullSnapshots(SettingsBackupSource|string|null $source): bool
    {
        $sourceValue = $source instanceof SettingsBackupSource ? $source->value : $source;

        return in_array($sourceValue, [
            SettingsBackupSource::Manual->value,
            SettingsBackupSource::BeforeImport->value,
            SettingsBackupSource::BeforeRestore->value,
        ], true);
    }

    /**
     * @param  array{screen_key: string, url: string}  $target
     */
    private function upsertPendingSnapshot(
        SettingsBackupVersion $backup,
        array $target,
        string $theme,
        string $kind,
        string $format,
    ): SettingsBackupSnapshot {
        return SettingsBackupSnapshot::query()->updateOrCreate(
            [
                'backup_id' => $backup->getKey(),
                'screen_key' => $target['screen_key'],
                'theme' => $theme,
                'viewport' => SettingsBackupSnapshot::VIEWPORT_DESKTOP,
                'kind' => $kind,
                'format' => $format,
            ],
            [
                'resolved_url' => $target['url'],
                'path' => null,
                'status' => SettingsBackupSnapshot::STATUS_PENDING,
                'error' => null,
            ],
        );
    }

    /**
     * @return array{targets: array<int, array<string, mixed>>}
     */
    private function processPayload(SettingsBackupSnapshot $snapshot, string $path): array
    {
        $settings = $this->settingsBackupsConfig($snapshot->backup);
        $maxWidth = (int) ($settings['thumbnail_max_width'] ?? PublicFrontConfigRegistry::defaults()['settings_backups']['thumbnail_max_width']);
        $thumbnailWidth = max(1, $maxWidth);
        $thumbnailHeight = max(1, (int) round($thumbnailWidth * (self::THUMBNAIL_HEIGHT / self::VIEWPORT_WIDTH)));
        $isThumbnail = $snapshot->kind === SettingsBackupSnapshot::KIND_THUMBNAIL;
        $width = self::VIEWPORT_WIDTH;
        $height = self::VIEWPORT_HEIGHT;

        return [
            'targets' => [
                [
                    'url' => $snapshot->resolved_url,
                    'screen_key' => $snapshot->screen_key,
                    'theme' => $snapshot->theme,
                    'formats' => [$snapshot->format],
                    'kind' => $snapshot->kind,
                    'mode' => $isThumbnail ? 'thumb' : 'full',
                    'max_width' => $maxWidth,
                    'device_scale_factor' => $isThumbnail
                        ? round($thumbnailWidth / self::VIEWPORT_WIDTH, 6)
                        : 1,
                    'viewport' => [
                        'name' => $snapshot->viewport,
                        'width' => $width,
                        'height' => $height,
                    ],
                    'fallback_viewport' => [
                        'name' => "{$snapshot->viewport}-thumbnail-fallback",
                        'width' => $isThumbnail ? $thumbnailWidth : $width,
                        'height' => $isThumbnail ? $thumbnailHeight : $height,
                    ],
                    'outputs' => [
                        $snapshot->format => $this->disk()->path($path),
                    ],
                ],
            ],
        ];
    }

    private function outputPath(SettingsBackupSnapshot $snapshot): string
    {
        return "{$this->backupDirectory($snapshot->backup_id)}/{$snapshot->kind}/{$snapshot->screen_key}-{$snapshot->theme}-{$snapshot->viewport}.{$snapshot->format}";
    }

    private function jobPath(SettingsBackupSnapshot $snapshot): string
    {
        return "{$this->backupDirectory($snapshot->backup_id)}/jobs/{$snapshot->getKey()}.json";
    }

    private function backupDirectory(int|string $backupId): string
    {
        return "settings-backups/{$backupId}";
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
