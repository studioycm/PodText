<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.menu_config', function (mixed $menuConfig): array {
            $menuConfig = is_object($menuConfig) ? (array) $menuConfig : $menuConfig;

            if (! is_array($menuConfig)) {
                $menuConfig = [];
            }

            $items = is_array($menuConfig['items'] ?? null) ? $menuConfig['items'] : [];
            $hasConfiguredItems = $items !== [];

            return [
                'enabled' => $hasConfiguredItems ? (bool) ($menuConfig['enabled'] ?? true) : true,
                'items' => $hasConfiguredItems ? $items : [
                    [
                        'key' => 'home',
                        'type' => 'route',
                        'label' => __('public.menu.routes.home'),
                        'route_key' => 'home',
                        'visible' => true,
                        'sort' => 10,
                    ],
                    [
                        'key' => 'podcasts',
                        'type' => 'route',
                        'label' => __('public.menu.routes.podcasts'),
                        'route_key' => 'podcasts',
                        'visible' => true,
                        'sort' => 20,
                    ],
                    [
                        'key' => 'about',
                        'type' => 'route',
                        'label' => __('public.menu.routes.about'),
                        'route_key' => 'about',
                        'visible' => true,
                        'sort' => 30,
                    ],
                    [
                        'key' => 'request_transcription',
                        'type' => 'public_form',
                        'label' => __('public.menu.forms.request_transcription'),
                        'form_key' => 'request_transcription',
                        'display_mode' => 'slide_over',
                        'visible' => true,
                        'sort' => 40,
                    ],
                    [
                        'key' => 'volunteer_transcriber',
                        'type' => 'public_form',
                        'label' => __('public.menu.forms.volunteer_transcriber'),
                        'form_key' => 'volunteer_transcriber',
                        'display_mode' => 'modal',
                        'visible' => true,
                        'sort' => 50,
                    ],
                    [
                        'key' => 'theme_selector',
                        'type' => 'theme_selector',
                        'label' => __('public.menu.theme'),
                        'visible' => true,
                        'sort' => 60,
                    ],
                ],
                'theme_selector' => is_array($menuConfig['theme_selector'] ?? null) ? $menuConfig['theme_selector'] : [
                    'enabled' => true,
                    'mode' => 'light_dark_system',
                ],
            ];
        });

        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;

            if (! is_array($aboutPage)) {
                $aboutPage = [];
            }

            $settings = is_array($aboutPage['settings'] ?? null) ? $aboutPage['settings'] : [];
            $settings['team_card'] = is_array($settings['team_card'] ?? null) ? $settings['team_card'] : [
                'show_image' => true,
                'image_size' => 'medium',
                'layout' => $settings['team_layout'] ?? 'grid',
                'density' => 'comfortable',
                'show_title' => true,
                'show_description' => true,
                'description_lines' => 3,
            ];

            return [
                ...$aboutPage,
                'settings' => $settings,
            ];
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.menu_config', function (mixed $menuConfig): array {
            $menuConfig = is_object($menuConfig) ? (array) $menuConfig : $menuConfig;

            if (! is_array($menuConfig)) {
                return [
                    'enabled' => false,
                    'items' => [],
                ];
            }

            return [
                'enabled' => (bool) ($menuConfig['enabled'] ?? false),
                'items' => is_array($menuConfig['items'] ?? null) ? $menuConfig['items'] : [],
            ];
        });

        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;

            if (! is_array($aboutPage)) {
                return [];
            }

            if (is_array($aboutPage['settings'] ?? null)) {
                unset($aboutPage['settings']['team_card']);
            }

            return $aboutPage;
        });
    }
};
