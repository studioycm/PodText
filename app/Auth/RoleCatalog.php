<?php

namespace App\Auth;

use App\Enums\UserRole;

final class RoleCatalog
{
    /**
     * @return list<RoleDefinition>
     */
    public static function definitions(): array
    {
        return [
            new RoleDefinition(UserRole::SuperAdmin->value, protected: true, reserved: true, delegable: false),
            new RoleDefinition(UserRole::Admin->value, protected: true, reserved: true, delegable: false),
            new RoleDefinition(UserRole::Moderator->value, protected: true, reserved: false, delegable: true),
            new RoleDefinition(UserRole::Transcriber->value, protected: true, reserved: false, delegable: true),
            new RoleDefinition(UserRole::User->value, protected: true, reserved: false, delegable: true),
        ];
    }
}
