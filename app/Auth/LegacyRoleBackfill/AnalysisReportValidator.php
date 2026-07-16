<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Auth\AbilityCatalog;
use App\Auth\CompatibilityGrantManifest;
use App\Enums\UserRole;

final class AnalysisReportValidator
{
    public function __construct(
        private readonly LegacyRoleBackfillAnalyzer $analyzer,
        private readonly PrivacyHasher $hasher,
    ) {}

    public function validate(AnalysisReport $report): void
    {
        $payload = $report->toArray();
        $contract = $this->analyzer->staticContract();
        $expectedConfigHash = hash('sha256', CanonicalJson::encode([
            'guard' => $contract['guard'],
            'guard_provider' => $contract['guard_provider'],
            'provider_model' => $contract['provider_model'],
            'model_type' => $contract['model_type'],
            'teams' => $contract['teams'],
            'tables' => $contract['tables'],
            'columns' => $contract['columns'],
        ]));
        $expectedSchemaHash = hash('sha256', CanonicalJson::encode($contract['schema']));

        $checks = [
            'ability_catalog_version' => [$payload['evidence']['ability_catalog_version'] ?? null, AbilityCatalog::VERSION],
            'ability_catalog_hash' => [$payload['evidence']['ability_catalog_hash'] ?? null, $contract['ability_catalog_hash']],
            'role_catalog_hash' => [$payload['evidence']['role_catalog_hash'] ?? null, $contract['role_catalog_hash']],
            'grant_manifest_hash' => [$payload['evidence']['grant_manifest_hash'] ?? null, $contract['grant_manifest_hash']],
            'user_role_hash' => [$payload['evidence']['user_role_hash'] ?? null, $contract['user_role_hash']],
            'package_version' => [$payload['evidence']['package_version'] ?? null, $contract['package_version']],
            'config_hash' => [$payload['evidence']['config_hash'] ?? null, $expectedConfigHash],
            'schema_hash' => [$payload['evidence']['schema_hash'] ?? null, $expectedSchemaHash],
            'driver' => [$payload['connection']['driver'] ?? null, $contract['driver']],
            'guard' => [$payload['connection']['guard'] ?? null, $contract['guard']],
            'model_type' => [$payload['connection']['model_type'] ?? null, $contract['model_type']],
            'teams' => [$payload['connection']['teams'] ?? null, false],
            'tables' => [$payload['connection']['tables'] ?? null, $contract['tables']],
            'columns' => [$payload['connection']['columns'] ?? null, $contract['columns']],
            'key_id' => [$payload['connection']['key_id'] ?? null, $contract['key_id']],
            'legacy_authority' => [$payload['legacy_authority'] ?? null, true],
        ];

        foreach ($checks as $field => [$actual, $expected]) {
            $matches = is_array($actual) && is_array($expected)
                ? CanonicalJson::encode($actual) === CanonicalJson::encode($expected)
                : $actual === $expected;

            if (! $matches) {
                throw new BackfillRefusalException("The accepted analysis static contract has drifted at {$field}.");
            }
        }

        $this->validateReconciliation($payload);

        if (! in_array($report->status(), ['ready', 'already_applied'], true) || $report->isBlocked()) {
            throw new BackfillRefusalException('A blocked analysis report cannot be applied.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function validateReconciliation(array $payload): void
    {
        $users = $payload['source']['users'] ?? null;
        $perRole = $payload['source']['per_role'] ?? null;
        $targetBefore = $payload['target_before'] ?? null;
        $targetPlanned = $payload['target_planned'] ?? null;

        if (! is_array($users) || ! array_is_list($users) || ! is_array($perRole) || ! is_array($targetBefore) || ! is_array($targetPlanned)) {
            throw new BackfillRefusalException('The accepted analysis reconciliation structure is invalid.');
        }

        $expectedPerRole = array_fill_keys(UserRole::values(), 0);
        $sourceVector = [];
        $plannedAssignments = [];
        $existingAssignments = [];
        $userHashes = [];
        $previousUserHash = null;
        $modelType = (string) $payload['connection']['model_type'];

        foreach ($users as $user) {
            if (! is_array($user)) {
                throw new BackfillRefusalException('The accepted analysis user evidence is invalid.');
            }

            $userHash = $user['user_hash'] ?? null;
            $rawRoleHash = $user['raw_role_hash'] ?? null;
            $role = $user['role'] ?? null;
            $valid = $user['valid'] ?? null;
            $plannedHash = $user['planned_assignment_hash'] ?? null;
            $existingHashes = $user['existing_assignment_hashes'] ?? null;
            $userIssues = $user['issues'] ?? null;

            if (
                ! is_string($userHash) || preg_match('/\A[a-f0-9]{64}\z/D', $userHash) !== 1
                || ! is_string($rawRoleHash) || preg_match('/\A[a-f0-9]{64}\z/D', $rawRoleHash) !== 1
                || $valid !== true
                || $userIssues !== []
                || ! is_array($existingHashes) || ! array_is_list($existingHashes)
                || ($previousUserHash !== null && strcmp($previousUserHash, $userHash) >= 0)
                || isset($userHashes[$userHash])
            ) {
                throw new BackfillRefusalException('The accepted analysis user hashes are invalid or non-unique.');
            }

            $previousUserHash = $userHash;
            $userHashes[$userHash] = true;

            if (! is_string($role) || ! array_key_exists($role, $expectedPerRole)) {
                throw new BackfillRefusalException('The accepted analysis valid role is invalid.');
            }

            $expectedPerRole[$role]++;
            $expectedAssignment = $this->hasher->fingerprint('assignment', [
                'user_hash' => $userHash,
                'role' => $role,
                'model_type' => $modelType,
            ]);

            if (! is_string($plannedHash) || ! hash_equals($expectedAssignment, $plannedHash)) {
                throw new BackfillRefusalException('The accepted analysis planned assignment hash is invalid.');
            }

            if (
                count($existingHashes) > 1
                || ($existingHashes !== [] && $existingHashes !== [$plannedHash])
            ) {
                throw new BackfillRefusalException('The accepted analysis existing assignments are invalid.');
            }

            $plannedAssignments[] = $plannedHash;
            array_push($existingAssignments, ...$existingHashes);
            $sourceVector[] = [
                'user_hash' => $userHash,
                'raw_role_hash' => $rawRoleHash,
                'role' => $role,
                'valid' => $valid,
            ];
        }

        sort($plannedAssignments, SORT_STRING);
        sort($existingAssignments, SORT_STRING);
        $beforeAssignments = $targetBefore['assignment_hashes'] ?? null;
        $plannedRoles = $targetPlanned['roles'] ?? null;
        $beforeRoles = $targetBefore['roles'] ?? null;

        if (! is_array($beforeAssignments) || ! array_is_list($beforeAssignments) || ! is_array($plannedRoles) || ! is_array($beforeRoles)) {
            throw new BackfillRefusalException('The accepted analysis target vectors are invalid.');
        }

        $sortedBeforeAssignments = $beforeAssignments;
        sort($sortedBeforeAssignments, SORT_STRING);
        $sortedBeforeRoles = $beforeRoles;
        sort($sortedBeforeRoles, SORT_STRING);
        $expectedRoles = UserRole::values();
        sort($expectedRoles, SORT_STRING);

        if (
            (int) ($payload['source']['total'] ?? -1) !== count($users)
            || CanonicalJson::encode($perRole) !== CanonicalJson::encode($expectedPerRole)
            || array_sum($perRole) !== count($users)
            || $plannedAssignments !== ($targetPlanned['assignment_hashes'] ?? null)
            || $plannedRoles !== $expectedRoles
            || $existingAssignments !== $beforeAssignments
            || count(array_unique($beforeRoles)) !== count($beforeRoles)
            || array_diff($beforeRoles, $expectedRoles) !== []
            || $beforeAssignments !== $sortedBeforeAssignments
            || $beforeRoles !== $sortedBeforeRoles
        ) {
            throw new BackfillRefusalException('The accepted analysis totals or ordered target vectors do not reconcile.');
        }

        $beforeCounts = $targetBefore['counts'] ?? null;
        $plannedCounts = $targetPlanned['counts'] ?? null;
        $expectedPlannedCounts = [
            'roles' => count($expectedRoles),
            'assignments' => count($plannedAssignments),
            'permissions' => 0,
            'role_grants' => 0,
            'direct_grants' => 0,
        ];
        $expectedBeforeCounts = [
            'roles' => count($beforeRoles),
            'assignments' => count($beforeAssignments),
            'permissions' => 0,
            'role_grants' => 0,
            'direct_grants' => 0,
        ];

        if (
            ! is_array($beforeCounts)
            || ! is_array($plannedCounts)
            || CanonicalJson::encode($beforeCounts) !== CanonicalJson::encode($expectedBeforeCounts)
            || CanonicalJson::encode($plannedCounts) !== CanonicalJson::encode($expectedPlannedCounts)
        ) {
            throw new BackfillRefusalException('The accepted analysis target counts do not reconcile.');
        }

        if (($payload['issues'] ?? null) !== [] || ($payload['issue_totals'] ?? null) !== []) {
            throw new BackfillRefusalException('An applicable analysis report must contain no blocker issues.');
        }

        $expectedFingerprints = [
            'source' => $this->hasher->fingerprint('source', $sourceVector),
            'assignment_before' => $this->hasher->fingerprint('assignment-before', $beforeAssignments),
            'target_before' => $this->hasher->fingerprint('target-state', [
                'counts' => $beforeCounts,
                'roles' => $beforeRoles,
                'assignments' => $beforeAssignments,
            ]),
            'target_planned' => $this->hasher->fingerprint('target-state', [
                'counts' => $plannedCounts,
                'roles' => $plannedRoles,
                'assignments' => $plannedAssignments,
            ]),
        ];

        foreach ($expectedFingerprints as $key => $expectedFingerprint) {
            $actual = $payload['fingerprints'][$key] ?? null;

            if (! is_string($actual) || ! hash_equals($expectedFingerprint, $actual)) {
                throw new BackfillRefusalException("The accepted analysis {$key} fingerprint does not reconcile.");
            }
        }

        $matrix = $payload['access_parity']['matrix'] ?? null;
        $matrixHash = $payload['access_parity']['hash'] ?? null;
        $expectedMatrix = [];
        $grants = CompatibilityGrantManifest::grants();

        foreach (UserRole::cases() as $role) {
            $expectedMatrix[$role->value] = [
                'abilities' => $grants[$role->value],
                'ability_count' => count($grants[$role->value]),
                'rank' => $role->rank(),
                'admin_panel' => $role->isAtLeast(UserRole::Admin),
                'horizon' => $role->isAtLeast(UserRole::Admin),
                'maintenance_bypass' => $role->isAtLeast(UserRole::Admin),
                'super_admin' => $role === UserRole::SuperAdmin,
            ];
        }

        if (
            ! is_array($matrix)
            || CanonicalJson::encode($matrix) !== CanonicalJson::encode($expectedMatrix)
            || ! is_string($matrixHash)
            || ! hash_equals(hash('sha256', CanonicalJson::encode($expectedMatrix)), $matrixHash)
        ) {
            throw new BackfillRefusalException('The accepted analysis access-parity evidence does not reconcile.');
        }

        $expectedStatus = CanonicalJson::encode($targetBefore) === CanonicalJson::encode($targetPlanned)
            ? 'already_applied'
            : 'ready';

        if (($payload['status'] ?? null) !== $expectedStatus) {
            throw new BackfillRefusalException('The accepted analysis status does not reconcile.');
        }
    }
}
