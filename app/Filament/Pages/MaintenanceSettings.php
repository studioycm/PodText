<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class MaintenanceSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/maintenance';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.maintenance_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.maintenance_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::MAINTENANCE;
    }

    protected function subjectSchema(): Tab
    {
        return $this->maintenanceTab();
    }
}
