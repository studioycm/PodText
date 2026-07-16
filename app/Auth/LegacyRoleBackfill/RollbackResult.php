<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class RollbackResult
{
    public function __construct(
        public string $status,
        public string $beforeFingerprint,
        public string $afterFingerprint,
        public ?string $receiptName,
        public int $deletedAssignments,
    ) {}
}
