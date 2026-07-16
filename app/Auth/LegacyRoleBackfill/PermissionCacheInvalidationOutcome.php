<?php

namespace App\Auth\LegacyRoleBackfill;

enum PermissionCacheInvalidationOutcome: string
{
    case AlreadyAbsent = 'already_absent';
    case Deleted = 'deleted';
}
