<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class AboutSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/about';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.about_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.about_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::ABOUT;
    }

    protected function subjectSchema(): Tab
    {
        return $this->aboutTab();
    }
}
