<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Enums\UserRole;
use DateTimeImmutable;
use Throwable;

final readonly class BackfillReceipt
{
    public const SCHEMA = 'podtext.authz1c.backfill-receipt.v2';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload, PrivacyHasher $hasher): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['artifact_mac'], $payload['receipt_fingerprint']);
        $payload['receipt_fingerprint'] = hash('sha256', CanonicalJson::encode($payload));
        $payload['artifact_mac'] = $hasher->artifactMac('backfill-receipt', $payload);
        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload, PrivacyHasher $hasher): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new ArtifactException('The backfill receipt schema is invalid.');
        }

        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload, PrivacyHasher $hasher): void
    {
        self::assertKeys($payload, [
            'after_fingerprint', 'artifact_mac', 'before_fingerprint', 'cache_invalidation_complete',
            'cache_outcome', 'cache_semantics', 'committed_at', 'completed_at', 'counts',
            'legacy_authority', 'operation_id', 'owned_assignments', 'owned_roles', 'ownership_status',
            'planned_assignments', 'planned_fingerprint', 'planned_roles', 'protected_roles',
            'receipt_fingerprint', 'report_fingerprint', 'rollback_capable', 'schema', 'source_fingerprint',
        ]);
        self::assertKeys($payload['counts'] ?? null, [
            'owned_assignments', 'owned_roles', 'planned_assignments', 'planned_roles', 'protected_roles',
        ]);

        foreach (['after_fingerprint', 'artifact_mac', 'before_fingerprint', 'operation_id', 'planned_fingerprint', 'receipt_fingerprint', 'report_fingerprint', 'source_fingerprint'] as $field) {
            self::assertHex($payload[$field] ?? null);
        }

        $withoutMac = $payload;
        unset($withoutMac['artifact_mac']);
        $withoutFingerprint = $withoutMac;
        $fingerprint = $withoutFingerprint['receipt_fingerprint'];
        unset($withoutFingerprint['receipt_fingerprint']);

        if (! hash_equals(hash('sha256', CanonicalJson::encode($withoutFingerprint)), $fingerprint) || ! hash_equals($hasher->artifactMac('backfill-receipt', $payload), $payload['artifact_mac'])) {
            throw new ArtifactException('The backfill receipt integrity check failed.');
        }

        foreach (['planned_roles', 'planned_assignments', 'owned_roles', 'protected_roles', 'owned_assignments'] as $field) {
            if (! is_array($payload[$field] ?? null) || ! array_is_list($payload[$field])) {
                throw new ArtifactException('The backfill receipt list structure is invalid.');
            }

            if (! is_int($payload['counts'][$field] ?? null) || $payload['counts'][$field] !== count($payload[$field])) {
                throw new ArtifactException('The backfill receipt counts do not reconcile.');
            }
        }

        $roles = $payload['planned_roles'];
        $sortedRoles = $roles;
        sort($sortedRoles, SORT_STRING);

        if ($roles !== $sortedRoles || count($roles) !== count(array_unique($roles)) || array_diff($roles, UserRole::values()) !== []) {
            throw new ArtifactException('The backfill receipt planned roles are invalid.');
        }

        foreach ($payload['planned_assignments'] as $assignment) {
            self::assertKeys($assignment, ['assignment_hash', 'model_type', 'role', 'user_hash']);
            self::assertSemanticAssignment($assignment);
        }

        foreach (['owned_roles', 'protected_roles'] as $field) {
            foreach ($payload[$field] as $role) {
                self::assertKeys($role, ['role', 'role_id']);

                if (! is_string($role['role'] ?? null) || ! in_array($role['role'], UserRole::values(), true) || ! is_int($role['role_id'] ?? null) || $role['role_id'] < 1) {
                    throw new ArtifactException('The backfill receipt role identity is invalid.');
                }
            }
        }

        foreach ($payload['owned_assignments'] as $assignment) {
            self::assertKeys($assignment, ['assignment_hash', 'model_type', 'physical_tuple_hash', 'role', 'role_id', 'user_hash']);
            self::assertSemanticAssignment($assignment);
            self::assertHex($assignment['physical_tuple_hash'] ?? null);

            if (! is_int($assignment['role_id'] ?? null) || $assignment['role_id'] < 1) {
                throw new ArtifactException('The backfill receipt physical assignment is invalid.');
            }
        }

        self::assertSortedBy($payload['planned_assignments'], 'assignment_hash');
        self::assertSortedBy($payload['owned_assignments'], 'assignment_hash');
        self::assertSortedBy($payload['owned_roles'], 'role');
        self::assertSortedBy($payload['protected_roles'], 'role');

        if (
            ! in_array($payload['ownership_status'] ?? null, ['proven', 'unproven'], true)
            || ! is_bool($payload['rollback_capable'] ?? null)
            || ($payload['cache_invalidation_complete'] ?? null) !== true
            || ($payload['cache_semantics'] ?? null) !== 'at_least_once_idempotent'
            || ! in_array($payload['cache_outcome'] ?? null, array_column(PermissionCacheInvalidationOutcome::cases(), 'value'), true)
            || ($payload['legacy_authority'] ?? null) !== true
            || ! is_string($payload['completed_at'] ?? null)
            || (($payload['committed_at'] ?? null) !== null && ! is_string($payload['committed_at']))
        ) {
            throw new ArtifactException('The backfill receipt completion structure is invalid.');
        }

        if ($payload['ownership_status'] === 'unproven' && ($payload['rollback_capable'] !== false || $payload['owned_roles'] !== [] || $payload['owned_assignments'] !== [])) {
            throw new ArtifactException('Unproven backfill evidence cannot claim physical ownership.');
        }

        if ($payload['ownership_status'] === 'proven' && $payload['rollback_capable'] !== true) {
            throw new ArtifactException('Proven backfill evidence must be rollback capable.');
        }

        self::assertTimestamp($payload['completed_at']);

        if ($payload['ownership_status'] === 'proven') {
            self::assertTimestamp($payload['committed_at']);
        } elseif ($payload['committed_at'] !== null) {
            throw new ArtifactException('Unproven backfill evidence cannot claim a commit timestamp.');
        }

        if ($payload['after_fingerprint'] !== $payload['planned_fingerprint']) {
            throw new ArtifactException('The backfill receipt after-state does not match its planned state.');
        }

        $plannedAssignmentHashes = array_column($payload['planned_assignments'], 'assignment_hash');
        $ownedAssignmentHashes = array_column($payload['owned_assignments'], 'assignment_hash');
        $protectedByRole = array_column($payload['protected_roles'], 'role_id', 'role');

        if (array_diff($ownedAssignmentHashes, $plannedAssignmentHashes) !== []) {
            throw new ArtifactException('The backfill receipt owned assignments exceed the semantic plan.');
        }

        if ($payload['ownership_status'] === 'proven') {
            $protectedRoleNames = array_keys($protectedByRole);
            $expectedRoleNames = UserRole::values();
            sort($expectedRoleNames, SORT_STRING);

            if ($protectedRoleNames !== $expectedRoleNames || count($protectedByRole) !== count(array_unique($protectedByRole))) {
                throw new ArtifactException('The backfill receipt protected role map is incomplete.');
            }

            foreach ($payload['owned_roles'] as $role) {
                if (($protectedByRole[$role['role']] ?? null) !== $role['role_id'] || ! in_array($role['role'], $payload['planned_roles'], true)) {
                    throw new ArtifactException('The backfill receipt owned role map does not reconcile.');
                }
            }

            foreach ($payload['owned_assignments'] as $assignment) {
                if (($protectedByRole[$assignment['role']] ?? null) !== $assignment['role_id']) {
                    throw new ArtifactException('The backfill receipt assignment role identity does not reconcile.');
                }
            }
        } elseif ($payload['protected_roles'] !== []) {
            throw new ArtifactException('Unproven backfill evidence cannot claim a protected role map.');
        }
    }

    /** @param array<string, mixed> $assignment */
    private static function assertSemanticAssignment(array $assignment): void
    {
        self::assertHex($assignment['assignment_hash'] ?? null);
        self::assertHex($assignment['user_hash'] ?? null);

        if (! is_string($assignment['role'] ?? null) || ! in_array($assignment['role'], UserRole::values(), true) || ! is_string($assignment['model_type'] ?? null) || $assignment['model_type'] === '') {
            throw new ArtifactException('The backfill receipt assignment is invalid.');
        }
    }

    /** @param list<array<string, mixed>> $items */
    private static function assertSortedBy(array $items, string $key): void
    {
        $values = array_column($items, $key);
        $sorted = $values;
        sort($sorted, SORT_STRING);

        if ($values !== $sorted || count($values) !== count(array_unique($values))) {
            throw new ArtifactException('The backfill receipt list ordering is invalid.');
        }
    }

    private static function assertHex(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new ArtifactException('The backfill receipt digest is invalid.');
        }
    }

    private static function assertTimestamp(mixed $value): void
    {
        if (! is_string($value) || ! str_ends_with($value, 'Z')) {
            throw new ArtifactException('The backfill receipt timestamp is invalid.');
        }

        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new ArtifactException('The backfill receipt timestamp is invalid.');
        }
    }

    /** @param list<string> $expected */
    private static function assertKeys(mixed $value, array $expected): void
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new ArtifactException('The backfill receipt object structure is invalid.');
        }

        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new ArtifactException('The backfill receipt fields are invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function reportFingerprint(): string
    {
        return $this->payload['report_fingerprint'];
    }

    public function afterFingerprint(): string
    {
        return $this->payload['after_fingerprint'];
    }

    public function receiptFingerprint(): string
    {
        return $this->payload['receipt_fingerprint'];
    }
}
