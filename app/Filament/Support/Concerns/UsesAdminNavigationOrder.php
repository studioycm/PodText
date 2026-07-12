<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\AdminNavigationOrder;
use UnitEnum;

trait UsesAdminNavigationOrder
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return AdminNavigationOrder::group(static::class);
    }

    public static function getNavigationSort(): ?int
    {
        return AdminNavigationOrder::sort(static::class);
    }
}
