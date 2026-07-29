<?php

namespace App\Filament\Clusters;

use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class SystemCluster extends Cluster
{
    use UsesAdminNavigationOrder;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.groups.system_management');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('admin.navigation.groups.system_management');
    }
}
