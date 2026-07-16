<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class BackfillReceipt
{
    public const SCHEMA = 'podtext.authz1c.backfill-receipt.v1';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['receipt_fingerprint']);
        $payload['receipt_fingerprint'] = hash('sha256', CanonicalJson::encode($payload));

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new ArtifactException('The backfill receipt schema is invalid.');
        }

        $fingerprint = $payload['receipt_fingerprint'] ?? null;

        if (! is_string($fingerprint)) {
            throw new ArtifactException('The backfill receipt fingerprint is missing.');
        }

        unset($payload['receipt_fingerprint']);
        $expected = hash('sha256', CanonicalJson::encode($payload));
        $payload['receipt_fingerprint'] = $fingerprint;

        if (! hash_equals($expected, $fingerprint)) {
            throw new ArtifactException('The backfill receipt fingerprint is invalid.');
        }

        self::assertShape($payload);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload): void
    {
        self::assertKeys($payload, [
            'after_fingerprint', 'before_fingerprint', 'cache_reset_complete', 'committed_at',
            'completed_at', 'counts', 'inserted_assignments', 'inserted_roles', 'legacy_authority',
            'operation_id', 'planned_fingerprint', 'receipt_fingerprint', 'report_fingerprint',
            'schema', 'source_fingerprint',
        ]);
        self::assertKeys($payload['counts'] ?? null, ['inserted_assignments', 'inserted_roles']);

        if (
            ! is_array($payload['inserted_roles'] ?? null) || ! array_is_list($payload['inserted_roles'])
            || ! is_array($payload['inserted_assignments'] ?? null) || ! array_is_list($payload['inserted_assignments'])
            || ($payload['cache_reset_complete'] ?? null) !== true
            || ($payload['legacy_authority'] ?? null) !== true
        ) {
            throw new ArtifactException('The backfill receipt structure is invalid.');
        }

        foreach ($payload['inserted_assignments'] as $assignment) {
            self::assertKeys($assignment, ['assignment_hash', 'model_type', 'role', 'user_hash']);
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
        return (string) $this->payload['report_fingerprint'];
    }

    public function afterFingerprint(): string
    {
        return (string) $this->payload['after_fingerprint'];
    }

    public function receiptFingerprint(): string
    {
        return (string) $this->payload['receipt_fingerprint'];
    }
}
