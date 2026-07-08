<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.display_defaults', function (mixed $displayDefaults): array {
            $displayDefaults = is_object($displayDefaults) ? (array) $displayDefaults : $displayDefaults;
            $displayDefaults = is_array($displayDefaults) ? $displayDefaults : [];

            return [
                ...$displayDefaults,
                'transcription_display' => $displayDefaults['transcription_display'] ?? 'effective_plus_count',
            ];
        });

        $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage): array {
            $podcastsPage = is_object($podcastsPage) ? (array) $podcastsPage : $podcastsPage;
            $podcastsPage = is_array($podcastsPage) ? $podcastsPage : [];
            $groupPage = $podcastsPage['group_page'] ?? [];
            $groupPage = is_object($groupPage) ? (array) $groupPage : $groupPage;
            $groupPage = is_array($groupPage) ? $groupPage : [];

            $podcastsPage['group_page'] = [
                ...$groupPage,
                'transcription_display' => $groupPage['transcription_display'] ?? 'effective_plus_count',
            ];

            return $podcastsPage;
        });

        $this->migrator->update('public_content.contributors_page', function (mixed $contributorsPage): array {
            $contributorsPage = is_object($contributorsPage) ? (array) $contributorsPage : $contributorsPage;
            $contributorsPage = is_array($contributorsPage) ? $contributorsPage : [];

            foreach (['directory', 'top_transcribers', 'page'] as $section) {
                $sectionConfig = $contributorsPage[$section] ?? [];
                $sectionConfig = is_object($sectionConfig) ? (array) $sectionConfig : $sectionConfig;
                $sectionConfig = is_array($sectionConfig) ? $sectionConfig : [];

                $contributorsPage[$section] = [
                    ...$sectionConfig,
                    'transcription_display' => $sectionConfig['transcription_display'] ?? 'effective_plus_count',
                ];
            }

            return $contributorsPage;
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.display_defaults', function (mixed $displayDefaults): array {
            $displayDefaults = is_object($displayDefaults) ? (array) $displayDefaults : $displayDefaults;
            $displayDefaults = is_array($displayDefaults) ? $displayDefaults : [];

            unset($displayDefaults['transcription_display']);

            return $displayDefaults;
        });

        $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage): array {
            $podcastsPage = is_object($podcastsPage) ? (array) $podcastsPage : $podcastsPage;
            $podcastsPage = is_array($podcastsPage) ? $podcastsPage : [];

            if (is_array($podcastsPage['group_page'] ?? null)) {
                unset($podcastsPage['group_page']['transcription_display']);
            }

            return $podcastsPage;
        });

        $this->migrator->update('public_content.contributors_page', function (mixed $contributorsPage): array {
            $contributorsPage = is_object($contributorsPage) ? (array) $contributorsPage : $contributorsPage;
            $contributorsPage = is_array($contributorsPage) ? $contributorsPage : [];

            foreach (['directory', 'top_transcribers', 'page'] as $section) {
                if (is_array($contributorsPage[$section] ?? null)) {
                    unset($contributorsPage[$section]['transcription_display']);
                }
            }

            return $contributorsPage;
        });
    }
};
