<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final class LegacyRoleBackfillRollback
{
    public function __construct(
        private readonly LegacyRoleBackfillAnalyzer $analyzer,
        private readonly PrivateArtifactRepository $artifacts,
        private readonly PrivacyHasher $hasher,
        private readonly ?Closure $postRollbackCommitHook = null,
    ) {}

    public function rollback(
        BackfillReceipt $receipt,
        string $acceptedAfter,
        string $confirmation,
    ): RollbackResult {
        if ($confirmation !== 'ROLLBACK-AUTHZ1-C') {
            throw new BackfillRefusalException('The literal ROLLBACK-AUTHZ1-C confirmation is required.');
        }

        if (! hash_equals($receipt->afterFingerprint(), $acceptedAfter)) {
            throw new BackfillRefusalException('The accepted after-state fingerprint does not match.');
        }

        $payload = $receipt->toArray();
        $this->validateCompleteBackfillEvidence($receipt);

        if (($payload['ownership_status'] ?? null) !== 'proven' || ($payload['rollback_capable'] ?? null) !== true) {
            throw new BackfillRefusalException('The backfill receipt does not prove rollback ownership.');
        }

        $fingerprint = $receipt->receiptFingerprint();
        $preparedName = $this->artifacts->operationName($fingerprint, 'rollback_prepared');
        $completeName = $this->artifacts->operationName($fingerprint, 'rollback_complete');
        $rollbackName = $this->artifacts->rollbackReceiptName($fingerprint);

        if ($this->artifacts->rollbackReceiptExists($rollbackName)) {
            $rollbackReceipt = $this->artifacts->loadRollbackReceipt($rollbackName);
            $this->assertCurrentRollbackCompletion($payload, $rollbackReceipt);
            $prepared = $this->loadRollbackPrepared($preparedName, $receipt);

            if ($this->artifacts->operationExists($completeName)) {
                $this->assertRollbackComplete($this->artifacts->loadRollbackOperation($completeName), $prepared, $rollbackName, $rollbackReceipt);
            } else {
                $this->artifacts->publishRollbackOperation($completeName, $this->rollbackComplete($prepared, $rollbackName, $rollbackReceipt));
            }

            $rollbackPayload = $rollbackReceipt->toArray();

            return new RollbackResult(
                status: 'no_op',
                beforeFingerprint: $rollbackPayload['before_fingerprint'],
                afterFingerprint: $rollbackPayload['after_fingerprint'],
                receiptName: $rollbackName,
                deletedAssignments: 0,
            );
        }

        $current = $this->analyzer->analyze();

        if ($current->isBlocked() || ! hash_equals($current->sourceFingerprint(), $payload['source_fingerprint'])) {
            throw new BackfillRefusalException('The current source or package state blocks rollback.');
        }

        $preparedExists = $this->artifacts->operationExists($preparedName);
        $prepared = $preparedExists
            ? $this->loadRollbackPrepared($preparedName, $receipt)
            : $this->rollbackPrepared($receipt, $current);
        $preparedPayload = $prepared->toArray();

        if (! $preparedExists) {
            if (! hash_equals($current->targetBeforeFingerprint(), $payload['after_fingerprint'])) {
                throw new BackfillRefusalException('The current state does not match the accepted backfill receipt.');
            }

            $this->artifacts->publishRollbackOperation($preparedName, $prepared);
        }

        $transaction = DB::transaction(function () use ($payload, $preparedPayload): array {
            $this->assertSupportedTransaction();
            $before = $this->analyzer->analyzeLocked();

            if ($before->isBlocked() || ! hash_equals($before->sourceFingerprint(), $payload['source_fingerprint'])) {
                throw new BackfillRefusalException('The current source or package state blocks rollback.');
            }

            if (hash_equals($before->targetBeforeFingerprint(), $preparedPayload['target_after_fingerprint'])) {
                return ['before' => $before, 'after' => $before, 'deleted' => 0, 'status' => 'recovered'];
            }

            if (! hash_equals($before->targetBeforeFingerprint(), $preparedPayload['target_before_fingerprint'])) {
                throw new BackfillRefusalException('The rollback target is partial or has drifted.');
            }

            $deleted = $this->deleteOwnedAssignments($payload['owned_assignments'], $payload['protected_roles']);
            $after = $this->analyzer->analyzeLocked();

            if ($after->isBlocked() || ! hash_equals($after->sourceFingerprint(), $payload['source_fingerprint']) || ! hash_equals($after->targetBeforeFingerprint(), $preparedPayload['target_after_fingerprint'])) {
                throw new BackfillException('The transactional rollback projection did not reconcile.');
            }

            return ['before' => $before, 'after' => $after, 'deleted' => $deleted, 'status' => 'rolled_back'];
        }, attempts: 3);

        if ($this->postRollbackCommitHook instanceof Closure) {
            ($this->postRollbackCommitHook)();
        }

        $deletedHashes = array_column($payload['owned_assignments'], 'assignment_hash');
        sort($deletedHashes, SORT_STRING);
        $rollbackReceipt = RollbackReceipt::create([
            'status' => $transaction['status'],
            'backfill_receipt_fingerprint' => $fingerprint,
            'report_fingerprint' => $payload['report_fingerprint'],
            'source_fingerprint' => $payload['source_fingerprint'],
            'before_fingerprint' => $preparedPayload['target_before_fingerprint'],
            'after_fingerprint' => $preparedPayload['target_after_fingerprint'],
            'deleted_assignment_hashes' => $deletedHashes,
            'counts' => ['deleted_assignments' => count($deletedHashes)],
            'rolled_back_at' => now('UTC')->toIso8601ZuluString(),
            'cache_invalidation_performed' => false,
            'legacy_authority' => true,
        ], $this->hasher);
        $this->artifacts->publishRollbackReceipt($rollbackName, $rollbackReceipt);
        $this->artifacts->publishRollbackOperation($completeName, $this->rollbackComplete($prepared, $rollbackName, $rollbackReceipt));

        return new RollbackResult(
            status: $transaction['status'],
            beforeFingerprint: $preparedPayload['target_before_fingerprint'],
            afterFingerprint: $preparedPayload['target_after_fingerprint'],
            receiptName: $rollbackName,
            deletedAssignments: $transaction['deleted'],
        );
    }

    private function validateCompleteBackfillEvidence(BackfillReceipt $receipt): void
    {
        $payload = $receipt->toArray();
        $receiptName = $this->artifacts->backfillReceiptName($receipt->reportFingerprint());
        $stored = $this->artifacts->loadBackfillReceipt($receiptName);

        if (! hash_equals($stored->receiptFingerprint(), $receipt->receiptFingerprint())) {
            throw new BackfillRefusalException('The accepted backfill receipt is not the canonical stored receipt.');
        }

        $prepared = $this->artifacts->loadOperation($this->artifacts->operationName($receipt->reportFingerprint(), 'prepared'))->toArray();
        $pending = $this->artifacts->loadOperation($this->artifacts->operationName($receipt->reportFingerprint(), 'cache_invalidation_pending'))->toArray();
        $cache = $this->artifacts->loadOperation($this->artifacts->operationName($receipt->reportFingerprint(), 'cache_invalidated'))->toArray();
        $complete = $this->artifacts->loadOperation($this->artifacts->operationName($receipt->reportFingerprint(), 'complete'))->toArray();

        if (
            $prepared['state'] !== 'prepared'
            || $pending['state'] !== 'cache_invalidation_pending'
            || $cache['state'] !== 'cache_invalidated'
            || $complete['state'] !== 'complete'
            || $prepared['operation_id'] !== $payload['operation_id']
            || $prepared['report_fingerprint'] !== $payload['report_fingerprint']
            || $prepared['source_fingerprint'] !== $payload['source_fingerprint']
            || $prepared['target_before_fingerprint'] !== $payload['before_fingerprint']
            || $prepared['target_planned_fingerprint'] !== $payload['planned_fingerprint']
            || CanonicalJson::encode($prepared['planned_roles']) !== CanonicalJson::encode($payload['planned_roles'])
            || CanonicalJson::encode($prepared['planned_assignments']) !== CanonicalJson::encode($payload['planned_assignments'])
            || $pending['operation_id'] !== $payload['operation_id']
            || $cache['operation_id'] !== $payload['operation_id']
            || $cache['cache_outcome'] !== $payload['cache_outcome']
            || $complete['operation_id'] !== $payload['operation_id']
            || $complete['receipt'] !== $receiptName
            || $complete['ownership_status'] !== $payload['ownership_status']
            || $complete['rollback_capable'] !== $payload['rollback_capable']
            || CanonicalJson::encode($complete['owned_roles']) !== CanonicalJson::encode($payload['owned_roles'])
            || CanonicalJson::encode($complete['protected_roles']) !== CanonicalJson::encode($payload['protected_roles'])
            || CanonicalJson::encode($complete['owned_assignments']) !== CanonicalJson::encode($payload['owned_assignments'])
        ) {
            throw new BackfillRefusalException('The accepted backfill receipt does not reconcile with complete operation evidence.');
        }
    }

    private function rollbackPrepared(BackfillReceipt $receipt, AnalysisReport $current, ?string $preparedAt = null): RollbackOperationJournal
    {
        $payload = $receipt->toArray();

        if (! hash_equals($current->targetBeforeFingerprint(), $payload['after_fingerprint'])) {
            throw new BackfillRefusalException('The current state does not match the accepted backfill receipt.');
        }

        $currentPayload = $current->toArray();
        $ownedHashes = array_column($payload['owned_assignments'], 'assignment_hash');
        $plannedAssignments = array_values(array_diff($currentPayload['target_before']['assignment_hashes'], $ownedHashes));
        sort($plannedAssignments, SORT_STRING);
        $counts = $currentPayload['target_before']['counts'];
        $counts['assignments'] -= count($ownedHashes);
        $targetAfter = $this->hasher->fingerprint('target-state', [
            'counts' => $counts,
            'roles' => $currentPayload['target_before']['roles'],
            'assignments' => $plannedAssignments,
        ]);

        return RollbackOperationJournal::create([
            'state' => 'prepared',
            'operation_id' => hash('sha256', "rollback\0v2\0".$receipt->receiptFingerprint()),
            'backfill_receipt_fingerprint' => $receipt->receiptFingerprint(),
            'report_fingerprint' => $payload['report_fingerprint'],
            'source_fingerprint' => $payload['source_fingerprint'],
            'target_before_fingerprint' => $payload['after_fingerprint'],
            'target_after_fingerprint' => $targetAfter,
            'owned_assignments' => $payload['owned_assignments'],
            'deleted_assignment_hashes' => [],
            'counts' => ['deleted_assignments' => 0],
            'prepared_at' => $preparedAt ?? now('UTC')->toIso8601ZuluString(),
            'transitioned_at' => null,
            'rollback_receipt' => null,
        ], $this->hasher);
    }

    private function loadRollbackPrepared(string $name, BackfillReceipt $receipt): RollbackOperationJournal
    {
        if (! $this->artifacts->operationExists($name)) {
            throw new BackfillRefusalException('The rollback prepared journal is missing.');
        }

        $stored = $this->artifacts->loadRollbackOperation($name);
        $payload = $stored->toArray();
        $current = $this->analyzer->analyze();

        if (! in_array($current->targetBeforeFingerprint(), [$payload['target_before_fingerprint'], $payload['target_after_fingerprint']], true)) {
            throw new BackfillRefusalException('The rollback target is partial or has drifted.');
        }

        $receiptPayload = $receipt->toArray();

        if (
            $payload['state'] !== 'prepared'
            || $payload['backfill_receipt_fingerprint'] !== $receipt->receiptFingerprint()
            || $payload['report_fingerprint'] !== $receiptPayload['report_fingerprint']
            || $payload['source_fingerprint'] !== $receiptPayload['source_fingerprint']
            || $payload['target_before_fingerprint'] !== $receiptPayload['after_fingerprint']
            || CanonicalJson::encode($payload['owned_assignments']) !== CanonicalJson::encode($receiptPayload['owned_assignments'])
        ) {
            throw new BackfillRefusalException('The rollback prepared journal does not match the backfill receipt.');
        }

        return $stored;
    }

    /** @param list<array<string, mixed>> $assignments @param list<array<string, mixed>> $protectedRoles */
    private function deleteOwnedAssignments(array $assignments, array $protectedRoles): int
    {
        $roleTable = (string) config('permission.table_names.roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $modelKey = (string) config('permission.column_names.model_morph_key');
        $modelType = (new User)->getMorphClass();
        $roles = DB::table($roleTable)->whereIn('id', array_column($protectedRoles, 'role_id'))->get(['id', 'name', 'guard_name'])->keyBy(fn (object $role): string => (string) $role->id);

        foreach ($protectedRoles as $protected) {
            $role = $roles->get((string) $protected['role_id']);

            if ($role === null || $role->name !== $protected['role'] || $role->guard_name !== 'web') {
                throw new BackfillRefusalException('A protected role identity has drifted.');
            }
        }

        $usersByHash = [];

        foreach (DB::table((new User)->getTable())->select('id')->orderBy('id')->get() as $user) {
            if (! is_int($user->id) && ! is_string($user->id)) {
                throw new BackfillRefusalException('A source identity type is invalid.');
            }

            $usersByHash[$this->hasher->userHash($user->id)] = $user->id;
        }

        foreach ($assignments as $assignment) {
            $userId = $usersByHash[$assignment['user_hash']] ?? null;
            $role = $roles->get((string) $assignment['role_id']);

            if ($userId === null || $role === null || $role->name !== $assignment['role'] || $role->guard_name !== 'web' || $assignment['model_type'] !== $modelType) {
                throw new BackfillRefusalException('A receipt assignment can no longer be resolved exactly.');
            }

            $semanticHash = $this->hasher->fingerprint('assignment', [
                'user_hash' => $assignment['user_hash'],
                'role' => $assignment['role'],
                'model_type' => $modelType,
            ]);
            $physicalHash = $this->hasher->physicalTupleHash($assignment['role_id'], $userId, $modelType);

            if (! hash_equals($semanticHash, $assignment['assignment_hash']) || ! hash_equals($physicalHash, $assignment['physical_tuple_hash'])) {
                throw new BackfillRefusalException('A receipt assignment identity is invalid.');
            }

            if (! DB::table($pivotTable)->where($roleKey, $assignment['role_id'])->where($modelKey, $userId)->where('model_type', $modelType)->exists()) {
                throw new BackfillRefusalException('A receipt assignment tuple has drifted.');
            }
        }

        $deleted = 0;

        foreach ($assignments as $assignment) {
            $userId = $usersByHash[$assignment['user_hash']];
            $count = DB::table($pivotTable)->where($roleKey, $assignment['role_id'])->where($modelKey, $userId)->where('model_type', $modelType)->delete();

            if ($count !== 1) {
                throw new BackfillRefusalException('A receipt assignment tuple has drifted.');
            }

            $deleted += $count;
        }

        return $deleted;
    }

    private function rollbackComplete(RollbackOperationJournal $prepared, string $receiptName, RollbackReceipt $receipt): RollbackOperationJournal
    {
        $payload = $prepared->toArray();
        $receiptPayload = $receipt->toArray();

        return RollbackOperationJournal::create([
            'state' => 'complete',
            'operation_id' => $payload['operation_id'],
            'backfill_receipt_fingerprint' => $payload['backfill_receipt_fingerprint'],
            'report_fingerprint' => $payload['report_fingerprint'],
            'source_fingerprint' => $payload['source_fingerprint'],
            'target_before_fingerprint' => $payload['target_before_fingerprint'],
            'target_after_fingerprint' => $payload['target_after_fingerprint'],
            'owned_assignments' => $payload['owned_assignments'],
            'deleted_assignment_hashes' => $receiptPayload['deleted_assignment_hashes'],
            'counts' => $receiptPayload['counts'],
            'prepared_at' => $payload['prepared_at'],
            'transitioned_at' => $receiptPayload['rolled_back_at'],
            'rollback_receipt' => $receiptName,
        ], $this->hasher);
    }

    private function assertRollbackComplete(RollbackOperationJournal $journal, RollbackOperationJournal $prepared, string $receiptName, RollbackReceipt $receipt): void
    {
        if (CanonicalJson::encode($journal->toArray()) !== CanonicalJson::encode($this->rollbackComplete($prepared, $receiptName, $receipt)->toArray())) {
            throw new BackfillRefusalException('The rollback complete journal does not reconcile.');
        }
    }

    private function assertCurrentRollbackCompletion(array $backfill, RollbackReceipt $receipt): void
    {
        $current = $this->analyzer->analyze();
        $payload = $receipt->toArray();

        if ($current->isBlocked() || ! hash_equals($current->sourceFingerprint(), $backfill['source_fingerprint']) || ! hash_equals($current->targetBeforeFingerprint(), $payload['after_fingerprint'])) {
            throw new BackfillRefusalException('The completed rollback no longer reconciles with current state.');
        }
    }

    private function assertSupportedTransaction(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            if (! app()->environment('testing') || $connection->getDatabaseName() !== ':memory:') {
                throw new BackfillRefusalException('SQLite rollback is restricted to the in-memory test contract.');
            }

            return;
        }

        if ($driver !== 'mysql') {
            throw new BackfillRefusalException('The database driver is unsupported for AUTHZ1-C rollback.');
        }

        $row = $connection->selectOne('select @@transaction_isolation as isolation_level');
        $isolation = is_object($row) ? strtoupper((string) $row->isolation_level) : '';

        if (! in_array($isolation, ['REPEATABLE-READ', 'REPEATABLE READ', 'SERIALIZABLE'], true)) {
            throw new BackfillRefusalException('The MySQL transaction isolation is too weak or unknown.');
        }
    }
}
