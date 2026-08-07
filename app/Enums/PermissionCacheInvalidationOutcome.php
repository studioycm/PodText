<?php

namespace App\Enums;

enum PermissionCacheInvalidationOutcome: string
{
    case AlreadyAbsent = 'already_absent';
    case Deleted = 'deleted';
}
