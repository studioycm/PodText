<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class PodcastSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/podcasts';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.podcast_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.podcast_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::PODCASTS;
    }

    protected function subjectSchema(): Tab
    {
        return $this->podcastsTab();
    }
}
