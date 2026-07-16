<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class AnalysisReport
{
    public const SCHEMA = 'podtext.authz1c.analysis.v1';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['fingerprints']['report']);
        $report = new self($payload);
        $payload['fingerprints']['report'] = hash('sha256', CanonicalJson::encode($report->contentForFingerprint()));

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new ArtifactException('The analysis artifact schema is invalid.');
        }

        $fingerprints = $payload['fingerprints'] ?? null;
        $fingerprint = is_array($fingerprints) ? ($fingerprints['report'] ?? null) : null;

        if (! is_string($fingerprint)) {
            throw new ArtifactException('The analysis artifact fingerprint is missing.');
        }

        unset($payload['fingerprints']['report']);
        $expected = hash('sha256', CanonicalJson::encode($payload));
        $payload['fingerprints']['report'] = $fingerprint;

        if (! hash_equals($expected, $fingerprint)) {
            throw new ArtifactException('The analysis artifact fingerprint is invalid.');
        }

        self::assertShape($payload);

        return new self($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function assertShape(array $payload): void
    {
        self::assertKeys($payload, [
            'access_parity', 'connection', 'evidence', 'fingerprints', 'generated_at',
            'issue_totals', 'issues', 'legacy_authority', 'schema', 'source', 'status',
            'target_before', 'target_planned',
        ]);
        self::assertKeys($payload['evidence'] ?? null, [
            'ability_catalog_hash', 'ability_catalog_version', 'config_hash', 'grant_manifest_hash',
            'package_version', 'role_catalog_hash', 'schema_hash', 'user_role_hash',
        ]);
        self::assertKeys($payload['connection'] ?? null, [
            'columns', 'driver', 'guard', 'key_id', 'model_type', 'tables', 'teams',
        ]);
        self::assertKeys($payload['source'] ?? null, ['per_role', 'total', 'users']);
        self::assertTargetShape($payload['target_before'] ?? null);
        self::assertTargetShape($payload['target_planned'] ?? null);
        self::assertKeys($payload['access_parity'] ?? null, ['hash', 'matrix']);
        self::assertKeys($payload['fingerprints'] ?? null, [
            'assignment_before', 'report', 'source', 'target_before', 'target_planned',
        ]);

        if (
            ! is_string($payload['generated_at'] ?? null)
            || ! is_string($payload['status'] ?? null)
            || ! is_bool($payload['legacy_authority'] ?? null)
            || ! is_array($payload['issues'] ?? null) || ! array_is_list($payload['issues'])
            || ! is_array($payload['issue_totals'] ?? null)
            || ($payload['issue_totals'] !== [] && array_is_list($payload['issue_totals']))
            || ! is_array($payload['source']['users'] ?? null) || ! array_is_list($payload['source']['users'])
        ) {
            throw new ArtifactException('The analysis artifact structure is invalid.');
        }

        foreach ($payload['source']['users'] as $user) {
            self::assertKeys($user, [
                'existing_assignment_hashes', 'issues', 'planned_assignment_hash', 'raw_role_hash',
                'role', 'user_hash', 'valid',
            ]);
        }

        foreach ($payload['issues'] as $issue) {
            self::assertKeys($issue, ['code', 'role', 'user_hash']);
        }
    }

    private static function assertTargetShape(mixed $target): void
    {
        self::assertKeys($target, ['assignment_hashes', 'counts', 'roles']);
        self::assertKeys(is_array($target) ? ($target['counts'] ?? null) : null, [
            'assignments', 'direct_grants', 'permissions', 'role_grants', 'roles',
        ]);

        if (
            ! is_array($target['roles'] ?? null) || ! array_is_list($target['roles'])
            || ! is_array($target['assignment_hashes'] ?? null) || ! array_is_list($target['assignment_hashes'])
        ) {
            throw new ArtifactException('The analysis target structure is invalid.');
        }
    }

    /** @param list<string> $expected */
    private static function assertKeys(mixed $value, array $expected): void
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new ArtifactException('The analysis artifact object structure is invalid.');
        }

        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new ArtifactException('The analysis artifact fields are invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    /** @return array<string, mixed> */
    public function contentForFingerprint(): array
    {
        $payload = $this->payload;
        unset($payload['fingerprints']['report']);

        return $payload;
    }

    public function status(): string
    {
        return (string) $this->payload['status'];
    }

    public function isBlocked(): bool
    {
        return $this->status() === 'blocked';
    }

    public function sourceFingerprint(): string
    {
        return (string) $this->payload['fingerprints']['source'];
    }

    public function reportFingerprint(): string
    {
        return (string) $this->payload['fingerprints']['report'];
    }

    public function targetBeforeFingerprint(): string
    {
        return (string) $this->payload['fingerprints']['target_before'];
    }

    public function targetPlannedFingerprint(): string
    {
        return (string) $this->payload['fingerprints']['target_planned'];
    }
}
