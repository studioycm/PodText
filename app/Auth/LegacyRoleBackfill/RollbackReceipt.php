<?php

namespace App\Auth\LegacyRoleBackfill;

use DateTimeImmutable;
use Throwable;

final readonly class RollbackReceipt
{
    public const SCHEMA = 'podtext.authz1c.rollback-receipt.v2';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload, PrivacyHasher $hasher): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['artifact_mac'], $payload['receipt_fingerprint']);
        $payload['receipt_fingerprint'] = hash('sha256', CanonicalJson::encode($payload));
        $payload['artifact_mac'] = $hasher->artifactMac('rollback-receipt', $payload);
        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload, PrivacyHasher $hasher): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new ArtifactException('The rollback receipt schema is invalid.');
        }

        self::assertShape($payload, $hasher);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload, PrivacyHasher $hasher): void
    {
        self::assertKeys($payload, [
            'after_fingerprint', 'artifact_mac', 'backfill_receipt_fingerprint', 'before_fingerprint',
            'cache_invalidation_performed', 'counts', 'deleted_assignment_hashes', 'legacy_authority',
            'receipt_fingerprint', 'report_fingerprint', 'rolled_back_at', 'schema', 'source_fingerprint',
            'status',
        ]);
        self::assertKeys($payload['counts'] ?? null, ['deleted_assignments']);

        foreach (['after_fingerprint', 'artifact_mac', 'backfill_receipt_fingerprint', 'before_fingerprint', 'receipt_fingerprint', 'report_fingerprint', 'source_fingerprint'] as $field) {
            self::assertHex($payload[$field] ?? null);
        }

        $withoutMac = $payload;
        unset($withoutMac['artifact_mac']);
        $withoutFingerprint = $withoutMac;
        $fingerprint = $withoutFingerprint['receipt_fingerprint'];
        unset($withoutFingerprint['receipt_fingerprint']);

        if (! hash_equals(hash('sha256', CanonicalJson::encode($withoutFingerprint)), $fingerprint) || ! hash_equals($hasher->artifactMac('rollback-receipt', $payload), $payload['artifact_mac'])) {
            throw new ArtifactException('The rollback receipt integrity check failed.');
        }

        $hashes = $payload['deleted_assignment_hashes'] ?? null;

        if (! is_array($hashes) || ! array_is_list($hashes)) {
            throw new ArtifactException('The rollback receipt assignment vector is invalid.');
        }

        foreach ($hashes as $hash) {
            self::assertHex($hash);
        }

        $sorted = $hashes;
        sort($sorted, SORT_STRING);

        if (
            $hashes !== $sorted
            || count($hashes) !== count(array_unique($hashes))
            || ! is_int($payload['counts']['deleted_assignments'] ?? null)
            || $payload['counts']['deleted_assignments'] !== count($hashes)
            || ! in_array($payload['status'] ?? null, ['rolled_back', 'recovered'], true)
            || ($payload['cache_invalidation_performed'] ?? null) !== false
            || ($payload['legacy_authority'] ?? null) !== true
            || ! is_string($payload['rolled_back_at'] ?? null)
        ) {
            throw new ArtifactException('The rollback receipt structure is invalid.');
        }

        if (! str_ends_with($payload['rolled_back_at'], 'Z')) {
            throw new ArtifactException('The rollback receipt timestamp is invalid.');
        }

        try {
            new DateTimeImmutable($payload['rolled_back_at']);
        } catch (Throwable) {
            throw new ArtifactException('The rollback receipt timestamp is invalid.');
        }
    }

    private static function assertHex(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new ArtifactException('The rollback receipt digest is invalid.');
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

    public function receiptFingerprint(): string
    {
        return $this->payload['receipt_fingerprint'];
    }
}
