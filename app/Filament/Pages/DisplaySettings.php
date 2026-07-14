<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class DisplaySettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/display';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.display_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.display_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::DISPLAY;
    }

    protected function subjectSchema(): Tab
    {
        return $this->displayTab();
    }
}
