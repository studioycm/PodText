<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class ContributorSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/contributors';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.contributor_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.contributor_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::CONTRIBUTORS;
    }

    protected function subjectSchema(): Tab
    {
        return $this->contributorsTab();
    }
}
