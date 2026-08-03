<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SettingsBackupVersion;
use App\Models\User;

/**
 * Backups are shared panel-maintenance artifacts written only by the
 * settings lifecycle managers. Reading follows the panel gate; deletion
 * destroys a shared recovery artifact and is reserved for super-admins
 * (operator ruling 2026-08-03, "for now"); creating or editing rows by hand
 * is never allowed, matching the resource's create-less, edit-less surface.
 */
class SettingsBackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, SettingsBackupVersion $backup): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SettingsBackupVersion $backup): bool
    {
        return false;
    }

    public function delete(User $user, SettingsBackupVersion $backup): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRoleAtLeast(UserRole::SuperAdmin);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin);
    }
}
