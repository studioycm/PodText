<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContentItem;
use App\Models\User;

/**
 * Episodes keep the uniform panel-admin authority for daily editorial work —
 * the policy exists so authorization surfaces (the inline status column's
 * disabled() gate, delete actions) have a real home instead of the
 * no-policy void. Deleting an episode destroys its transcript history and
 * is reserved for super-admins (operator ruling 2026-08-05, EQ-6), matching
 * the settings-backup precedent.
 */
class ContentItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, ContentItem $contentItem): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ContentItem $contentItem): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ContentItem $contentItem): bool
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
