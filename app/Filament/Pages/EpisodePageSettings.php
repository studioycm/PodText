<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class EpisodePageSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/episode-page';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.episode_page_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.episode_page_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::EPISODE_PAGE;
    }

    protected function subjectSchema(): Tab
    {
        return $this->episodePageTab();
    }
}
