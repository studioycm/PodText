<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Enums\UserRole;
use DateTimeImmutable;
use Throwable;

final readonly class OperationJournal
{
    public const SCHEMA = 'podtext.authz1c.operation.v2';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload, PrivacyHasher $hasher): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['artifact_mac']);
        $payload['artifact_mac'] = $hasher->artifactMac('operation-journal', $payload);
        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload, PrivacyHasher $hasher): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new ArtifactException('The operation journal schema is invalid.');
        }

        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function state(): string
    {
        return $this->payload['state'];
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload, PrivacyHasher $hasher): void
    {
        self::assertKeys($payload, [
            'artifact_mac', 'cache_outcome', 'operation_id', 'owned_assignments', 'owned_roles',
            'ownership_status', 'planned_assignments', 'planned_roles', 'prepared_at',
            'protected_roles', 'receipt', 'report_fingerprint', 'rollback_capable', 'schema',
            'source_fingerprint', 'state', 'target_before_fingerprint', 'target_planned_fingerprint',
            'transitioned_at',
        ]);

        foreach (['artifact_mac', 'operation_id', 'report_fingerprint', 'source_fingerprint', 'target_before_fingerprint', 'target_planned_fingerprint'] as $field) {
            self::assertHex($payload[$field] ?? null);
        }

        if (! hash_equals($hasher->artifactMac('operation-journal', $payload), $payload['artifact_mac'])) {
            throw new ArtifactException('The operation journal integrity check failed.');
        }

        $states = ['prepared', 'cache_invalidation_pending', 'cache_invalidated', 'complete'];

        if (! is_string($payload['state'] ?? null) || ! in_array($payload['state'], $states, true)) {
            throw new ArtifactException('The operation journal state is invalid.');
        }

        self::assertTimestamp($payload['prepared_at'] ?? null);

        if (($payload['transitioned_at'] ?? null) !== null) {
            self::assertTimestamp($payload['transitioned_at']);
        }

        foreach (['planned_roles', 'planned_assignments', 'owned_roles', 'protected_roles', 'owned_assignments'] as $field) {
            if (! is_array($payload[$field] ?? null) || ! array_is_list($payload[$field])) {
                throw new ArtifactException('The operation journal list structure is invalid.');
            }
        }

        self::assertSortedUniqueStrings($payload['planned_roles'], UserRole::values());

        foreach ($payload['planned_assignments'] as $assignment) {
            self::assertKeys($assignment, ['assignment_hash', 'model_type', 'role', 'user_hash']);
            self::assertSemanticAssignment($assignment);
        }

        foreach (['owned_roles', 'protected_roles'] as $field) {
            foreach ($payload[$field] as $role) {
                self::assertKeys($role, ['role', 'role_id']);

                if (! is_string($role['role'] ?? null) || ! in_array($role['role'], UserRole::values(), true) || ! is_int($role['role_id'] ?? null) || $role['role_id'] < 1) {
                    throw new ArtifactException('The operation journal role identity is invalid.');
                }
            }
        }

        foreach ($payload['owned_assignments'] as $assignment) {
            self::assertKeys($assignment, ['assignment_hash', 'model_type', 'physical_tuple_hash', 'role', 'role_id', 'user_hash']);
            self::assertSemanticAssignment($assignment);
            self::assertHex($assignment['physical_tuple_hash'] ?? null);

            if (! is_int($assignment['role_id'] ?? null) || $assignment['role_id'] < 1) {
                throw new ArtifactException('The operation journal physical assignment is invalid.');
            }
        }

        self::assertSortedBy($payload['planned_assignments'], 'assignment_hash');
        self::assertSortedBy($payload['owned_assignments'], 'assignment_hash');
        self::assertSortedBy($payload['owned_roles'], 'role');
        self::assertSortedBy($payload['protected_roles'], 'role');

        $ownership = $payload['ownership_status'] ?? null;
        $rollbackCapable = $payload['rollback_capable'] ?? null;
        $cacheOutcome = $payload['cache_outcome'] ?? null;

        if ($ownership !== null && ! in_array($ownership, ['proven', 'unproven'], true)) {
            throw new ArtifactException('The operation journal ownership status is invalid.');
        }

        if ($rollbackCapable !== null && ! is_bool($rollbackCapable)) {
            throw new ArtifactException('The operation journal rollback capability is invalid.');
        }

        if ($cacheOutcome !== null && ! in_array($cacheOutcome, array_column(PermissionCacheInvalidationOutcome::cases(), 'value'), true)) {
            throw new ArtifactException('The operation journal cache outcome is invalid.');
        }

        if (($payload['receipt'] ?? null) !== null && (! is_string($payload['receipt']) || $payload['receipt'] === '')) {
            throw new ArtifactException('The operation journal receipt reference is invalid.');
        }

        if ($payload['state'] === 'prepared' && ($ownership !== null || $rollbackCapable !== null || $cacheOutcome !== null || $payload['transitioned_at'] !== null || $payload['receipt'] !== null || $payload['owned_roles'] !== [] || $payload['protected_roles'] !== [] || $payload['owned_assignments'] !== [])) {
            throw new ArtifactException('The prepared operation journal contains premature completion evidence.');
        }

        if ($payload['state'] !== 'prepared' && $payload['transitioned_at'] === null) {
            throw new ArtifactException('The operation journal transition timestamp is missing.');
        }

        if ($ownership === 'unproven' && ($rollbackCapable !== false || $payload['owned_roles'] !== [] || $payload['owned_assignments'] !== [])) {
            throw new ArtifactException('Unproven operation evidence cannot claim physical ownership.');
        }

        if ($payload['state'] === 'cache_invalidation_pending' && $cacheOutcome !== null) {
            throw new ArtifactException('Pending cache invalidation cannot contain an outcome.');
        }

        if (in_array($payload['state'], ['cache_invalidation_pending', 'cache_invalidated'], true) && ($ownership !== null || $rollbackCapable !== null || $payload['owned_roles'] !== [] || $payload['protected_roles'] !== [] || $payload['owned_assignments'] !== [] || $payload['receipt'] !== null)) {
            throw new ArtifactException('Cache transition evidence cannot assert receipt ownership.');
        }

        if (in_array($payload['state'], ['cache_invalidated', 'complete'], true) && $cacheOutcome === null) {
            throw new ArtifactException('Completed cache invalidation is missing its outcome.');
        }

        if ($payload['state'] === 'complete' && $payload['receipt'] === null) {
            throw new ArtifactException('The complete operation journal is missing its receipt.');
        }

        if ($payload['state'] === 'complete' && (! in_array($ownership, ['proven', 'unproven'], true) || ! is_bool($rollbackCapable))) {
            throw new ArtifactException('The complete operation journal ownership evidence is incomplete.');
        }
    }

    /** @param array<string, mixed> $assignment */
    private static function assertSemanticAssignment(array $assignment): void
    {
        self::assertHex($assignment['assignment_hash'] ?? null);
        self::assertHex($assignment['user_hash'] ?? null);

        if (! is_string($assignment['role'] ?? null) || ! in_array($assignment['role'], UserRole::values(), true) || ! is_string($assignment['model_type'] ?? null) || $assignment['model_type'] === '') {
            throw new ArtifactException('The operation journal assignment is invalid.');
        }
    }

    /** @param list<array<string, mixed>> $items */
    private static function assertSortedBy(array $items, string $key): void
    {
        $values = array_column($items, $key);
        $sorted = $values;
        sort($sorted, SORT_STRING);

        if ($values !== $sorted || count($values) !== count(array_unique($values))) {
            throw new ArtifactException('The operation journal list ordering is invalid.');
        }
    }

    /** @param list<string> $values @param list<string> $allowed */
    private static function assertSortedUniqueStrings(array $values, array $allowed): void
    {
        $sorted = $values;
        sort($sorted, SORT_STRING);

        if ($values !== $sorted || count($values) !== count(array_unique($values)) || array_diff($values, $allowed) !== []) {
            throw new ArtifactException('The operation journal role vector is invalid.');
        }
    }

    private static function assertHex(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new ArtifactException('The operation journal digest is invalid.');
        }
    }

    private static function assertTimestamp(mixed $value): void
    {
        if (! is_string($value) || ! str_ends_with($value, 'Z')) {
            throw new ArtifactException('The operation journal timestamp is invalid.');
        }

        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new ArtifactException('The operation journal timestamp is invalid.');
        }
    }

    /** @param list<string> $expected */
    private static function assertKeys(mixed $value, array $expected): void
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new ArtifactException('The operation journal object structure is invalid.');
        }

        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new ArtifactException('The operation journal fields are invalid.');
        }
    }
}
