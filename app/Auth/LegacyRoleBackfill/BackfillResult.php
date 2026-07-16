<?php

namespace App\Auth\LegacyRoleBackfill;

final readonly class BackfillResult
{
    public function __construct(
        public string $status,
        public string $sourceFingerprint,
        public string $afterFingerprint,
        public ?string $receiptName,
        public int $insertedRoles,
        public int $insertedAssignments,
    ) {}
}
