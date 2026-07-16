<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Enums\UserRole;
use DateTimeImmutable;
use Throwable;

final readonly class RollbackOperationJournal
{
    public const SCHEMA = 'podtext.authz1c.rollback-operation.v2';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload, PrivacyHasher $hasher): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['artifact_mac']);
        $payload['artifact_mac'] = $hasher->artifactMac('rollback-operation-journal', $payload);
        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload, PrivacyHasher $hasher): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new ArtifactException('The rollback operation journal schema is invalid.');
        }

        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload, PrivacyHasher $hasher): void
    {
        self::assertKeys($payload, [
            'artifact_mac', 'backfill_receipt_fingerprint', 'counts', 'deleted_assignment_hashes',
            'operation_id', 'owned_assignments', 'prepared_at', 'report_fingerprint',
            'rollback_receipt', 'schema', 'source_fingerprint', 'state', 'target_after_fingerprint',
            'target_before_fingerprint', 'transitioned_at',
        ]);

        foreach (['artifact_mac', 'backfill_receipt_fingerprint', 'operation_id', 'report_fingerprint', 'source_fingerprint', 'target_after_fingerprint', 'target_before_fingerprint'] as $field) {
            self::assertHex($payload[$field] ?? null);
        }

        if (! hash_equals($hasher->artifactMac('rollback-operation-journal', $payload), $payload['artifact_mac'])) {
            throw new ArtifactException('The rollback operation journal integrity check failed.');
        }

        if (! in_array($payload['state'] ?? null, ['prepared', 'complete'], true)) {
            throw new ArtifactException('The rollback operation journal state is invalid.');
        }

        foreach (['owned_assignments', 'deleted_assignment_hashes'] as $field) {
            if (! is_array($payload[$field] ?? null) || ! array_is_list($payload[$field])) {
                throw new ArtifactException('The rollback operation journal list structure is invalid.');
            }
        }

        self::assertKeys($payload['counts'] ?? null, ['deleted_assignments']);

        if (! is_int($payload['counts']['deleted_assignments'] ?? null) || $payload['counts']['deleted_assignments'] < 0) {
            throw new ArtifactException('The rollback operation journal count is invalid.');
        }

        $deleted = $payload['deleted_assignment_hashes'];

        foreach ($payload['owned_assignments'] as $assignment) {
            self::assertKeys($assignment, ['assignment_hash', 'model_type', 'physical_tuple_hash', 'role', 'role_id', 'user_hash']);
            self::assertHex($assignment['assignment_hash'] ?? null);
            self::assertHex($assignment['physical_tuple_hash'] ?? null);
            self::assertHex($assignment['user_hash'] ?? null);

            if (! is_string($assignment['role'] ?? null) || ! in_array($assignment['role'], UserRole::values(), true) || ! is_int($assignment['role_id'] ?? null) || $assignment['role_id'] < 1 || ! is_string($assignment['model_type'] ?? null) || $assignment['model_type'] === '') {
                throw new ArtifactException('The rollback operation journal owned assignment is invalid.');
            }
        }

        $ownedHashes = array_column($payload['owned_assignments'], 'assignment_hash');
        $sortedOwnedHashes = $ownedHashes;
        sort($sortedOwnedHashes, SORT_STRING);

        if ($ownedHashes !== $sortedOwnedHashes || count($ownedHashes) !== count(array_unique($ownedHashes))) {
            throw new ArtifactException('The rollback operation journal owned assignment ordering is invalid.');
        }

        foreach ($deleted as $hash) {
            self::assertHex($hash);
        }

        $sorted = $deleted;
        sort($sorted, SORT_STRING);

        if ($deleted !== $sorted || count($deleted) !== count(array_unique($deleted))) {
            throw new ArtifactException('The rollback operation journal digest ordering is invalid.');
        }

        self::assertTimestamp($payload['prepared_at'] ?? null);

        if (($payload['transitioned_at'] ?? null) !== null) {
            self::assertTimestamp($payload['transitioned_at']);
        }

        if ($payload['state'] === 'prepared' && ($payload['transitioned_at'] !== null || $payload['rollback_receipt'] !== null || $payload['counts']['deleted_assignments'] !== 0 || $deleted !== [])) {
            throw new ArtifactException('The rollback prepared journal contains premature completion evidence.');
        }

        if ($payload['state'] === 'complete' && (! is_string($payload['rollback_receipt'] ?? null) || $payload['rollback_receipt'] === '' || $payload['transitioned_at'] === null || $payload['counts']['deleted_assignments'] !== count($deleted))) {
            throw new ArtifactException('The rollback complete journal is invalid.');
        }

        if ($payload['state'] === 'complete' && $deleted !== $ownedHashes) {
            throw new ArtifactException('The rollback complete journal deletion vector does not reconcile.');
        }
    }

    private static function assertHex(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new ArtifactException('The rollback operation journal digest is invalid.');
        }
    }

    private static function assertTimestamp(mixed $value): void
    {
        if (! is_string($value) || ! str_ends_with($value, 'Z')) {
            throw new ArtifactException('The rollback operation journal timestamp is invalid.');
        }

        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new ArtifactException('The rollback operation journal timestamp is invalid.');
        }
    }

    /** @param list<string> $expected */
    private static function assertKeys(mixed $value, array $expected): void
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new ArtifactException('The rollback operation journal object structure is invalid.');
        }

        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new ArtifactException('The rollback operation journal fields are invalid.');
        }
    }
}
