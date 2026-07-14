<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class CardTemplateSettings extends PublicContentSettingsSubjectPage
{
    protected static ?string $slug = 'settings/card-templates';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.card_template_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.card_template_settings.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::CARD_TEMPLATES;
    }

    protected function subjectSchema(): Tab
    {
        return $this->cardTemplatesTab();
    }
}
