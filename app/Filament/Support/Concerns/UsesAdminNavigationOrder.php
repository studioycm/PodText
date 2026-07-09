<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\AdminNavigationOrder;

trait UsesAdminNavigationOrder
{
    public static function getNavigationSort(): ?int
    {
        return AdminNavigationOrder::sort(static::class);
    }
}
