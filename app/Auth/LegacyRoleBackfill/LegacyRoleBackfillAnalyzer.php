<?php

namespace App\Auth\LegacyRoleBackfill;

use App\Auth\AbilityCatalog;
use App\Auth\AuthorizationFoundationValidator;
use App\Auth\CompatibilityGrantManifest;
use App\Auth\RoleCatalog;
use App\Enums\UserRole;
use App\Models\User;
use Composer\InstalledVersions;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LegacyRoleBackfillAnalyzer
{
    /** @var list<string> */
    private const TABLE_KEYS = [
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ];

    /** @var array<string, list<string>> */
    private const EXPECTED_COLUMNS = [
        'roles' => ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
        'permissions' => ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
        'model_has_roles' => ['role_id', 'model_type', 'model_id'],
        'model_has_permissions' => ['permission_id', 'model_type', 'model_id'],
        'role_has_permissions' => ['permission_id', 'role_id'],
    ];

    public function __construct(private readonly PrivacyHasher $hasher) {}

    public function analyze(): AnalysisReport
    {
        return DB::transaction(fn (): AnalysisReport => $this->buildReport(lock: false));
    }

    public function analyzeLocked(): AnalysisReport
    {
        return $this->buildReport(lock: true);
    }

    /**
     * @param  list<object|array{id: mixed, role: mixed}>  $rows
     */
    public function analyzeSourceRows(array $rows): AnalysisReport
    {
        return DB::transaction(fn (): AnalysisReport => $this->buildReport(lock: false, sourceRows: $rows));
    }

    /** @return array<string, mixed> */
    public function staticContract(): array
    {
        $tables = $this->tableNames();
        $columns = $this->columnNames();
        $schema = [];

        foreach (self::TABLE_KEYS as $key) {
            $table = $tables[$key];
            $schema[$key] = Schema::hasTable($table)
                ? array_values(Schema::getColumnListing($table))
                : [];
            sort($schema[$key], SORT_STRING);
        }

        $rolePayload = array_map(
            fn ($definition): array => $definition->toArray(),
            RoleCatalog::definitions(),
        );

        return [
            'ability_catalog_version' => AbilityCatalog::VERSION,
            'ability_catalog_hash' => AbilityCatalog::hash(),
            'role_catalog_hash' => hash('sha256', CanonicalJson::encode($rolePayload)),
            'grant_manifest_hash' => hash('sha256', CanonicalJson::encode(CompatibilityGrantManifest::grants())),
            'user_role_hash' => hash('sha256', CanonicalJson::encode(UserRole::values())),
            'package_version' => InstalledVersions::getPrettyVersion('spatie/laravel-permission') ?? 'unknown',
            'driver' => DB::connection()->getDriverName(),
            'guard' => config('auth.defaults.guard'),
            'guard_provider' => config('auth.guards.web.provider'),
            'provider_model' => config('auth.providers.users.model'),
            'model_type' => (new User)->getMorphClass(),
            'teams' => config('permission.teams'),
            'tables' => $tables,
            'columns' => $columns,
            'schema' => $schema,
            'key_id' => $this->hasher->keyId(),
        ];
    }

    /**
     * @param  list<object|array{id: mixed, role: mixed}>|null  $sourceRows
     */
    private function buildReport(bool $lock, ?array $sourceRows = null): AnalysisReport
    {
        $staticIssues = [];

        try {
            AuthorizationFoundationValidator::assertFoundation();
        } catch (Throwable) {
            $staticIssues[] = new AnalysisIssue('catalog_drift');
        }

        $contract = $this->staticContract();
        $this->validateStaticContract($contract, $staticIssues);
        $tables = $contract['tables'];
        $columns = $contract['columns'];

        $sourceRows ??= $this->sourceQuery($lock)->get()->all();
        [$users, $userInternal, $sourceIssues, $perRole] = $this->scanSource($sourceRows, $contract['model_type']);
        [$target, $targetIssues, $assignmentHashesByUser] = $this->scanTarget(
            $tables,
            $columns,
            $userInternal,
            $contract['model_type'],
            $lock,
        );

        $userRows = [];

        foreach ($users as $user) {
            $existingHashes = $assignmentHashesByUser[$user['user_hash']] ?? [];
            sort($existingHashes, SORT_STRING);
            $issues = $user['issues'];

            foreach ($targetIssues as $issue) {
                if ($issue->userHash === $user['user_hash']) {
                    $issues[] = $issue->code;
                }
            }

            $issues = array_values(array_unique($issues));
            sort($issues, SORT_STRING);
            $userRows[] = (new AnalysisUser(
                userHash: $user['user_hash'],
                rawRoleHash: $user['raw_role_hash'],
                role: $user['role'],
                valid: $user['valid'],
                existingAssignmentHashes: $existingHashes,
                plannedAssignmentHash: $user['planned_assignment_hash'],
                issues: $issues,
            ))->toArray();
        }

        usort($userRows, fn (array $left, array $right): int => $left['user_hash'] <=> $right['user_hash']);
        $issues = [...$staticIssues, ...$sourceIssues, ...$targetIssues];
        usort($issues, function (AnalysisIssue $left, AnalysisIssue $right): int {
            return [$left->code, $left->userHash ?? '', $left->role ?? '']
                <=> [$right->code, $right->userHash ?? '', $right->role ?? ''];
        });
        $issueRows = array_map(fn (AnalysisIssue $issue): array => $issue->toArray(), $issues);
        $issueTotals = array_count_values(array_column($issueRows, 'code'));
        ksort($issueTotals, SORT_STRING);

        $sourceVector = array_map(
            fn (array $user): array => [
                'user_hash' => $user['user_hash'],
                'raw_role_hash' => $user['raw_role_hash'],
                'role' => $user['role'],
                'valid' => $user['valid'],
            ],
            $userRows,
        );
        $plannedAssignmentHashes = array_values(array_filter(array_column($userRows, 'planned_assignment_hash'), 'is_string'));
        sort($plannedAssignmentHashes, SORT_STRING);
        $beforeAssignmentHashes = $target['assignment_hashes'];
        sort($beforeAssignmentHashes, SORT_STRING);
        $plannedRoles = UserRole::values();
        sort($plannedRoles, SORT_STRING);

        $targetBeforeVector = [
            'counts' => $target['counts'],
            'roles' => $target['roles'],
            'assignments' => $beforeAssignmentHashes,
        ];
        $targetPlannedVector = [
            'counts' => [
                'roles' => count($plannedRoles),
                'assignments' => count($plannedAssignmentHashes),
                'permissions' => 0,
                'role_grants' => 0,
                'direct_grants' => 0,
            ],
            'roles' => $plannedRoles,
            'assignments' => $plannedAssignmentHashes,
        ];
        $targetBeforeFingerprint = $this->hasher->fingerprint('target-state', $targetBeforeVector);
        $targetPlannedFingerprint = $this->hasher->fingerprint('target-state', $targetPlannedVector);
        $status = $issues !== []
            ? 'blocked'
            : ($targetBeforeVector === $targetPlannedVector ? 'already_applied' : 'ready');
        $accessParity = $this->accessParity();

        return AnalysisReport::create([
            'generated_at' => now('UTC')->toIso8601ZuluString(),
            'evidence' => [
                'ability_catalog_version' => $contract['ability_catalog_version'],
                'ability_catalog_hash' => $contract['ability_catalog_hash'],
                'role_catalog_hash' => $contract['role_catalog_hash'],
                'grant_manifest_hash' => $contract['grant_manifest_hash'],
                'user_role_hash' => $contract['user_role_hash'],
                'package_version' => $contract['package_version'],
                'config_hash' => hash('sha256', CanonicalJson::encode([
                    'guard' => $contract['guard'],
                    'guard_provider' => $contract['guard_provider'],
                    'provider_model' => $contract['provider_model'],
                    'model_type' => $contract['model_type'],
                    'teams' => $contract['teams'],
                    'tables' => $contract['tables'],
                    'columns' => $contract['columns'],
                ])),
                'schema_hash' => hash('sha256', CanonicalJson::encode($contract['schema'])),
            ],
            'connection' => [
                'driver' => $contract['driver'],
                'guard' => $contract['guard'],
                'model_type' => $contract['model_type'],
                'teams' => $contract['teams'],
                'tables' => $contract['tables'],
                'columns' => $contract['columns'],
                'key_id' => $contract['key_id'],
            ],
            'status' => $status,
            'legacy_authority' => true,
            'source' => [
                'total' => count($users),
                'per_role' => $perRole,
                'users' => $userRows,
            ],
            'target_before' => [
                'counts' => $target['counts'],
                'roles' => $target['roles'],
                'assignment_hashes' => $beforeAssignmentHashes,
            ],
            'target_planned' => [
                'counts' => $targetPlannedVector['counts'],
                'roles' => $plannedRoles,
                'assignment_hashes' => $plannedAssignmentHashes,
            ],
            'issues' => $issueRows,
            'issue_totals' => $issueTotals,
            'access_parity' => [
                'matrix' => $accessParity,
                'hash' => hash('sha256', CanonicalJson::encode($accessParity)),
            ],
            'fingerprints' => [
                'source' => $this->hasher->fingerprint('source', $sourceVector),
                'assignment_before' => $this->hasher->fingerprint('assignment-before', $beforeAssignmentHashes),
                'target_before' => $targetBeforeFingerprint,
                'target_planned' => $targetPlannedFingerprint,
            ],
        ]);
    }

    /** @param array<string, mixed> $contract @param list<AnalysisIssue> $issues */
    private function validateStaticContract(array $contract, array &$issues): void
    {
        if ($contract['teams'] !== false) {
            $issues[] = new AnalysisIssue('config_teams_enabled');
        }

        if ($contract['guard_provider'] !== 'users' || $contract['provider_model'] !== User::class) {
            $issues[] = new AnalysisIssue('config_provider_drift');
        }

        if ($contract['guard'] !== 'web') {
            $issues[] = new AnalysisIssue('config_guard_drift');
        }

        if ($contract['package_version'] !== '7.3.0') {
            $issues[] = new AnalysisIssue('package_version_drift');
        }

        foreach (self::TABLE_KEYS as $key) {
            if ($contract['tables'][$key] !== $key) {
                $issues[] = new AnalysisIssue('config_table_drift');
            }

            $actual = $contract['schema'][$key];
            $expected = self::EXPECTED_COLUMNS[$key];
            sort($expected, SORT_STRING);

            if ($actual === []) {
                $issues[] = new AnalysisIssue('schema_missing_table');
            } elseif ($actual !== $expected) {
                $issues[] = new AnalysisIssue(in_array('team_id', $actual, true) ? 'schema_team_column_present' : 'schema_column_drift');
            }
        }

        if (
            $contract['columns']['role_pivot_key'] !== 'role_id'
            || $contract['columns']['permission_pivot_key'] !== 'permission_id'
            || $contract['columns']['model_morph_key'] !== 'model_id'
            || $contract['columns']['team_foreign_key'] !== 'team_id'
        ) {
            $issues[] = new AnalysisIssue('config_column_drift');
        }
    }

    /**
     * @param  list<object|array{id: mixed, role: mixed}>  $rows
     * @return array{list<array<string, mixed>>, array<string, array<string, mixed>>, list<AnalysisIssue>, array<string, int>}
     */
    private function scanSource(array $rows, string $modelType): array
    {
        $knownRoles = UserRole::values();
        $perRole = array_fill_keys($knownRoles, 0);
        $seen = [];
        $users = [];
        $internal = [];
        $issues = [];

        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : ($row->id ?? null);
            $rawRole = is_array($row) ? ($row['role'] ?? null) : ($row->role ?? null);
            $identity = CanonicalJson::encode(['type' => get_debug_type($id), 'value' => $id]);
            $userHash = is_int($id) || is_string($id) ? $this->hasher->userHash($id) : $this->hasher->fingerprint('invalid-user-id', $identity);
            $userIssues = [];

            if (! is_int($id) && ! is_string($id)) {
                $userIssues[] = 'source_invalid_identity_type';
            }

            if (isset($seen[$identity])) {
                $userIssues[] = 'source_duplicate_identity';
            }

            $seen[$identity] = true;
            $validRole = is_string($rawRole) && in_array($rawRole, $knownRoles, true) ? $rawRole : null;

            if (! is_string($rawRole)) {
                $userIssues[] = 'source_invalid_role_type';
            } elseif ($validRole === null) {
                $userIssues[] = 'source_invalid_role';
            }

            if ($validRole !== null) {
                $perRole[$validRole]++;
            }

            $userIssues = array_values(array_unique($userIssues));
            sort($userIssues, SORT_STRING);

            foreach ($userIssues as $code) {
                $issues[] = new AnalysisIssue($code, $userHash, $validRole);
            }

            $plannedHash = $validRole === null
                ? null
                : $this->assignmentHash($userHash, $validRole, $modelType);
            $users[] = [
                'user_hash' => $userHash,
                'raw_role_hash' => $this->hasher->rawRoleHash($rawRole),
                'role' => $validRole,
                'valid' => $userIssues === [],
                'planned_assignment_hash' => $plannedHash,
                'issues' => $userIssues,
            ];
            $internal[(string) $id] = [
                'id' => $id,
                'user_hash' => $userHash,
                'role' => $validRole,
                'valid' => $userIssues === [],
            ];
        }

        return [$users, $internal, $issues, $perRole];
    }

    /**
     * @param  array<string, string>  $tables
     * @param  array<string, string>  $columns
     * @param  array<string, array<string, mixed>>  $users
     * @return array{array<string, mixed>, list<AnalysisIssue>, array<string, list<string>>}
     */
    private function scanTarget(array $tables, array $columns, array $users, string $modelType, bool $lock): array
    {
        $empty = [
            'counts' => ['roles' => 0, 'assignments' => 0, 'permissions' => 0, 'role_grants' => 0, 'direct_grants' => 0],
            'roles' => [],
            'assignment_hashes' => [],
        ];

        foreach (self::TABLE_KEYS as $key) {
            if (! Schema::hasTable($tables[$key])) {
                return [$empty, [], []];
            }
        }

        $roleRows = $this->lockedQuery($tables['roles'], $lock)->orderBy('id')->get(['id', 'name', 'guard_name']);
        $permissionRows = $this->lockedQuery($tables['permissions'], $lock)->orderBy('id')->get(['id']);
        $grantRows = $this->lockedQuery($tables['role_has_permissions'], $lock)
            ->orderBy($columns['permission_pivot_key'])
            ->orderBy($columns['role_pivot_key'])
            ->get([$columns['permission_pivot_key'], $columns['role_pivot_key']]);
        $directGrantRows = $this->lockedQuery($tables['model_has_permissions'], $lock)
            ->orderBy($columns['permission_pivot_key'])
            ->orderBy($columns['model_morph_key'])
            ->orderBy('model_type')
            ->get([$columns['permission_pivot_key'], $columns['model_morph_key'], 'model_type']);
        $permissionCount = $permissionRows->count();
        $grantCount = $grantRows->count();
        $directGrantCount = $directGrantRows->count();
        $assignmentRows = $this->lockedQuery($tables['model_has_roles'], $lock)
            ->orderBy($columns['role_pivot_key'])
            ->orderBy($columns['model_morph_key'])
            ->orderBy('model_type')
            ->get();
        $issues = [];
        $knownRoles = UserRole::values();
        $roleById = [];
        $roles = [];
        $seenNormalized = [];

        foreach ($roleRows as $roleRow) {
            $name = $roleRow->name;
            $guard = $roleRow->guard_name;
            $canonicalRole = is_string($name) && in_array($name, $knownRoles, true) ? $name : null;
            $normalized = is_string($name) ? strtolower($name) : '';

            if ($canonicalRole === null) {
                $issues[] = new AnalysisIssue(in_array($normalized, array_map('strtolower', $knownRoles), true) ? 'role_case_collision' : 'role_unknown');
            } elseif ($guard !== 'web') {
                $issues[] = new AnalysisIssue('role_wrong_guard', role: $canonicalRole);
            }

            if ($normalized !== '' && isset($seenNormalized[$normalized])) {
                $issues[] = new AnalysisIssue('role_duplicate', role: $canonicalRole);
            }

            $seenNormalized[$normalized] = true;
            $roleById[(string) $roleRow->id] = ['role' => $canonicalRole, 'guard' => $guard];

            if ($canonicalRole !== null && $guard === 'web') {
                $roles[] = $canonicalRole;
            }
        }

        sort($roles, SORT_STRING);

        if ($permissionCount > 0) {
            $issues[] = new AnalysisIssue('permission_rows_present');
        }

        if ($grantCount > 0) {
            $issues[] = new AnalysisIssue('role_grant_rows_present');
        }

        if ($directGrantCount > 0) {
            $issues[] = new AnalysisIssue('direct_grant_rows_present');
        }

        $assignmentHashes = [];
        $byUser = [];

        foreach ($assignmentRows as $assignmentRow) {
            $roleId = (string) $assignmentRow->{$columns['role_pivot_key']};
            $modelId = (string) $assignmentRow->{$columns['model_morph_key']};
            $assignedModelType = $assignmentRow->model_type;
            $user = $users[$modelId] ?? null;
            $role = $roleById[$roleId]['role'] ?? null;
            $userHash = is_array($user) ? $user['user_hash'] : null;

            if ($assignedModelType !== $modelType) {
                $issues[] = new AnalysisIssue('assignment_wrong_model_type', $userHash, $role);
            }

            if ($user === null) {
                $issues[] = new AnalysisIssue('assignment_orphan_user', role: $role);
            }

            if ($role === null || ($roleById[$roleId]['guard'] ?? null) !== 'web') {
                $issues[] = new AnalysisIssue('assignment_unknown_role', $userHash);
            }

            if ($user === null || $role === null || $assignedModelType !== $modelType) {
                continue;
            }

            $hash = $this->assignmentHash($user['user_hash'], $role, $modelType);
            $assignmentHashes[] = $hash;
            $byUser[$user['user_hash']][] = $hash;

            if (! $user['valid'] || $user['role'] !== $role) {
                $issues[] = new AnalysisIssue('assignment_wrong_role', $user['user_hash'], $role);
            }
        }

        foreach ($byUser as $userHash => $hashes) {
            if (count($hashes) > 1) {
                $issues[] = new AnalysisIssue('assignment_multiple', $userHash);
            }
        }

        sort($assignmentHashes, SORT_STRING);

        return [[
            'counts' => [
                'roles' => count($roleRows),
                'assignments' => count($assignmentRows),
                'permissions' => $permissionCount,
                'role_grants' => $grantCount,
                'direct_grants' => $directGrantCount,
            ],
            'roles' => $roles,
            'assignment_hashes' => $assignmentHashes,
        ], $issues, $byUser];
    }

    private function sourceQuery(bool $lock): Builder
    {
        $query = DB::table((new User)->getTable())->select(['id', 'role'])->orderBy('id');

        return $lock ? $query->lockForUpdate() : $query;
    }

    private function lockedQuery(string $table, bool $lock): Builder
    {
        $query = DB::table($table);

        return $lock ? $query->lockForUpdate() : $query;
    }

    /** @return array<string, string> */
    private function tableNames(): array
    {
        return array_map(
            fn (string $key): string => (string) config("permission.table_names.{$key}"),
            array_combine(self::TABLE_KEYS, self::TABLE_KEYS),
        );
    }

    /** @return array<string, string> */
    private function columnNames(): array
    {
        return [
            'role_pivot_key' => (string) (config('permission.column_names.role_pivot_key') ?: 'role_id'),
            'permission_pivot_key' => (string) (config('permission.column_names.permission_pivot_key') ?: 'permission_id'),
            'model_morph_key' => (string) config('permission.column_names.model_morph_key'),
            'team_foreign_key' => (string) config('permission.column_names.team_foreign_key'),
        ];
    }

    private function assignmentHash(string $userHash, string $role, string $modelType): string
    {
        return $this->hasher->fingerprint('assignment', [
            'user_hash' => $userHash,
            'role' => $role,
            'model_type' => $modelType,
        ]);
    }

    /** @return array<string, array<string, bool|int|list<string>>> */
    private function accessParity(): array
    {
        $matrix = [];
        $grants = CompatibilityGrantManifest::grants();

        foreach (UserRole::cases() as $role) {
            $matrix[$role->value] = [
                'abilities' => $grants[$role->value],
                'ability_count' => count($grants[$role->value]),
                'rank' => $role->rank(),
                'admin_panel' => $role->isAtLeast(UserRole::Admin),
                'horizon' => $role->isAtLeast(UserRole::Admin),
                'maintenance_bypass' => $role->isAtLeast(UserRole::Admin),
                'super_admin' => $role === UserRole::SuperAdmin,
            ];
        }

        return $matrix;
    }
}
