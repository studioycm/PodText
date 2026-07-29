<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\SettingsCluster;
use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class MenuHeaderSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'menu-header';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.menu_header_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.menu_header_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::MENU_HEADER;
    }

    protected function subjectSchema(): Tab
    {
        return $this->menuHeaderTab();
    }
}
