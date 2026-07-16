<?php

namespace App\Auth\LegacyRoleBackfill;

use Illuminate\Support\Str;
use JsonException;

final class PrivateArtifactRepository
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const NAME_PATTERN = '/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\.json\z/D';

    private readonly string $root;

    public function __construct(
        private readonly PrivacyHasher $hasher,
        ?string $localRoot = null,
    ) {
        $configuredRoot = $localRoot ?? config('filesystems.disks.local.root');

        if (! is_string($configuredRoot) || $configuredRoot === '') {
            throw new ArtifactException('The private artifact root is unavailable.');
        }

        $this->root = rtrim($configuredRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'authorization'
            .DIRECTORY_SEPARATOR.'authz1-c';
    }

    public function defaultReportName(): string
    {
        return now('UTC')->format('Ymd\THis\Z').'-'.Str::ulid().'-analysis.json';
    }

    public function reportNameFor(AnalysisReport $report): string
    {
        return 'analysis-'.$report->reportFingerprint().'.json';
    }

    public function operationName(string $reportFingerprint, string $state): string
    {
        if (! in_array($state, [
            'prepared', 'cache_invalidation_pending', 'cache_invalidated', 'complete',
            'rollback_prepared', 'rollback_complete',
        ], true)) {
            throw new ArtifactException('The operation journal state is invalid.');
        }

        return 'operation-'.$reportFingerprint.'.v2.'.$state.'.json';
    }

    public function backfillReceiptName(string $reportFingerprint): string
    {
        return 'backfill-'.$reportFingerprint.'.v2.json';
    }

    public function rollbackReceiptName(string $receiptFingerprint): string
    {
        return 'rollback-'.$receiptFingerprint.'.v2.json';
    }

    public function publishReport(AnalysisReport $report, ?string $name = null): string
    {
        $name ??= $this->defaultReportName();
        $this->publish('reports', $name, $report->toArray());

        return $name;
    }

    public function loadReport(string $name): AnalysisReport
    {
        return AnalysisReport::fromArray($this->load('reports', $name));
    }

    public function publishOperation(string $name, OperationJournal $journal): void
    {
        $this->publish('operations', $name, $journal->toArray());
    }

    public function loadOperation(string $name): OperationJournal
    {
        return OperationJournal::fromArray($this->load('operations', $name), $this->hasher);
    }

    public function publishRollbackOperation(string $name, RollbackOperationJournal $journal): void
    {
        $this->publish('operations', $name, $journal->toArray());
    }

    public function loadRollbackOperation(string $name): RollbackOperationJournal
    {
        return RollbackOperationJournal::fromArray($this->load('operations', $name), $this->hasher);
    }

    public function operationExists(string $name): bool
    {
        return $this->exists('operations', $name);
    }

    public function publishBackfillReceipt(string $name, BackfillReceipt $receipt): void
    {
        $this->publish('receipts', $name, $receipt->toArray());
    }

    public function loadBackfillReceipt(string $name): BackfillReceipt
    {
        return BackfillReceipt::fromArray($this->load('receipts', $name), $this->hasher);
    }

    public function backfillReceiptExists(string $name): bool
    {
        return $this->exists('receipts', $name);
    }

    public function publishRollbackReceipt(string $name, RollbackReceipt $receipt): void
    {
        $this->publish('receipts', $name, $receipt->toArray());
    }

    public function loadRollbackReceipt(string $name): RollbackReceipt
    {
        return RollbackReceipt::fromArray($this->load('receipts', $name), $this->hasher);
    }

    public function rollbackReceiptExists(string $name): bool
    {
        return $this->exists('receipts', $name);
    }

    /** @param array<string, mixed> $payload */
    private function publish(string $directory, string $name, array $payload): void
    {
        $this->assertName($name);
        $this->ensureDirectories();

        $lock = fopen($this->root.DIRECTORY_SEPARATOR.'.publish.lock', 'c+b');

        if ($lock === false) {
            throw new ArtifactException('The artifact publication lock could not be opened.');
        }

        chmod($this->root.DIRECTORY_SEPARATOR.'.publish.lock', 0600);

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new ArtifactException('The artifact publication lock could not be acquired.');
            }

            $target = $this->path($directory, $name);

            if (file_exists($target) || is_link($target)) {
                throw new ArtifactException('The immutable artifact destination already exists.');
            }

            $json = CanonicalJson::encode($payload);

            if (strlen($json) > self::MAX_BYTES) {
                throw new ArtifactException('The artifact exceeds the maximum allowed size.');
            }

            $json .= "\n";

            $temporary = $target.'.'.Str::ulid().'.tmp';
            $handle = fopen($temporary, 'x+b');

            if ($handle === false) {
                throw new ArtifactException('The artifact temporary file could not be created.');
            }

            try {
                if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                    throw new ArtifactException('The artifact could not be flushed.');
                }
            } finally {
                fclose($handle);
            }

            chmod($temporary, 0600);

            if (! rename($temporary, $target)) {
                @unlink($temporary);

                throw new ArtifactException('The artifact could not be published atomically.');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    private function load(string $directory, string $name): array
    {
        $this->assertName($name);
        $this->ensureDirectories();
        $path = $this->path($directory, $name);

        if (! is_file($path) || is_link($path)) {
            throw new ArtifactException('The requested immutable artifact does not exist.');
        }

        $this->assertPrivatePermissions($path);

        $size = filesize($path);

        if (! is_int($size) || $size < 1 || $size > self::MAX_BYTES + 1) {
            throw new ArtifactException('The artifact size is invalid.');
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new ArtifactException('The artifact could not be read.');
        }

        $payloadBytes = str_ends_with($contents, "\n") ? substr($contents, 0, -1) : $contents;

        if (strlen($payloadBytes) > self::MAX_BYTES) {
            throw new ArtifactException('The artifact size is invalid.');
        }

        try {
            $payload = json_decode($payloadBytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ArtifactException('The artifact JSON is invalid.');
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new ArtifactException('The artifact payload is invalid.');
        }

        if (in_array($payload['schema'] ?? null, [
            'podtext.authz1c.analysis.v1',
            'podtext.authz1c.operation.v1',
            'podtext.authz1c.backfill-receipt.v1',
            'podtext.authz1c.rollback-receipt.v1',
        ], true)) {
            throw ArtifactVersionException::v1();
        }

        return $payload;
    }

    private function exists(string $directory, string $name): bool
    {
        $this->assertName($name);
        $this->ensureDirectories();
        $path = $this->path($directory, $name);

        if (is_link($path)) {
            throw new ArtifactException('A symbolic-link artifact is not accepted.');
        }

        return is_file($path);
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->root, ...array_map(
            fn (string $child): string => $this->root.DIRECTORY_SEPARATOR.$child,
            ['reports', 'operations', 'receipts'],
        )] as $directory) {
            if (is_link($directory)) {
                throw new ArtifactException('Symbolic-link artifact directories are not accepted.');
            }

            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new ArtifactException('The private artifact directory could not be created.');
            }

            chmod($directory, 0700);
        }
    }

    private function assertName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || basename($name) !== $name || str_contains($name, '..')) {
            throw new ArtifactException('The artifact basename is invalid.');
        }
    }

    private function path(string $directory, string $name): string
    {
        return $this->root.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.$name;
    }

    private function assertPrivatePermissions(string $path): void
    {
        foreach ([$this->root, dirname($path)] as $directory) {
            $permissions = fileperms($directory);

            if (is_int($permissions) && ($permissions & 0777) !== 0700) {
                throw new ArtifactException('The private artifact directory permissions are invalid.');
            }
        }

        $permissions = fileperms($path);

        if (is_int($permissions) && ($permissions & 0777) !== 0600) {
            throw new ArtifactException('The private artifact file permissions are invalid.');
        }
    }
}
