<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Every dashboard widget is a registered Livewire component, so the panel's
 * page-level guard is not the only way in. These widgets return editorial
 * counts, so the guard belongs on the component as well.
 */
trait AdminOnlyWidget
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRoleAtLeast(UserRole::Admin);
    }
}
