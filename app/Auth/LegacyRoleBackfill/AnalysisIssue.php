<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class AnalysisIssue
{
    public const ASSIGNMENT_MULTIPLE = 'assignment_multiple';

    public const ASSIGNMENT_ORPHAN_USER = 'assignment_orphan_user';

    public const ASSIGNMENT_UNKNOWN_ROLE = 'assignment_unknown_role';

    public const ASSIGNMENT_WRONG_MODEL_TYPE = 'assignment_wrong_model_type';

    public const ASSIGNMENT_WRONG_ROLE = 'assignment_wrong_role';

    public const CATALOG_DRIFT = 'catalog_drift';

    public const CONFIG_COLUMN_DRIFT = 'config_column_drift';

    public const CONFIG_GUARD_DRIFT = 'config_guard_drift';

    public const CONFIG_MODEL_TYPE_DRIFT = 'config_model_type_drift';

    public const CONFIG_PROVIDER_DRIFT = 'config_provider_drift';

    public const CONFIG_TABLE_DRIFT = 'config_table_drift';

    public const CONFIG_TEAMS_ENABLED = 'config_teams_enabled';

    public const DIRECT_GRANT_ROWS_PRESENT = 'direct_grant_rows_present';

    public const PACKAGE_VERSION_DRIFT = 'package_version_drift';

    public const PERMISSION_ROWS_PRESENT = 'permission_rows_present';

    public const ROLE_CASE_COLLISION = 'role_case_collision';

    public const ROLE_DUPLICATE = 'role_duplicate';

    public const ROLE_GRANT_ROWS_PRESENT = 'role_grant_rows_present';

    public const ROLE_UNKNOWN = 'role_unknown';

    public const ROLE_WRONG_GUARD = 'role_wrong_guard';

    public const SCHEMA_COLUMN_DRIFT = 'schema_column_drift';

    public const SCHEMA_COLUMN_PROPERTY_DRIFT = 'schema_column_property_drift';

    public const SCHEMA_FOREIGN_KEY_DRIFT = 'schema_foreign_key_drift';

    public const SCHEMA_MISSING_TABLE = 'schema_missing_table';

    public const SCHEMA_PRIMARY_KEY_DRIFT = 'schema_primary_key_drift';

    public const SCHEMA_SECONDARY_INDEX_DRIFT = 'schema_secondary_index_drift';

    public const SCHEMA_TEAM_COLUMN_PRESENT = 'schema_team_column_present';

    public const SCHEMA_UNIQUE_INDEX_DRIFT = 'schema_unique_index_drift';

    public const SOURCE_DUPLICATE_IDENTITY = 'source_duplicate_identity';

    public const SOURCE_INVALID_IDENTITY_TYPE = 'source_invalid_identity_type';

    public const SOURCE_INVALID_ROLE = 'source_invalid_role';

    public const SOURCE_INVALID_ROLE_TYPE = 'source_invalid_role_type';

    public function __construct(
        public string $code,
        public ?string $userHash = null,
        public ?string $role = null,
    ) {}

    /** @return array{code: string, user_hash: ?string, role: ?string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'user_hash' => $this->userHash,
            'role' => $this->role,
        ];
    }
}
