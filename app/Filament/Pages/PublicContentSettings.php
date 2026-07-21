<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Pages\Page;

class PublicContentSettings extends Page
{
    protected static ?string $slug = 'public-content-settings';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRoleAtLeast(UserRole::Admin);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $page = match (request()->query('public-content-tab')) {
            SettingsSubjectOwnershipRegistry::DISPLAY => DisplaySettings::class,
            'item-page' => EpisodePageSettings::class,
            SettingsSubjectOwnershipRegistry::MENU_HEADER => MenuHeaderSettings::class,
            SettingsSubjectOwnershipRegistry::PODCASTS => PodcastSettings::class,
            SettingsSubjectOwnershipRegistry::CONTRIBUTORS => ContributorSettings::class,
            SettingsSubjectOwnershipRegistry::ABOUT => AboutSettings::class,
            SettingsSubjectOwnershipRegistry::MAINTENANCE => MaintenanceSettings::class,
            'advanced' => CardTemplateSettings::class,
            default => HomepageSettings::class,
        };

        $this->redirect($page::getUrl());
    }

    public function getTitle(): string
    {
        return __('admin.pages.public_content_settings.title');
    }
}
