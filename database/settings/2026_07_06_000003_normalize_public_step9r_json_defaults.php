<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.menu_config', function (mixed $menuConfig): array {
            $menuConfig = is_object($menuConfig) ? (array) $menuConfig : $menuConfig;
            $menuConfig = is_array($menuConfig) ? $menuConfig : [];

            $themeSelector = is_array($menuConfig['theme_selector'] ?? null) ? $menuConfig['theme_selector'] : [];
            $logo = is_array($menuConfig['logo'] ?? null) ? $menuConfig['logo'] : [];
            $search = is_array($menuConfig['search'] ?? null) ? $menuConfig['search'] : [];

            return [
                'enabled' => (bool) ($menuConfig['enabled'] ?? true),
                'items_alignment' => $menuConfig['items_alignment'] ?? 'center',
                'items' => is_array($menuConfig['items'] ?? null) ? $menuConfig['items'] : [],
                'logo' => [
                    'light_path' => $logo['light_path'] ?? null,
                    'dark_path' => $logo['dark_path'] ?? null,
                    'alt_text' => $logo['alt_text'] ?? __('app.name'),
                    'display_mode' => $logo['display_mode'] ?? 'image',
                    'size' => $logo['size'] ?? 'medium',
                ],
                'search' => [
                    'enabled' => $search['enabled'] ?? true,
                    'placeholder' => $search['placeholder'] ?? __('public.menu.search_placeholder'),
                    'route_key' => $search['route_key'] ?? 'search',
                    'query_param' => $search['query_param'] ?? 'q',
                ],
                'theme_selector' => [
                    'enabled' => $themeSelector['enabled'] ?? true,
                    'mode' => $themeSelector['mode'] ?? 'light_dark_system',
                    'display_mode' => $themeSelector['display_mode'] ?? 'text_icon',
                ],
            ];
        });

        $this->migrator->update('public_content.display_defaults', function (mixed $displayDefaults): array {
            $displayDefaults = is_object($displayDefaults) ? (array) $displayDefaults : $displayDefaults;
            $displayDefaults = is_array($displayDefaults) ? $displayDefaults : [];

            return [
                ...$displayDefaults,
                'image_fit' => $displayDefaults['image_fit'] ?? 'cover',
                'image_radius' => $displayDefaults['image_radius'] ?? 'mid_rounded',
            ];
        });

        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;
            $aboutPage = is_array($aboutPage) ? $aboutPage : [];
            $settings = is_array($aboutPage['settings'] ?? null) ? $aboutPage['settings'] : [];
            $teamCard = is_array($settings['team_card'] ?? null) ? $settings['team_card'] : [];

            $settings['team_card'] = [
                ...$teamCard,
                'image_fit' => $teamCard['image_fit'] ?? 'cover',
                'image_radius' => $teamCard['image_radius'] ?? 'circle',
            ];

            return [
                ...$aboutPage,
                'settings' => $settings,
            ];
        });

        $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage): array {
            $podcastsPage = is_object($podcastsPage) ? (array) $podcastsPage : $podcastsPage;
            $podcastsPage = is_array($podcastsPage) ? $podcastsPage : [];

            return [
                ...$podcastsPage,
                'image_fit' => $podcastsPage['image_fit'] ?? 'cover',
                'image_radius' => $podcastsPage['image_radius'] ?? 'mid_rounded',
            ];
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.menu_config', function (mixed $menuConfig): array {
            $menuConfig = is_object($menuConfig) ? (array) $menuConfig : $menuConfig;

            if (! is_array($menuConfig)) {
                return [];
            }

            unset($menuConfig['items_alignment'], $menuConfig['logo'], $menuConfig['search']);

            if (is_array($menuConfig['theme_selector'] ?? null)) {
                unset($menuConfig['theme_selector']['display_mode']);
            }

            return $menuConfig;
        });

        $this->migrator->update('public_content.display_defaults', function (mixed $displayDefaults): array {
            $displayDefaults = is_object($displayDefaults) ? (array) $displayDefaults : $displayDefaults;
            $displayDefaults = is_array($displayDefaults) ? $displayDefaults : [];

            unset($displayDefaults['image_fit'], $displayDefaults['image_radius']);

            return $displayDefaults;
        });

        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;
            $aboutPage = is_array($aboutPage) ? $aboutPage : [];

            if (is_array($aboutPage['settings']['team_card'] ?? null)) {
                unset($aboutPage['settings']['team_card']['image_fit'], $aboutPage['settings']['team_card']['image_radius']);
            }

            return $aboutPage;
        });

        $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage): array {
            $podcastsPage = is_object($podcastsPage) ? (array) $podcastsPage : $podcastsPage;
            $podcastsPage = is_array($podcastsPage) ? $podcastsPage : [];

            unset($podcastsPage['image_fit'], $podcastsPage['image_radius']);

            return $podcastsPage;
        });
    }
};
