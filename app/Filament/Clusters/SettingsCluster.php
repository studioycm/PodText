<?php

namespace App\Filament\Clusters;

use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class SettingsCluster extends Cluster
{
    use UsesAdminNavigationOrder;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.groups.settings');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('admin.navigation.groups.settings');
    }
}
