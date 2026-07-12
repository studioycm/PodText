<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AdminTools extends Page
{
    use UsesAdminNavigationOrder;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $slug = 'tools';

    protected string $view = 'filament.pages.admin-tools';

    public static function getNavigationLabel(): string
    {
        return __('admin.tools.pages.tools.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.tools.pages.tools.title');
    }
}
