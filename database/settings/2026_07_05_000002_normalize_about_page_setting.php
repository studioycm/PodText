<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;

            if (! is_array($aboutPage)) {
                $aboutPage = [];
            }

            return [
                'enabled' => (bool) ($aboutPage['enabled'] ?? false),
                'title' => $aboutPage['title'] ?? 'מי אנחנו',
                'kicker' => $aboutPage['kicker'] ?? null,
                'description' => $aboutPage['description'] ?? null,
                'blocks' => is_array($aboutPage['blocks'] ?? null) ? $aboutPage['blocks'] : [],
                'team_profiles' => is_array($aboutPage['team_profiles'] ?? null) ? $aboutPage['team_profiles'] : [],
                'settings' => [
                    'team_heading' => $aboutPage['settings']['team_heading'] ?? null,
                    'team_description' => $aboutPage['settings']['team_description'] ?? null,
                    'team_layout' => $aboutPage['settings']['team_layout'] ?? 'grid',
                ],
            ];
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;

            if (! is_array($aboutPage)) {
                return [
                    'enabled' => false,
                    'blocks' => [],
                    'team_profiles' => [],
                ];
            }

            return [
                'enabled' => (bool) ($aboutPage['enabled'] ?? false),
                'blocks' => is_array($aboutPage['blocks'] ?? null) ? $aboutPage['blocks'] : [],
                'team_profiles' => is_array($aboutPage['team_profiles'] ?? null) ? $aboutPage['team_profiles'] : [],
            ];
        });
    }
};
