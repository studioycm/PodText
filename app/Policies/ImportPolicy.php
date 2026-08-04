<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;

/**
 * Filament's failure-CSV controller honours a `view` policy on the Import
 * model and otherwise allows only the import's owner. Editorial triage is a
 * team activity: any admin may read failure CSVs; everyone else stays with
 * the owner-only rule. The signed-URL requirement is enforced by the
 * controller before this policy runs.
 */
class ImportPolicy
{
    /**
     * With a policy registered, a missing ability method is a DENY — the
     * read-only listing (ListImports) cannot render without this.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin);
    }

    public function view(User $user, Import $import): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin)
            || $import->user()->is($user);
    }

    /** Rows are created by the import modal and future fetch runs, never by hand. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Import $import): bool
    {
        return false;
    }

    public function delete(User $user, Import $import): bool
    {
        return false;
    }
}
