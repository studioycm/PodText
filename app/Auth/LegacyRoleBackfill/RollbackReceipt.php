<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class RollbackReceipt
{
    public const SCHEMA = 'podtext.authz1c.rollback-receipt.v1';

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
            throw new ArtifactException('The rollback receipt schema is invalid.');
        }

        $fingerprint = $payload['receipt_fingerprint'] ?? null;

        if (! is_string($fingerprint)) {
            throw new ArtifactException('The rollback receipt fingerprint is missing.');
        }

        unset($payload['receipt_fingerprint']);
        $expected = hash('sha256', CanonicalJson::encode($payload));
        $payload['receipt_fingerprint'] = $fingerprint;

        if (! hash_equals($expected, $fingerprint)) {
            throw new ArtifactException('The rollback receipt fingerprint is invalid.');
        }

        self::assertShape($payload);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload): void
    {
        self::assertKeys($payload, [
            'after_fingerprint', 'backfill_receipt_fingerprint', 'before_fingerprint', 'cache_reset',
            'counts', 'deleted_assignment_hashes', 'legacy_authority', 'receipt_fingerprint',
            'report_fingerprint', 'rolled_back_at', 'schema', 'source_fingerprint',
        ]);
        self::assertKeys($payload['counts'] ?? null, ['deleted_assignments']);

        if (
            ! is_array($payload['deleted_assignment_hashes'] ?? null) || ! array_is_list($payload['deleted_assignment_hashes'])
            || ($payload['cache_reset'] ?? null) !== false
            || ($payload['legacy_authority'] ?? null) !== true
        ) {
            throw new ArtifactException('The rollback receipt structure is invalid.');
        }
    }

    /** @param list<string> $expected */
    private static function assertKeys(mixed $value, array $expected): void
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new ArtifactException('The rollback receipt object structure is invalid.');
        }

        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new ArtifactException('The rollback receipt fields are invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
