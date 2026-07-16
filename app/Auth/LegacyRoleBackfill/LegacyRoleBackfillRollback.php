<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LegacyRoleBackfillRollback
{
    public function __construct(
        private readonly LegacyRoleBackfillAnalyzer $analyzer,
        private readonly PrivateArtifactRepository $artifacts,
        private readonly PrivacyHasher $hasher,
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

        $receiptPayload = $receipt->toArray();
        $this->validateCompleteBackfillEvidence($receipt, $receiptPayload);

        if (
            ($receiptPayload['cache_reset_complete'] ?? null) !== true
            || ($receiptPayload['legacy_authority'] ?? null) !== true
        ) {
            throw new BackfillRefusalException('The backfill receipt is not complete or pre-cutover.');
        }

        $rollbackName = $this->artifacts->rollbackReceiptName($receipt->receiptFingerprint());
        $journalName = $this->artifacts->operationName($receipt->receiptFingerprint(), 'rolled_back');

        if ($this->artifacts->rollbackReceiptExists($rollbackName)) {
            $rollbackReceipt = $this->artifacts->loadRollbackReceipt($rollbackName)->toArray();
            $current = $this->analyzer->analyze();

            if (
                $current->isBlocked()
                || ! hash_equals($current->sourceFingerprint(), (string) $receiptPayload['source_fingerprint'])
                || ! hash_equals($current->targetBeforeFingerprint(), (string) $rollbackReceipt['after_fingerprint'])
            ) {
                throw new BackfillRefusalException('The completed rollback no longer reconciles with current state.');
            }

            if (! $this->artifacts->operationExists($journalName)) {
                $this->artifacts->publishOperation($journalName, $this->rollbackJournal(
                    $receipt,
                    $rollbackName,
                    $rollbackReceipt,
                ));
            } else {
                $this->validateRollbackJournal($journalName, $receipt, $rollbackName, $rollbackReceipt);
            }

            return new RollbackResult(
                status: 'no_op',
                beforeFingerprint: (string) $rollbackReceipt['before_fingerprint'],
                afterFingerprint: (string) $rollbackReceipt['after_fingerprint'],
                receiptName: $rollbackName,
                deletedAssignments: (int) $rollbackReceipt['counts']['deleted_assignments'],
            );
        }

        $transaction = DB::transaction(function () use ($receiptPayload): array {
            $this->assertSupportedTransaction();
            $current = $this->analyzer->analyzeLocked();

            if (
                ! hash_equals($current->sourceFingerprint(), (string) $receiptPayload['source_fingerprint'])
                || ! hash_equals($current->targetBeforeFingerprint(), (string) $receiptPayload['after_fingerprint'])
                || $current->status() !== 'already_applied'
            ) {
                throw new BackfillRefusalException('The current state does not match the accepted backfill receipt.');
            }

            $deleted = $this->deleteReceiptAssignments($receiptPayload['inserted_assignments']);
            $after = $this->analyzer->analyzeLocked();
            $this->assertRollbackProjection($current, $after, $receiptPayload, $deleted);

            return ['before' => $current, 'after' => $after, 'deleted' => $deleted];
        }, attempts: 3);

        $deletedHashes = array_column($receiptPayload['inserted_assignments'], 'assignment_hash');
        sort($deletedHashes, SORT_STRING);
        $rollbackReceipt = RollbackReceipt::create([
            'backfill_receipt_fingerprint' => $receipt->receiptFingerprint(),
            'report_fingerprint' => $receipt->reportFingerprint(),
            'source_fingerprint' => $receiptPayload['source_fingerprint'],
            'before_fingerprint' => $transaction['before']->targetBeforeFingerprint(),
            'after_fingerprint' => $transaction['after']->targetBeforeFingerprint(),
            'deleted_assignment_hashes' => $deletedHashes,
            'counts' => ['deleted_assignments' => $transaction['deleted']],
            'rolled_back_at' => now('UTC')->toIso8601ZuluString(),
            'cache_reset' => false,
            'legacy_authority' => true,
        ]);
        $this->artifacts->publishRollbackReceipt($rollbackName, $rollbackReceipt);
        $rollbackReceiptPayload = $rollbackReceipt->toArray();
        $this->artifacts->publishOperation($journalName, $this->rollbackJournal(
            $receipt,
            $rollbackName,
            $rollbackReceiptPayload,
        ));

        return new RollbackResult(
            status: 'rolled_back',
            beforeFingerprint: $transaction['before']->targetBeforeFingerprint(),
            afterFingerprint: $transaction['after']->targetBeforeFingerprint(),
            receiptName: $rollbackName,
            deletedAssignments: $transaction['deleted'],
        );
    }

    /** @param list<array<string, string>> $assignments */
    private function deleteReceiptAssignments(array $assignments): int
    {
        $roleTable = (string) config('permission.table_names.roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $modelKey = (string) config('permission.column_names.model_morph_key');
        $modelType = (new User)->getMorphClass();
        $roleIds = DB::table($roleTable)->where('guard_name', 'web')->pluck('id', 'name')->all();
        $usersByHash = [];

        foreach (DB::table((new User)->getTable())->select('id')->orderBy('id')->get() as $user) {
            $usersByHash[$this->hasher->userHash($user->id)] = $user->id;
        }

        $deleted = 0;

        foreach ($assignments as $assignment) {
            $userId = $usersByHash[$assignment['user_hash']] ?? null;
            $roleId = $roleIds[$assignment['role']] ?? null;

            if ($userId === null || $roleId === null || $assignment['model_type'] !== $modelType) {
                throw new BackfillRefusalException('A receipt assignment can no longer be resolved exactly.');
            }

            $expectedHash = $this->hasher->fingerprint('assignment', [
                'user_hash' => $assignment['user_hash'],
                'role' => $assignment['role'],
                'model_type' => $modelType,
            ]);

            if (! hash_equals($expectedHash, $assignment['assignment_hash'])) {
                throw new BackfillRefusalException('A receipt assignment hash is invalid.');
            }

            $count = DB::table($pivotTable)
                ->where($roleKey, $roleId)
                ->where($modelKey, $userId)
                ->where('model_type', $modelType)
                ->delete();

            if ($count !== 1) {
                throw new BackfillRefusalException('A receipt assignment tuple has drifted.');
            }

            $deleted += $count;
        }

        return $deleted;
    }

    /** @param array<string, mixed> $receiptPayload */
    private function validateCompleteBackfillEvidence(BackfillReceipt $receipt, array $receiptPayload): void
    {
        $reportFingerprint = $receipt->reportFingerprint();
        $receiptName = $this->artifacts->backfillReceiptName($reportFingerprint);
        $storedReceipt = $this->artifacts->loadBackfillReceipt($receiptName);

        if (! hash_equals($storedReceipt->receiptFingerprint(), $receipt->receiptFingerprint())) {
            throw new BackfillRefusalException('The accepted backfill receipt is not the canonical stored receipt.');
        }

        $prepared = $this->loadVerifiedJournal($this->artifacts->operationName($reportFingerprint, 'prepared'));
        $cacheReset = $this->loadVerifiedJournal($this->artifacts->operationName($reportFingerprint, 'cache_reset'));
        $complete = $this->loadVerifiedJournal($this->artifacts->operationName($reportFingerprint, 'complete'));

        if (
            ($prepared['state'] ?? null) !== 'prepared'
            || ($cacheReset['state'] ?? null) !== 'cache_reset'
            || ($complete['state'] ?? null) !== 'complete'
            || ($prepared['operation_id'] ?? null) !== ($receiptPayload['operation_id'] ?? null)
            || ($prepared['report_fingerprint'] ?? null) !== $reportFingerprint
            || ($prepared['source_fingerprint'] ?? null) !== ($receiptPayload['source_fingerprint'] ?? null)
            || ($prepared['target_before_fingerprint'] ?? null) !== ($receiptPayload['before_fingerprint'] ?? null)
            || ($prepared['target_planned_fingerprint'] ?? null) !== ($receiptPayload['planned_fingerprint'] ?? null)
            || ($receiptPayload['after_fingerprint'] ?? null) !== ($receiptPayload['planned_fingerprint'] ?? null)
            || CanonicalJson::encode($prepared['inserted_roles'] ?? null) !== CanonicalJson::encode($receiptPayload['inserted_roles'] ?? null)
            || CanonicalJson::encode($prepared['inserted_assignments'] ?? null) !== CanonicalJson::encode($receiptPayload['inserted_assignments'] ?? null)
            || ($cacheReset['operation_id'] ?? null) !== ($prepared['operation_id'] ?? null)
            || ($cacheReset['report_fingerprint'] ?? null) !== $reportFingerprint
            || ($complete['operation_id'] ?? null) !== ($prepared['operation_id'] ?? null)
            || ($complete['report_fingerprint'] ?? null) !== $reportFingerprint
            || ($complete['receipt'] ?? null) !== $receiptName
        ) {
            throw new BackfillRefusalException('The accepted backfill receipt does not reconcile with complete operation evidence.');
        }
    }

    /** @return array<string, mixed> */
    private function loadVerifiedJournal(string $name): array
    {
        $journal = $this->artifacts->loadOperation($name);
        $fingerprint = $journal['journal_fingerprint'] ?? null;

        if (! is_string($fingerprint)) {
            throw new ArtifactException('The operation journal fingerprint is missing.');
        }

        unset($journal['journal_fingerprint']);

        if (! hash_equals(hash('sha256', CanonicalJson::encode($journal)), $fingerprint)) {
            throw new ArtifactException('The operation journal fingerprint is invalid.');
        }

        $journal['journal_fingerprint'] = $fingerprint;

        return $journal;
    }

    /** @param array<string, mixed> $receiptPayload */
    private function assertRollbackProjection(
        AnalysisReport $before,
        AnalysisReport $after,
        array $receiptPayload,
        int $deleted,
    ): void {
        $beforePayload = $before->toArray();
        $afterPayload = $after->toArray();
        $insertedHashes = array_column($receiptPayload['inserted_assignments'], 'assignment_hash');
        $expectedAssignments = array_values(array_diff(
            $beforePayload['target_before']['assignment_hashes'],
            $insertedHashes,
        ));
        sort($expectedAssignments, SORT_STRING);
        $expectedCounts = $beforePayload['target_before']['counts'];
        $expectedCounts['assignments'] -= count($insertedHashes);

        if (
            $after->isBlocked()
            || ! hash_equals($after->sourceFingerprint(), (string) $receiptPayload['source_fingerprint'])
            || $deleted !== count($insertedHashes)
            || CanonicalJson::encode($afterPayload['target_before']['roles']) !== CanonicalJson::encode($beforePayload['target_before']['roles'])
            || CanonicalJson::encode($afterPayload['target_before']['counts']) !== CanonicalJson::encode($expectedCounts)
            || CanonicalJson::encode($afterPayload['target_before']['assignment_hashes']) !== CanonicalJson::encode($expectedAssignments)
        ) {
            throw new BackfillException('The transactional rollback projection did not reconcile.');
        }
    }

    /** @param array<string, mixed> $rollbackReceipt @return array<string, mixed> */
    private function rollbackJournal(
        BackfillReceipt $backfillReceipt,
        string $rollbackName,
        array $rollbackReceipt,
    ): array {
        $journal = [
            'schema' => 'podtext.authz1c.operation.v1',
            'state' => 'rolled_back',
            'backfill_receipt_fingerprint' => $backfillReceipt->receiptFingerprint(),
            'rollback_receipt' => $rollbackName,
            'rollback_receipt_fingerprint' => $rollbackReceipt['receipt_fingerprint'],
            'report_fingerprint' => $backfillReceipt->reportFingerprint(),
            'source_fingerprint' => $rollbackReceipt['source_fingerprint'],
            'target_before_fingerprint' => $rollbackReceipt['before_fingerprint'],
            'target_after_fingerprint' => $rollbackReceipt['after_fingerprint'],
            'deleted_assignment_hashes' => $rollbackReceipt['deleted_assignment_hashes'],
            'counts' => $rollbackReceipt['counts'],
            'transitioned_at' => $rollbackReceipt['rolled_back_at'],
        ];
        $journal['journal_fingerprint'] = hash('sha256', CanonicalJson::encode($journal));

        return $journal;
    }

    /** @param array<string, mixed> $rollbackReceipt */
    private function validateRollbackJournal(
        string $name,
        BackfillReceipt $backfillReceipt,
        string $rollbackName,
        array $rollbackReceipt,
    ): void {
        $actual = $this->artifacts->loadOperation($name);
        $expected = $this->rollbackJournal($backfillReceipt, $rollbackName, $rollbackReceipt);

        if (CanonicalJson::encode($actual) !== CanonicalJson::encode($expected)) {
            throw new BackfillRefusalException('The rolled-back operation journal does not reconcile.');
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
