<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = $this->arrayValue($aboutPage);
            $settings = $this->arrayValue($aboutPage['settings'] ?? []);
            $teamCard = $this->arrayValue($settings['team_card'] ?? []);

            return [
                ...$aboutPage,
                'settings' => [
                    'team_heading' => $settings['team_heading'] ?? null,
                    'team_description' => $settings['team_description'] ?? null,
                    'team_layout' => $settings['team_layout'] ?? 'grid',
                    'team_card' => [
                        'show_image' => $teamCard['show_image'] ?? true,
                        'image_size' => $teamCard['image_size'] ?? 'medium',
                        'layout' => $teamCard['layout'] ?? ($settings['team_layout'] ?? 'grid'),
                        'density' => $teamCard['density'] ?? 'comfortable',
                        'show_title' => $teamCard['show_title'] ?? true,
                        'show_description' => $teamCard['show_description'] ?? true,
                        'description_lines' => $teamCard['description_lines'] ?? 3,
                    ],
                ],
            ];
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = $this->arrayValue($aboutPage);
            $settings = $this->arrayValue($aboutPage['settings'] ?? []);

            unset($settings['team_card']);

            return [
                ...$aboutPage,
                'settings' => $settings,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        return is_array($value) ? $value : [];
    }
};
