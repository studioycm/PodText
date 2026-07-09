<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use UsesAdminNavigationOrder;
}
