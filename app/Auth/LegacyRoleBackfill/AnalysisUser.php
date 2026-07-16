<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class AnalysisUser
{
    /**
     * @param  list<string>  $existingAssignmentHashes
     * @param  list<string>  $issues
     */
    public function __construct(
        public string $userHash,
        public string $rawRoleHash,
        public ?string $role,
        public bool $valid,
        public array $existingAssignmentHashes,
        public ?string $plannedAssignmentHash,
        public array $issues,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_hash' => $this->userHash,
            'raw_role_hash' => $this->rawRoleHash,
            'role' => $this->role,
            'valid' => $this->valid,
            'existing_assignment_hashes' => $this->existingAssignmentHashes,
            'planned_assignment_hash' => $this->plannedAssignmentHash,
            'issues' => $this->issues,
        ];
    }
}
