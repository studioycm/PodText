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
    public function view(User $user, Import $import): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin)
            || $import->user()->is($user);
    }
}
