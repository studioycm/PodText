<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Enums\UserRole;

final readonly class AnalysisReport
{
    public const SCHEMA = 'podtext.authz1c.analysis.v2';

    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function create(array $payload): self
    {
        $payload['schema'] = self::SCHEMA;
        unset($payload['fingerprints']['report']);
        $report = new self($payload);
        $payload['fingerprints']['report'] = hash('sha256', CanonicalJson::encode($report->contentForFingerprint()));
        self::assertShape($payload);

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
            'columns', 'driver', 'guard', 'key_id', 'model_type', 'schema', 'tables', 'teams',
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
            || ! in_array($payload['status'] ?? null, ['ready', 'already_applied', 'blocked'], true)
            || ($payload['legacy_authority'] ?? null) !== true
            || ! is_array($payload['issues'] ?? null) || ! array_is_list($payload['issues'])
            || ! is_array($payload['issue_totals'] ?? null)
            || ($payload['issue_totals'] !== [] && array_is_list($payload['issue_totals']))
            || ! is_array($payload['source']['users'] ?? null) || ! array_is_list($payload['source']['users'])
            || ! is_array($payload['connection']['schema'] ?? null) || array_is_list($payload['connection']['schema'])
            || ! is_int($payload['source']['total'] ?? null) || $payload['source']['total'] < 0
        ) {
            throw new ArtifactException('The analysis artifact structure is invalid.');
        }

        foreach (['ability_catalog_hash', 'config_hash', 'grant_manifest_hash', 'role_catalog_hash', 'schema_hash', 'user_role_hash'] as $field) {
            self::assertHex($payload['evidence'][$field] ?? null);
        }

        foreach (['assignment_before', 'report', 'source', 'target_before', 'target_planned'] as $field) {
            self::assertHex($payload['fingerprints'][$field] ?? null);
        }

        self::assertHex($payload['connection']['key_id'] ?? null);

        if (
            ! is_string($payload['evidence']['ability_catalog_version'] ?? null)
            || ! is_string($payload['evidence']['package_version'] ?? null)
            || ! is_string($payload['connection']['driver'] ?? null)
            || ! is_string($payload['connection']['guard'] ?? null)
            || ! is_string($payload['connection']['model_type'] ?? null)
            || ! is_bool($payload['connection']['teams'] ?? null)
            || ! is_array($payload['connection']['tables'] ?? null) || array_is_list($payload['connection']['tables'])
            || ! is_array($payload['connection']['columns'] ?? null) || array_is_list($payload['connection']['columns'])
            || ! is_array($payload['source']['per_role'] ?? null) || array_is_list($payload['source']['per_role'])
        ) {
            throw new ArtifactException('The analysis static contract structure is invalid.');
        }

        $expectedPerRole = array_fill_keys(UserRole::values(), 0);

        $actualRoleKeys = array_keys($payload['source']['per_role']);
        $expectedRoleKeys = array_keys($expectedPerRole);
        sort($actualRoleKeys, SORT_STRING);
        sort($expectedRoleKeys, SORT_STRING);

        if ($actualRoleKeys !== $expectedRoleKeys) {
            throw new ArtifactException('The analysis per-role vector is invalid.');
        }

        foreach ($payload['source']['per_role'] as $count) {
            if (! is_int($count) || $count < 0) {
                throw new ArtifactException('The analysis per-role count is invalid.');
            }
        }

        foreach ($payload['source']['users'] as $user) {
            self::assertKeys($user, [
                'existing_assignment_hashes', 'issues', 'planned_assignment_hash', 'raw_role_hash',
                'role', 'user_hash', 'valid',
            ]);

            self::assertHex($user['user_hash'] ?? null);
            self::assertHex($user['raw_role_hash'] ?? null);

            if (
                ! is_bool($user['valid'] ?? null)
                || (($user['role'] ?? null) !== null && (! is_string($user['role']) || ! in_array($user['role'], UserRole::values(), true)))
                || (($user['planned_assignment_hash'] ?? null) !== null && (! is_string($user['planned_assignment_hash']) || preg_match('/\A[a-f0-9]{64}\z/D', $user['planned_assignment_hash']) !== 1))
                || ! is_array($user['existing_assignment_hashes'] ?? null) || ! array_is_list($user['existing_assignment_hashes'])
                || ! is_array($user['issues'] ?? null) || ! array_is_list($user['issues'])
            ) {
                throw new ArtifactException('The analysis user evidence is invalid.');
            }

            foreach ($user['existing_assignment_hashes'] as $hash) {
                self::assertHex($hash);
            }

            foreach ($user['issues'] as $issue) {
                if (! is_string($issue) || $issue === '') {
                    throw new ArtifactException('The analysis user issue is invalid.');
                }
            }
        }

        foreach ($payload['issues'] as $issue) {
            self::assertKeys($issue, ['code', 'role', 'user_hash']);

            if (! is_string($issue['code'] ?? null) || (($issue['role'] ?? null) !== null && ! is_string($issue['role'])) || (($issue['user_hash'] ?? null) !== null && ! is_string($issue['user_hash']))) {
                throw new ArtifactException('The analysis issue structure is invalid.');
            }
        }

        foreach ($payload['issue_totals'] as $code => $count) {
            if (! is_string($code) || $code === '' || ! is_int($count) || $count < 1) {
                throw new ArtifactException('The analysis issue totals are invalid.');
            }
        }

        self::assertHex($payload['access_parity']['hash'] ?? null);

        if (! is_array($payload['access_parity']['matrix'] ?? null) || array_is_list($payload['access_parity']['matrix'])) {
            throw new ArtifactException('The analysis access parity matrix is invalid.');
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

        foreach ($target['counts'] as $count) {
            if (! is_int($count) || $count < 0) {
                throw new ArtifactException('The analysis target count is invalid.');
            }
        }

        foreach ($target['roles'] as $role) {
            if (! is_string($role) || ! in_array($role, UserRole::values(), true)) {
                throw new ArtifactException('The analysis target role is invalid.');
            }
        }

        foreach ($target['assignment_hashes'] as $hash) {
            self::assertHex($hash);
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

    private static function assertHex(mixed $value): void
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new ArtifactException('The analysis artifact digest is invalid.');
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
