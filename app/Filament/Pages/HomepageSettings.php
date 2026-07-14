<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class HomepageSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/homepage';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.homepage_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.homepage_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::HOMEPAGE;
    }

    protected function subjectSchema(): Tab
    {
        return $this->homepageTab();
    }
}
