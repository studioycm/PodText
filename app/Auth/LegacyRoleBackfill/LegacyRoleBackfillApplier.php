<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class LegacyRoleBackfillApplier
{
    public function __construct(
        private readonly LegacyRoleBackfillAnalyzer $analyzer,
        private readonly AnalysisReportValidator $validator,
        private readonly PrivateArtifactRepository $artifacts,
        private readonly PermissionRegistrar $registrar,
        private readonly ?Closure $afterWriteHook = null,
    ) {}

    public function apply(
        AnalysisReport $report,
        string $acceptedSource,
        string $acceptedReport,
        string $confirmation,
    ): BackfillResult {
        if ($confirmation !== 'AUTHZ1-C') {
            throw new BackfillRefusalException('The literal AUTHZ1-C confirmation is required.');
        }

        if (! hash_equals($report->sourceFingerprint(), $acceptedSource)) {
            throw new BackfillRefusalException('The accepted source fingerprint does not match.');
        }

        if (! hash_equals($report->reportFingerprint(), $acceptedReport)) {
            throw new BackfillRefusalException('The accepted report fingerprint does not match.');
        }

        $this->validator->validate($report);
        $preparedName = $this->artifacts->operationName($report->reportFingerprint(), 'prepared');
        $cacheResetName = $this->artifacts->operationName($report->reportFingerprint(), 'cache_reset');
        $completeName = $this->artifacts->operationName($report->reportFingerprint(), 'complete');
        $receiptName = $this->artifacts->backfillReceiptName($report->reportFingerprint());

        if ($this->artifacts->backfillReceiptExists($receiptName)) {
            $receipt = $this->artifacts->loadBackfillReceipt($receiptName);
            $prepared = $this->loadPreparedJournal($preparedName, $report);
            $this->loadStateJournal($cacheResetName, 'cache_reset', $report, $prepared);
            $current = $this->analyzer->analyze();
            $this->assertCurrentSourceAndTarget($current, $report, $receipt->afterFingerprint());

            if (! $this->artifacts->operationExists($completeName)) {
                $this->artifacts->publishOperation($completeName, $this->journal(
                    state: 'complete',
                    report: $report,
                    prepared: $prepared,
                    receiptName: $receiptName,
                ));
            } else {
                $this->loadStateJournal($completeName, 'complete', $report, $prepared, $receiptName);
            }

            return $this->resultFromReceipt('no_op', $receiptName, $receipt);
        }

        if ($report->status() === 'already_applied' && ! $this->artifacts->operationExists($preparedName)) {
            return new BackfillResult(
                status: 'no_op',
                sourceFingerprint: $report->sourceFingerprint(),
                afterFingerprint: $report->targetPlannedFingerprint(),
                receiptName: null,
                insertedRoles: 0,
                insertedAssignments: 0,
            );
        }

        $transaction = DB::transaction(function () use ($report, $preparedName): array {
            $this->assertSupportedTransaction();
            $current = $this->analyzer->analyzeLocked();

            if ($current->isBlocked() || ! hash_equals($current->sourceFingerprint(), $report->sourceFingerprint())) {
                throw new BackfillRefusalException('The raw source changed after analysis.');
            }

            $prepared = $this->artifacts->operationExists($preparedName)
                ? $this->loadPreparedJournal($preparedName, $report)
                : $this->preparedJournal($report);

            if (hash_equals($current->targetPlannedFingerprint(), $current->targetBeforeFingerprint())) {
                if (! $this->artifacts->operationExists($preparedName)) {
                    throw new BackfillRefusalException('A planned target without its prepared journal is not recoverable.');
                }

                return ['report' => $current, 'prepared' => $prepared, 'changed' => false, 'recovery' => true];
            }

            if (! hash_equals($current->targetBeforeFingerprint(), $report->targetBeforeFingerprint())) {
                throw new BackfillRefusalException('The package target changed after analysis.');
            }

            if (! $this->artifacts->operationExists($preparedName)) {
                $this->artifacts->publishOperation($preparedName, $prepared);
            }

            $this->insertMissingRoles();
            $this->insertMissingAssignments();

            if ($this->afterWriteHook instanceof Closure) {
                ($this->afterWriteHook)();
            }

            $after = $this->analyzer->analyzeLocked();

            if (
                ! hash_equals($after->sourceFingerprint(), $report->sourceFingerprint())
                || ! hash_equals($after->targetBeforeFingerprint(), $report->targetPlannedFingerprint())
                || $after->status() !== 'already_applied'
            ) {
                throw new BackfillException('The transactional authorization projection did not reconcile.');
            }

            return ['report' => $after, 'prepared' => $prepared, 'changed' => true, 'recovery' => false];
        }, attempts: 3);

        $prepared = $transaction['prepared'];
        $committedAt = now('UTC')->toIso8601ZuluString();

        if ($this->artifacts->operationExists($cacheResetName)) {
            $this->loadStateJournal($cacheResetName, 'cache_reset', $report, $prepared);
        } else {
            if (! $this->registrar->forgetCachedPermissions()) {
                throw new BackfillException('The post-commit permission cache reset failed.');
            }

            $this->artifacts->publishOperation($cacheResetName, $this->journal(
                state: 'cache_reset',
                report: $report,
                prepared: $prepared,
            ));
        }

        $receipt = BackfillReceipt::create([
            'operation_id' => (string) $prepared['operation_id'],
            'report_fingerprint' => $report->reportFingerprint(),
            'source_fingerprint' => $report->sourceFingerprint(),
            'before_fingerprint' => $report->targetBeforeFingerprint(),
            'planned_fingerprint' => $report->targetPlannedFingerprint(),
            'after_fingerprint' => $transaction['report']->targetBeforeFingerprint(),
            'inserted_roles' => $prepared['inserted_roles'],
            'inserted_assignments' => $prepared['inserted_assignments'],
            'counts' => [
                'inserted_roles' => count($prepared['inserted_roles']),
                'inserted_assignments' => count($prepared['inserted_assignments']),
            ],
            'cache_reset_complete' => true,
            'committed_at' => $committedAt,
            'completed_at' => now('UTC')->toIso8601ZuluString(),
            'legacy_authority' => true,
        ]);

        $this->artifacts->publishBackfillReceipt($receiptName, $receipt);
        $this->artifacts->publishOperation($completeName, $this->journal(
            state: 'complete',
            report: $report,
            prepared: $prepared,
            receiptName: $receiptName,
        ));

        return $this->resultFromReceipt($transaction['recovery'] ? 'recovered' : 'applied', $receiptName, $receipt);
    }

    private function insertMissingRoles(): void
    {
        $table = (string) config('permission.table_names.roles');
        $existing = DB::table($table)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();
        $now = now();

        foreach (UserRole::values() as $role) {
            if (in_array($role, $existing, true)) {
                continue;
            }

            DB::table($table)->insert([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertMissingAssignments(): void
    {
        $roleTable = (string) config('permission.table_names.roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $modelKey = (string) config('permission.column_names.model_morph_key');
        $modelType = (new User)->getMorphClass();
        $roleIds = DB::table($roleTable)
            ->where('guard_name', 'web')
            ->whereIn('name', UserRole::values())
            ->pluck('id', 'name')
            ->all();
        $users = DB::table((new User)->getTable())
            ->select(['id', 'role'])
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            if (! is_string($user->role) || ! array_key_exists($user->role, $roleIds)) {
                throw new BackfillRefusalException('The raw source became invalid during apply.');
            }

            $exists = DB::table($pivotTable)
                ->where($roleKey, $roleIds[$user->role])
                ->where($modelKey, $user->id)
                ->where('model_type', $modelType)
                ->exists();

            if (! $exists) {
                DB::table($pivotTable)->insert([
                    $roleKey => $roleIds[$user->role],
                    $modelKey => $user->id,
                    'model_type' => $modelType,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function preparedJournal(AnalysisReport $report): array
    {
        $payload = $report->toArray();
        $beforeRoles = $payload['target_before']['roles'];
        $insertedRoles = array_values(array_diff($payload['target_planned']['roles'], $beforeRoles));
        $beforeAssignments = $payload['target_before']['assignment_hashes'];
        $insertedHashes = array_fill_keys(array_diff($payload['target_planned']['assignment_hashes'], $beforeAssignments), true);
        $insertedAssignments = [];

        foreach ($payload['source']['users'] as $user) {
            $hash = $user['planned_assignment_hash'];

            if (is_string($hash) && isset($insertedHashes[$hash])) {
                $insertedAssignments[] = [
                    'assignment_hash' => $hash,
                    'user_hash' => $user['user_hash'],
                    'role' => $user['role'],
                    'model_type' => $payload['connection']['model_type'],
                ];
            }
        }

        usort($insertedAssignments, fn (array $left, array $right): int => $left['assignment_hash'] <=> $right['assignment_hash']);
        sort($insertedRoles, SORT_STRING);

        return $this->signedJournal([
            'schema' => 'podtext.authz1c.operation.v1',
            'state' => 'prepared',
            'operation_id' => hash('sha256', 'operation\0'.$report->reportFingerprint()),
            'report_fingerprint' => $report->reportFingerprint(),
            'source_fingerprint' => $report->sourceFingerprint(),
            'target_before_fingerprint' => $report->targetBeforeFingerprint(),
            'target_planned_fingerprint' => $report->targetPlannedFingerprint(),
            'inserted_roles' => $insertedRoles,
            'inserted_assignments' => $insertedAssignments,
            'prepared_at' => now('UTC')->toIso8601ZuluString(),
        ]);
    }

    /** @return array<string, mixed> */
    private function loadPreparedJournal(string $name, AnalysisReport $report): array
    {
        $journal = $this->artifacts->loadOperation($name);
        $this->assertJournal($journal);

        if (
            ($journal['state'] ?? null) !== 'prepared'
            || ($journal['report_fingerprint'] ?? null) !== $report->reportFingerprint()
            || ($journal['source_fingerprint'] ?? null) !== $report->sourceFingerprint()
            || ($journal['target_before_fingerprint'] ?? null) !== $report->targetBeforeFingerprint()
            || ($journal['target_planned_fingerprint'] ?? null) !== $report->targetPlannedFingerprint()
        ) {
            throw new BackfillRefusalException('The prepared operation journal does not match the accepted report.');
        }

        return $journal;
    }

    /** @return array<string, mixed> */
    private function loadStateJournal(
        string $name,
        string $state,
        AnalysisReport $report,
        array $prepared,
        ?string $receiptName = null,
    ): array {
        if (! $this->artifacts->operationExists($name)) {
            throw new BackfillRefusalException("The {$state} operation journal is missing.");
        }

        $journal = $this->artifacts->loadOperation($name);
        $this->assertJournal($journal);

        if (
            ($journal['state'] ?? null) !== $state
            || ($journal['operation_id'] ?? null) !== ($prepared['operation_id'] ?? null)
            || ($journal['report_fingerprint'] ?? null) !== $report->reportFingerprint()
            || ($journal['source_fingerprint'] ?? null) !== $report->sourceFingerprint()
            || ($journal['target_before_fingerprint'] ?? null) !== $report->targetBeforeFingerprint()
            || ($journal['target_planned_fingerprint'] ?? null) !== $report->targetPlannedFingerprint()
            || CanonicalJson::encode($journal['inserted_roles'] ?? null) !== CanonicalJson::encode($prepared['inserted_roles'] ?? null)
            || CanonicalJson::encode($journal['inserted_assignments'] ?? null) !== CanonicalJson::encode($prepared['inserted_assignments'] ?? null)
            || ($journal['prepared_at'] ?? null) !== ($prepared['prepared_at'] ?? null)
            || ($journal['receipt'] ?? null) !== $receiptName
        ) {
            throw new BackfillRefusalException("The {$state} operation journal does not match the accepted operation.");
        }

        return $journal;
    }

    /** @param array<string, mixed> $prepared @return array<string, mixed> */
    private function journal(string $state, AnalysisReport $report, array $prepared, ?string $receiptName = null): array
    {
        return $this->signedJournal([
            'schema' => 'podtext.authz1c.operation.v1',
            'state' => $state,
            'operation_id' => $prepared['operation_id'],
            'report_fingerprint' => $report->reportFingerprint(),
            'source_fingerprint' => $report->sourceFingerprint(),
            'target_before_fingerprint' => $report->targetBeforeFingerprint(),
            'target_planned_fingerprint' => $report->targetPlannedFingerprint(),
            'inserted_roles' => $prepared['inserted_roles'],
            'inserted_assignments' => $prepared['inserted_assignments'],
            'prepared_at' => $prepared['prepared_at'],
            'transitioned_at' => now('UTC')->toIso8601ZuluString(),
            'receipt' => $receiptName,
        ]);
    }

    /** @param array<string, mixed> $journal @return array<string, mixed> */
    private function signedJournal(array $journal): array
    {
        unset($journal['journal_fingerprint']);
        $journal['journal_fingerprint'] = hash('sha256', CanonicalJson::encode($journal));

        return $journal;
    }

    /** @param array<string, mixed> $journal */
    private function assertJournal(array $journal): void
    {
        $fingerprint = $journal['journal_fingerprint'] ?? null;

        if (! is_string($fingerprint)) {
            throw new ArtifactException('The operation journal fingerprint is missing.');
        }

        unset($journal['journal_fingerprint']);

        if (! hash_equals(hash('sha256', CanonicalJson::encode($journal)), $fingerprint)) {
            throw new ArtifactException('The operation journal fingerprint is invalid.');
        }
    }

    private function assertSupportedTransaction(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            if (! app()->environment('testing') || $connection->getDatabaseName() !== ':memory:') {
                throw new BackfillRefusalException('SQLite apply is restricted to the in-memory test contract.');
            }

            return;
        }

        if ($driver !== 'mysql') {
            throw new BackfillRefusalException('The database driver is unsupported for AUTHZ1-C apply.');
        }

        $row = $connection->selectOne('select @@transaction_isolation as isolation_level');
        $isolation = is_object($row) ? strtoupper((string) $row->isolation_level) : '';

        if (! in_array($isolation, ['REPEATABLE-READ', 'REPEATABLE READ', 'SERIALIZABLE'], true)) {
            throw new BackfillRefusalException('The MySQL transaction isolation is too weak or unknown.');
        }
    }

    private function assertCurrentSourceAndTarget(AnalysisReport $current, AnalysisReport $accepted, string $after): void
    {
        if (
            $current->isBlocked()
            || ! hash_equals($current->sourceFingerprint(), $accepted->sourceFingerprint())
            || ! hash_equals($current->targetBeforeFingerprint(), $after)
            || $current->status() !== 'already_applied'
        ) {
            throw new BackfillRefusalException('The completed operation no longer reconciles with current state.');
        }
    }

    private function resultFromReceipt(string $status, string $name, BackfillReceipt $receipt): BackfillResult
    {
        $payload = $receipt->toArray();

        return new BackfillResult(
            status: $status,
            sourceFingerprint: (string) $payload['source_fingerprint'],
            afterFingerprint: (string) $payload['after_fingerprint'],
            receiptName: $name,
            insertedRoles: (int) $payload['counts']['inserted_roles'],
            insertedAssignments: (int) $payload['counts']['inserted_assignments'],
        );
    }
}
