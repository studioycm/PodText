<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final class LegacyRoleBackfillApplier
{
    public function __construct(
        private readonly LegacyRoleBackfillAnalyzer $analyzer,
        private readonly AnalysisReportValidator $validator,
        private readonly PrivateArtifactRepository $artifacts,
        private readonly PermissionCacheInvalidator $cacheInvalidator,
        private readonly PrivacyHasher $hasher,
        private readonly ?Closure $afterWriteHook = null,
        private readonly ?Closure $postInvalidationHook = null,
    ) {}

    public function apply(
        AnalysisReport $report,
        string $acceptedSource,
        string $acceptedReport,
        string $confirmation,
    ): BackfillResult {
        $this->assertAcceptance($report, $acceptedSource, $acceptedReport, $confirmation);
        $this->validator->validate($report);

        $fingerprint = $report->reportFingerprint();
        $preparedName = $this->artifacts->operationName($fingerprint, 'prepared');
        $pendingName = $this->artifacts->operationName($fingerprint, 'cache_invalidation_pending');
        $cacheName = $this->artifacts->operationName($fingerprint, 'cache_invalidated');
        $completeName = $this->artifacts->operationName($fingerprint, 'complete');
        $receiptName = $this->artifacts->backfillReceiptName($fingerprint);

        if ($this->artifacts->backfillReceiptExists($receiptName)) {
            $receipt = $this->artifacts->loadBackfillReceipt($receiptName);
            $prepared = $this->loadPrepared($preparedName, $report);
            $this->loadCacheTransition($pendingName, 'cache_invalidation_pending', $prepared, null);
            $this->loadCacheTransition($cacheName, 'cache_invalidated', $prepared, $receipt->toArray()['cache_outcome']);
            $this->assertCurrentCompletion($report, $receipt);

            if ($this->artifacts->operationExists($completeName)) {
                $this->assertCompleteJournal($this->artifacts->loadOperation($completeName), $prepared, $receiptName, $receipt);
            } else {
                $this->artifacts->publishOperation($completeName, $this->completeJournal($prepared, $receiptName, $receipt));
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
                ownershipStatus: 'not_applicable',
                rollbackCapable: false,
                cacheOutcome: null,
            );
        }

        $transaction = DB::transaction(function () use ($report, $preparedName): array {
            $this->assertSupportedTransaction();
            $current = $this->analyzer->analyzeLocked();

            if ($current->isBlocked() || ! hash_equals($current->sourceFingerprint(), $report->sourceFingerprint())) {
                throw new BackfillRefusalException('The raw source changed after analysis.');
            }

            $preparedExists = $this->artifacts->operationExists($preparedName);
            $prepared = $preparedExists
                ? $this->loadPrepared($preparedName, $report)
                : $this->preparedFromReport($report);

            if (hash_equals($current->targetBeforeFingerprint(), $report->targetPlannedFingerprint())) {
                if (! $preparedExists) {
                    throw new BackfillRefusalException('A planned target without its prepared journal is not recoverable.');
                }

                return [
                    'report' => $current,
                    'prepared' => $prepared,
                    'ownership_status' => 'unproven',
                    'rollback_capable' => false,
                    'owned_roles' => [],
                    'protected_roles' => [],
                    'owned_assignments' => [],
                    'committed_at' => null,
                ];
            }

            if (! hash_equals($current->targetBeforeFingerprint(), $report->targetBeforeFingerprint())) {
                throw new BackfillRefusalException('The package target changed after analysis.');
            }

            if (! $preparedExists) {
                $this->artifacts->publishOperation($preparedName, $prepared);
            }

            $ownedRoles = $this->insertMissingRoles();
            [$protectedRoles, $ownedAssignments] = $this->insertMissingAssignments();

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

            $this->assertPhysicalProjection($protectedRoles, $ownedAssignments);

            return [
                'report' => $after,
                'prepared' => $prepared,
                'ownership_status' => 'proven',
                'rollback_capable' => true,
                'owned_roles' => $ownedRoles,
                'protected_roles' => $protectedRoles,
                'owned_assignments' => $ownedAssignments,
                'committed_at' => 'pending',
            ];
        }, attempts: 3);

        /** @var OperationJournal $prepared */
        $prepared = $transaction['prepared'];

        if ($transaction['ownership_status'] === 'proven') {
            $transaction['committed_at'] = now('UTC')->toIso8601ZuluString();
        }

        if ($this->artifacts->operationExists($pendingName)) {
            $this->loadCacheTransition($pendingName, 'cache_invalidation_pending', $prepared, null);
        } else {
            $this->artifacts->publishOperation($pendingName, $this->cacheTransition($prepared, 'cache_invalidation_pending', null));
        }

        if ($this->artifacts->operationExists($cacheName)) {
            $cacheJournal = $this->artifacts->loadOperation($cacheName);
            $cacheOutcome = $cacheJournal->toArray()['cache_outcome'];
            $this->loadCacheTransition($cacheName, 'cache_invalidated', $prepared, $cacheOutcome);
        } else {
            $cacheOutcome = $this->cacheInvalidator->invalidate()->value;

            if ($this->postInvalidationHook instanceof Closure) {
                ($this->postInvalidationHook)();
            }

            $this->artifacts->publishOperation($cacheName, $this->cacheTransition($prepared, 'cache_invalidated', $cacheOutcome));
        }

        $preparedPayload = $prepared->toArray();
        $receipt = BackfillReceipt::create([
            'operation_id' => $preparedPayload['operation_id'],
            'report_fingerprint' => $report->reportFingerprint(),
            'source_fingerprint' => $report->sourceFingerprint(),
            'before_fingerprint' => $report->targetBeforeFingerprint(),
            'planned_fingerprint' => $report->targetPlannedFingerprint(),
            'after_fingerprint' => $transaction['report']->targetBeforeFingerprint(),
            'planned_roles' => $preparedPayload['planned_roles'],
            'planned_assignments' => $preparedPayload['planned_assignments'],
            'owned_roles' => $transaction['owned_roles'],
            'protected_roles' => $transaction['protected_roles'],
            'owned_assignments' => $transaction['owned_assignments'],
            'counts' => [
                'planned_roles' => count($preparedPayload['planned_roles']),
                'planned_assignments' => count($preparedPayload['planned_assignments']),
                'owned_roles' => count($transaction['owned_roles']),
                'protected_roles' => count($transaction['protected_roles']),
                'owned_assignments' => count($transaction['owned_assignments']),
            ],
            'ownership_status' => $transaction['ownership_status'],
            'rollback_capable' => $transaction['rollback_capable'],
            'cache_semantics' => 'at_least_once_idempotent',
            'cache_outcome' => $cacheOutcome,
            'cache_invalidation_complete' => true,
            'committed_at' => $transaction['committed_at'],
            'completed_at' => now('UTC')->toIso8601ZuluString(),
            'legacy_authority' => true,
        ], $this->hasher);

        $this->artifacts->publishBackfillReceipt($receiptName, $receipt);
        $this->artifacts->publishOperation($completeName, $this->completeJournal($prepared, $receiptName, $receipt));

        return $this->resultFromReceipt($transaction['ownership_status'] === 'proven' ? 'applied' : 'completed_unowned', $receiptName, $receipt);
    }

    /** @return list<array{role: string, role_id: int}> */
    private function insertMissingRoles(): array
    {
        $table = (string) config('permission.table_names.roles');
        $existing = DB::table($table)->where('guard_name', 'web')->pluck('id', 'name')->all();
        $owned = [];
        $now = now();

        foreach (UserRole::values() as $role) {
            if (array_key_exists($role, $existing)) {
                continue;
            }

            $roleId = DB::table($table)->insertGetId([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $owned[] = ['role' => $role, 'role_id' => (int) $roleId];
        }

        usort($owned, fn (array $left, array $right): int => $left['role'] <=> $right['role']);

        return $owned;
    }

    /** @return array{list<array{role: string, role_id: int}>, list<array<string, int|string>>} */
    private function insertMissingAssignments(): array
    {
        $roleTable = (string) config('permission.table_names.roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $modelKey = (string) config('permission.column_names.model_morph_key');
        $modelType = (new User)->getMorphClass();
        $roleIds = DB::table($roleTable)->where('guard_name', 'web')->whereIn('name', UserRole::values())->pluck('id', 'name')->all();

        if (count($roleIds) !== count(UserRole::values())) {
            throw new BackfillException('The protected role identity map is incomplete.');
        }

        $protected = [];

        foreach ($roleIds as $role => $roleId) {
            if (! is_int($roleId) && ! ctype_digit((string) $roleId)) {
                throw new BackfillException('A protected role identity is invalid.');
            }

            $protected[] = ['role' => (string) $role, 'role_id' => (int) $roleId];
        }

        usort($protected, fn (array $left, array $right): int => $left['role'] <=> $right['role']);
        $owned = [];
        $users = DB::table((new User)->getTable())->select(['id', 'role'])->orderBy('id')->get();

        foreach ($users as $user) {
            if ((! is_int($user->id) && ! is_string($user->id)) || ! is_string($user->role) || ! array_key_exists($user->role, $roleIds)) {
                throw new BackfillRefusalException('The raw source became invalid during apply.');
            }

            $roleId = (int) $roleIds[$user->role];
            $exists = DB::table($pivotTable)
                ->where($roleKey, $roleId)
                ->where($modelKey, $user->id)
                ->where('model_type', $modelType)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table($pivotTable)->insert([
                $roleKey => $roleId,
                $modelKey => $user->id,
                'model_type' => $modelType,
            ]);
            $userHash = $this->hasher->userHash($user->id);
            $owned[] = [
                'assignment_hash' => $this->hasher->fingerprint('assignment', [
                    'user_hash' => $userHash,
                    'role' => $user->role,
                    'model_type' => $modelType,
                ]),
                'user_hash' => $userHash,
                'role' => $user->role,
                'role_id' => $roleId,
                'model_type' => $modelType,
                'physical_tuple_hash' => $this->hasher->physicalTupleHash($roleId, $user->id, $modelType),
            ];
        }

        usort($owned, fn (array $left, array $right): int => $left['assignment_hash'] <=> $right['assignment_hash']);

        return [$protected, $owned];
    }

    /** @param list<array{role: string, role_id: int}> $protected @param list<array<string, mixed>> $owned */
    private function assertPhysicalProjection(array $protected, array $owned): void
    {
        $roleTable = (string) config('permission.table_names.roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $modelKey = (string) config('permission.column_names.model_morph_key');
        $roleMap = DB::table($roleTable)->where('guard_name', 'web')->whereIn('name', UserRole::values())->pluck('id', 'name')->all();

        foreach ($protected as $role) {
            if ((int) ($roleMap[$role['role']] ?? 0) !== $role['role_id']) {
                throw new BackfillException('The protected role identity changed during apply.');
            }
        }

        $usersByHash = $this->usersByHash();

        foreach ($owned as $assignment) {
            $userId = $usersByHash[$assignment['user_hash']] ?? null;

            if ($userId === null || ! hash_equals($this->hasher->physicalTupleHash($assignment['role_id'], $userId, $assignment['model_type']), $assignment['physical_tuple_hash'])) {
                throw new BackfillException('The physical assignment evidence did not reconcile.');
            }

            if (! DB::table($pivotTable)->where($roleKey, $assignment['role_id'])->where($modelKey, $userId)->where('model_type', $assignment['model_type'])->exists()) {
                throw new BackfillException('The inserted physical assignment could not be re-read.');
            }
        }
    }

    /** @return array<string, int|string> */
    private function usersByHash(): array
    {
        $users = [];

        foreach (DB::table((new User)->getTable())->select('id')->orderBy('id')->get() as $user) {
            if (! is_int($user->id) && ! is_string($user->id)) {
                throw new BackfillRefusalException('A source identity type is invalid.');
            }

            $users[$this->hasher->userHash($user->id)] = $user->id;
        }

        return $users;
    }

    private function preparedFromReport(AnalysisReport $report, ?string $preparedAt = null): OperationJournal
    {
        $payload = $report->toArray();
        $plannedRoles = array_values(array_diff($payload['target_planned']['roles'], $payload['target_before']['roles']));
        sort($plannedRoles, SORT_STRING);
        $plannedHashes = array_fill_keys(array_diff($payload['target_planned']['assignment_hashes'], $payload['target_before']['assignment_hashes']), true);
        $assignments = [];

        foreach ($payload['source']['users'] as $user) {
            $hash = $user['planned_assignment_hash'];

            if (is_string($hash) && isset($plannedHashes[$hash])) {
                $assignments[] = [
                    'assignment_hash' => $hash,
                    'user_hash' => $user['user_hash'],
                    'role' => $user['role'],
                    'model_type' => $payload['connection']['model_type'],
                ];
            }
        }

        usort($assignments, fn (array $left, array $right): int => $left['assignment_hash'] <=> $right['assignment_hash']);

        return OperationJournal::create([
            'state' => 'prepared',
            'operation_id' => hash('sha256', "operation\0v2\0".$report->reportFingerprint()),
            'report_fingerprint' => $report->reportFingerprint(),
            'source_fingerprint' => $report->sourceFingerprint(),
            'target_before_fingerprint' => $report->targetBeforeFingerprint(),
            'target_planned_fingerprint' => $report->targetPlannedFingerprint(),
            'planned_roles' => $plannedRoles,
            'planned_assignments' => $assignments,
            'owned_roles' => [],
            'protected_roles' => [],
            'owned_assignments' => [],
            'ownership_status' => null,
            'rollback_capable' => null,
            'cache_outcome' => null,
            'prepared_at' => $preparedAt ?? now('UTC')->toIso8601ZuluString(),
            'transitioned_at' => null,
            'receipt' => null,
        ], $this->hasher);
    }

    private function loadPrepared(string $name, AnalysisReport $report): OperationJournal
    {
        if (! $this->artifacts->operationExists($name)) {
            throw new BackfillRefusalException('The prepared operation journal is missing.');
        }

        $stored = $this->artifacts->loadOperation($name);
        $expected = $this->preparedFromReport($report, $stored->toArray()['prepared_at']);

        if (CanonicalJson::encode($stored->toArray()) !== CanonicalJson::encode($expected->toArray())) {
            throw new BackfillRefusalException('The prepared operation journal does not exactly match the accepted report.');
        }

        return $stored;
    }

    private function cacheTransition(OperationJournal $prepared, string $state, ?string $outcome): OperationJournal
    {
        $payload = $prepared->toArray();

        return OperationJournal::create([
            ...$this->baseJournalPayload($payload),
            'state' => $state,
            'owned_roles' => [],
            'protected_roles' => [],
            'owned_assignments' => [],
            'ownership_status' => null,
            'rollback_capable' => null,
            'cache_outcome' => $outcome,
            'transitioned_at' => now('UTC')->toIso8601ZuluString(),
            'receipt' => null,
        ], $this->hasher);
    }

    private function loadCacheTransition(string $name, string $state, OperationJournal $prepared, ?string $outcome): OperationJournal
    {
        if (! $this->artifacts->operationExists($name)) {
            throw new BackfillRefusalException("The {$state} operation journal is missing.");
        }

        $stored = $this->artifacts->loadOperation($name);
        $payload = $stored->toArray();
        $expected = OperationJournal::create([
            ...$this->baseJournalPayload($prepared->toArray()),
            'state' => $state,
            'owned_roles' => [],
            'protected_roles' => [],
            'owned_assignments' => [],
            'ownership_status' => null,
            'rollback_capable' => null,
            'cache_outcome' => $outcome,
            'transitioned_at' => $payload['transitioned_at'],
            'receipt' => null,
        ], $this->hasher);

        if (CanonicalJson::encode($payload) !== CanonicalJson::encode($expected->toArray())) {
            throw new BackfillRefusalException("The {$state} operation journal does not match the accepted operation.");
        }

        return $stored;
    }

    private function completeJournal(OperationJournal $prepared, string $receiptName, BackfillReceipt $receipt): OperationJournal
    {
        $receiptPayload = $receipt->toArray();

        return OperationJournal::create([
            ...$this->baseJournalPayload($prepared->toArray()),
            'state' => 'complete',
            'owned_roles' => $receiptPayload['owned_roles'],
            'protected_roles' => $receiptPayload['protected_roles'],
            'owned_assignments' => $receiptPayload['owned_assignments'],
            'ownership_status' => $receiptPayload['ownership_status'],
            'rollback_capable' => $receiptPayload['rollback_capable'],
            'cache_outcome' => $receiptPayload['cache_outcome'],
            'transitioned_at' => $receiptPayload['completed_at'],
            'receipt' => $receiptName,
        ], $this->hasher);
    }

    private function assertCompleteJournal(OperationJournal $journal, OperationJournal $prepared, string $receiptName, BackfillReceipt $receipt): void
    {
        $expected = $this->completeJournal($prepared, $receiptName, $receipt);

        if (CanonicalJson::encode($journal->toArray()) !== CanonicalJson::encode($expected->toArray())) {
            throw new BackfillRefusalException('The complete operation journal does not reconcile with the receipt.');
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function baseJournalPayload(array $payload): array
    {
        return [
            'operation_id' => $payload['operation_id'],
            'report_fingerprint' => $payload['report_fingerprint'],
            'source_fingerprint' => $payload['source_fingerprint'],
            'target_before_fingerprint' => $payload['target_before_fingerprint'],
            'target_planned_fingerprint' => $payload['target_planned_fingerprint'],
            'planned_roles' => $payload['planned_roles'],
            'planned_assignments' => $payload['planned_assignments'],
            'prepared_at' => $payload['prepared_at'],
        ];
    }

    private function assertCurrentCompletion(AnalysisReport $accepted, BackfillReceipt $receipt): void
    {
        $current = $this->analyzer->analyze();

        if ($current->isBlocked() || ! hash_equals($current->sourceFingerprint(), $accepted->sourceFingerprint()) || ! hash_equals($current->targetBeforeFingerprint(), $receipt->afterFingerprint()) || $current->status() !== 'already_applied') {
            throw new BackfillRefusalException('The completed operation no longer reconciles with current state.');
        }
    }

    private function assertAcceptance(AnalysisReport $report, string $acceptedSource, string $acceptedReport, string $confirmation): void
    {
        if ($confirmation !== 'AUTHZ1-C') {
            throw new BackfillRefusalException('The literal AUTHZ1-C confirmation is required.');
        }

        if (! hash_equals($report->sourceFingerprint(), $acceptedSource)) {
            throw new BackfillRefusalException('The accepted source fingerprint does not match.');
        }

        if (! hash_equals($report->reportFingerprint(), $acceptedReport)) {
            throw new BackfillRefusalException('The accepted report fingerprint does not match.');
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

    private function resultFromReceipt(string $status, string $name, BackfillReceipt $receipt): BackfillResult
    {
        $payload = $receipt->toArray();

        return new BackfillResult(
            status: $status,
            sourceFingerprint: $payload['source_fingerprint'],
            afterFingerprint: $payload['after_fingerprint'],
            receiptName: $name,
            insertedRoles: $payload['counts']['owned_roles'],
            insertedAssignments: $payload['counts']['owned_assignments'],
            ownershipStatus: $payload['ownership_status'],
            rollbackCapable: $payload['rollback_capable'],
            cacheOutcome: $payload['cache_outcome'],
        );
    }
}
