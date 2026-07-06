<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;
            $aboutPage = is_array($aboutPage) ? $aboutPage : [];
            $settings = is_array($aboutPage['settings'] ?? null) ? $aboutPage['settings'] : [];
            $teamCard = is_array($settings['team_card'] ?? null) ? $settings['team_card'] : [];

            $settings = [
                'team_heading' => $settings['team_heading'] ?? null,
                'team_description' => $settings['team_description'] ?? null,
                'team_layout' => $settings['team_layout'] ?? 'grid',
                ...$settings,
                'team_card' => [
                    'show_image' => $teamCard['show_image'] ?? true,
                    'image_size' => $teamCard['image_size'] ?? 'medium',
                    'image_fit' => $teamCard['image_fit'] ?? 'cover',
                    'image_radius' => $teamCard['image_radius'] ?? 'circle',
                    'layout' => $teamCard['layout'] ?? ($settings['team_layout'] ?? 'grid'),
                    'density' => $teamCard['density'] ?? 'comfortable',
                    'show_title' => $teamCard['show_title'] ?? true,
                    'show_description' => $teamCard['show_description'] ?? true,
                    'description_lines' => $teamCard['description_lines'] ?? 3,
                ],
            ];

            return [
                ...$aboutPage,
                'settings' => $settings,
            ];
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;
            $aboutPage = is_array($aboutPage) ? $aboutPage : [];

            if (is_array($aboutPage['settings']['team_card'] ?? null)) {
                unset(
                    $aboutPage['settings']['team_card']['image_fit'],
                    $aboutPage['settings']['team_card']['image_radius'],
                );
            }

            return $aboutPage;
        });
    }
};
