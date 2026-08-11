<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\SettingsBackupSource;
use App\Jobs\SettingsBackupSnapshotJob;
use App\Models\SettingsBackupSnapshot;
use App\Models\SettingsBackupVersion;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
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
            $fullTargets = $this->manifest->fullTargets();
            $sourceBackup = $this->findFullSetSourceBackup($backup, $fullTargets, $resolvedThemes, $resolvedFormats);

            if ($sourceBackup !== null) {
                $backup->forceFill([
                    'full_snapshot_source_backup_id' => $sourceBackup->getKey(),
                ])->save();
            } else {
                $fullRows = collect($fullTargets)
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

    /**
     * Process every given pending row of ONE backup through a single script
     * invocation. The job-file write and the spawn are sequential statements
     * in this one process — the write-then-spawn ordering property (register
     * 2.6) that one-row-per-process used to provide for free and the batch
     * tests now assert — and per-target truth comes back through the results
     * file, so one target's failure marks exactly its own row.
     *
     * @param  Collection<int, SettingsBackupSnapshot>  $snapshots
     */
    public function processBatch(Collection $snapshots): void
    {
        if ($snapshots->isEmpty()) {
            return;
        }

        $backup = $snapshots->first()->loadMissing('backup')->backup;
        $settings = $this->settingsBackupsConfig($backup);

        $snapshots->each(function (SettingsBackupSnapshot $snapshot): void {
            $snapshot->forceFill([
                'path' => $this->outputPath($snapshot),
                'status' => SettingsBackupSnapshot::STATUS_PENDING,
                'error' => null,
            ])->save();

            $this->disk()->makeDirectory(dirname((string) $snapshot->path));
        });

        $batchId = (string) Str::uuid();
        $jobPath = "{$this->backupDirectory($backup->getKey())}/jobs/batch-{$batchId}.json";
        $resultsPath = "{$this->backupDirectory($backup->getKey())}/jobs/batch-{$batchId}.results.json";
        $this->disk()->makeDirectory(dirname($jobPath));
        $this->disk()->put($jobPath, json_encode([
            'targets' => $snapshots
                ->map(fn (SettingsBackupSnapshot $snapshot): array => $this->targetPayload($snapshot, $settings))
                ->values()
                ->all(),
            'results_path' => $this->disk()->path($resultsPath),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        try {
            $result = Process::path(base_path())
                ->timeout($this->batchProcessTimeout($snapshots->count()))
                ->run([
                    'node',
                    'scripts/settings-snapshots.mjs',
                    $this->disk()->path($jobPath),
                ]);
        } catch (Throwable $exception) {
            $this->failSnapshots($snapshots, str($exception->getMessage())->limit(2000)->toString());
            $this->deleteBatchFiles($jobPath, $resultsPath);

            return;
        }

        $this->applyBatchResults($snapshots, $resultsPath, $result);
        $this->deleteBatchFiles($jobPath, $resultsPath);
    }

    /**
     * The per-invocation batch pair never persists: the per-row `error`
     * columns already carry the post-mortem signal, and every retained backup
     * (Manual is keep-forever by default; bounded sources retain up to their
     * ceiling) would otherwise accumulate one pair per invocation for its
     * whole life (review finding 2 on 476c508).
     */
    private function deleteBatchFiles(string $jobPath, string $resultsPath): void
    {
        $this->disk()->delete([$jobPath, $resultsPath]);
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
        $snapshots = $backup->effectiveSnapshots()
            ->filter(fn (SettingsBackupSnapshot $snapshot): bool => $snapshot->status === SettingsBackupSnapshot::STATUS_DONE
                && filled($snapshot->path)
                && $this->disk()->exists((string) $snapshot->path));

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

    /**
     * Whether this source's own retention ceiling means "keep forever", so
     * that backups of it are never prune candidates. The per-source config
     * keys mirror the enum values (`retention_manual`, etc.); an unmapped
     * source reads as keep-forever, which fails toward a re-render — the
     * direction that costs a spawn instead of pinning an owner.
     */
    private function isKeepForeverSource(SettingsBackupSource|string|null $source): bool
    {
        $sourceValue = $source instanceof SettingsBackupSource ? $source->value : $source;

        return (int) config("settings-backups.retention_{$sourceValue}", 0) <= 0;
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
     * Full-set dedup: a full-source backup whose payload-minus-locks is
     * byte-identical to a sibling that already OWNS a DONE full set covering
     * every requested target×theme×format borrows that set instead of
     * re-rendering it. Owners must own their rows (a borrower has none), so
     * borrow chains cannot form; the newest owner wins; the scan is bounded
     * to the newest candidates because missing a match only costs a render.
     *
     * Accepted trade (review finding 3 on 476c508): the borrow is live — the
     * borrower's gallery always shows the owner's CURRENT rows, checked as
     * complete-and-DONE only at borrow time. If an owner row later degrades
     * (a failed recapture), the borrower surfaces that failure and cannot
     * re-render a set of its own; the owner's next successful retry heals
     * both galleries, and a stuck-failing owner is equally broken in its own
     * gallery — so recovery is operational there, not structural here. The
     * unattended counterpart is stricter: retention (register 1.9) refuses to
     * prune an owner while a surviving backup still borrows its set.
     *
     * Two independent refusals gate borrowing (register 1.9), each with its
     * own reason so that removing either is a deliberate act:
     * **retention correctness** — a source whose own ceiling is keep-forever
     * is never a prune candidate, so as a borrower it would pin its mortal
     * owner permanently; keying this on the ceiling rather than a source name
     * is what makes "every borrower is mortal" (prune()'s bounded-deferral
     * premise) true under any configuration. **Durability** — Manual backups
     * stay self-contained whatever their ceiling, because retention protects
     * a live-borrowed owner from pruning but not from an admin deleting it by
     * hand, and a curated keeper should not lose its gallery to an unrelated
     * deletion. Under the default configuration the two coincide on Manual.
     *
     * @param  array<int, array{screen_key: string, url: string}>  $fullTargets
     * @param  array<int, string>  $themes
     * @param  array<int, string>  $formats
     */
    private function findFullSetSourceBackup(SettingsBackupVersion $backup, array $fullTargets, array $themes, array $formats): ?SettingsBackupVersion
    {
        $sourceValue = $backup->source instanceof SettingsBackupSource ? $backup->source->value : $backup->source;

        // Retention correctness: a borrower that is never a prune candidate
        // would pin its mortal owner past that owner's ceiling forever.
        if ($this->isKeepForeverSource($sourceValue)) {
            return null;
        }

        // Durability, and deliberately independent of the rule above: prune()
        // refuses to delete a live-borrowed owner, but a human deleting that
        // owner still nulls the pointer and empties the borrower's gallery
        // with no retry offered. A Manual backup exists because an admin said
        // "keep this", so it owns its files at the cost of one render even
        // when a finite ceiling would otherwise make it an eligible borrower.
        if ($sourceValue === SettingsBackupSource::Manual->value) {
            return null;
        }

        $needed = collect($fullTargets)
            ->flatMap(fn (array $target): Collection => collect($themes)
                ->flatMap(fn (string $theme): Collection => collect($formats)
                    ->map(fn (string $format): string => implode('|', [
                        $target['screen_key'],
                        $theme,
                        SettingsBackupSnapshot::VIEWPORT_DESKTOP,
                        $format,
                    ]))))
            ->all();

        if ($needed === []) {
            return null;
        }

        $payloadJson = PublicSettingsPackage::canonicalPayloadJsonWithoutImportLocks($backup->package()->payload());

        return SettingsBackupVersion::query()
            ->where('scope', $backup->scope)
            ->whereKeyNot($backup->getKey())
            ->whereHas('snapshots', fn ($query) => $query
                ->where('kind', SettingsBackupSnapshot::KIND_FULL)
                ->where('status', SettingsBackupSnapshot::STATUS_DONE))
            ->latest('id')
            ->limit(10)
            ->get()
            ->first(function (SettingsBackupVersion $candidate) use ($payloadJson, $needed): bool {
                if (PublicSettingsPackage::canonicalPayloadJsonWithoutImportLocks($candidate->package()->payload()) !== $payloadJson) {
                    return false;
                }

                $owned = $candidate->snapshots()
                    ->where('kind', SettingsBackupSnapshot::KIND_FULL)
                    ->where('status', SettingsBackupSnapshot::STATUS_DONE)
                    ->get()
                    ->map(fn (SettingsBackupSnapshot $snapshot): string => implode('|', [
                        $snapshot->screen_key,
                        $snapshot->theme,
                        $snapshot->viewport,
                        $snapshot->format,
                    ]))
                    ->all();

                return array_diff($needed, $owned) === [];
            });
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
     * The per-target spawn budget scales with the batch size, capped just
     * under the queue job timeout so a timed-out spawn still dies inside the
     * job and its per-target results can be mapped.
     */
    private function batchProcessTimeout(int $targetCount): int
    {
        $perTarget = (int) config('settings-backups.snapshot_process_timeout', self::PROCESS_TIMEOUT);
        $jobTimeout = (int) config('settings-backups.snapshot_job_timeout', 1800);

        return min($perTarget * max(1, $targetCount), max($perTarget, $jobTimeout - 60));
    }

    /**
     * @param  Collection<int, SettingsBackupSnapshot>  $snapshots
     */
    private function applyBatchResults(Collection $snapshots, string $resultsPath, ProcessResult $result): void
    {
        $resultsById = $this->readBatchResults($resultsPath);
        $processError = str(trim($result->errorOutput()) ?: trim($result->output()) ?: 'Snapshot process failed.')
            ->limit(2000)
            ->toString();

        $snapshots->each(function (SettingsBackupSnapshot $snapshot) use ($resultsById, $processError, $result): void {
            $entry = $resultsById->get($snapshot->getKey());

            if (is_array($entry) && ($entry['ok'] ?? false) === true) {
                $snapshot->forceFill([
                    'status' => SettingsBackupSnapshot::STATUS_DONE,
                    'error' => null,
                ])->save();

                return;
            }

            $error = is_array($entry) && filled($entry['error'] ?? null)
                ? str((string) $entry['error'])->limit(2000)->toString()
                : ($result->successful()
                    ? 'The snapshot process reported no result for this target.'
                    : $processError);

            $snapshot->forceFill([
                'status' => SettingsBackupSnapshot::STATUS_FAILED,
                'error' => $error,
            ])->save();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function readBatchResults(string $resultsPath): Collection
    {
        if (! $this->disk()->exists($resultsPath)) {
            return collect();
        }

        try {
            $decoded = json_decode((string) $this->disk()->get($resultsPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return collect();
        }

        return collect((array) ($decoded['results'] ?? []))
            ->filter(fn (mixed $entry): bool => is_array($entry) && isset($entry['snapshot_id']))
            ->keyBy(fn (array $entry): int => (int) $entry['snapshot_id']);
    }

    /**
     * @param  Collection<int, SettingsBackupSnapshot>  $snapshots
     */
    private function failSnapshots(Collection $snapshots, string $error): void
    {
        $snapshots->each(fn (SettingsBackupSnapshot $snapshot) => $snapshot->forceFill([
            'status' => SettingsBackupSnapshot::STATUS_FAILED,
            'error' => $error,
        ])->save());
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function targetPayload(SettingsBackupSnapshot $snapshot, array $settings): array
    {
        $maxWidth = (int) ($settings['thumbnail_max_width'] ?? PublicFrontConfigRegistry::defaults()['settings_backups']['thumbnail_max_width']);
        $thumbnailWidth = max(1, $maxWidth);
        $thumbnailHeight = max(1, (int) round($thumbnailWidth * (self::THUMBNAIL_HEIGHT / self::VIEWPORT_WIDTH)));
        $isThumbnail = $snapshot->kind === SettingsBackupSnapshot::KIND_THUMBNAIL;
        $width = self::VIEWPORT_WIDTH;
        $height = self::VIEWPORT_HEIGHT;

        return [
            'snapshot_id' => $snapshot->getKey(),
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
                $snapshot->format => $this->disk()->path((string) $snapshot->path),
            ],
        ];
    }

    private function outputPath(SettingsBackupSnapshot $snapshot): string
    {
        return "{$this->backupDirectory($snapshot->backup_id)}/{$snapshot->kind}/{$snapshot->screen_key}-{$snapshot->theme}-{$snapshot->viewport}.{$snapshot->format}";
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
