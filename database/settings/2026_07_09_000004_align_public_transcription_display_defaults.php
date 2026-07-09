<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->alignDefaults('effective_plus_count', 'effective_only');
    }

    public function down(): void
    {
        $this->alignDefaults('effective_only', 'effective_plus_count');
    }

    private function alignDefaults(string $from, string $to): void
    {
        if ($this->migrator->exists('public_content.display_defaults')) {
            $this->migrator->update('public_content.display_defaults', function (mixed $displayDefaults) use ($from, $to): array {
                $displayDefaults = $this->arrayFrom($displayDefaults);

                if (($displayDefaults['transcription_display'] ?? null) === $from) {
                    $displayDefaults['transcription_display'] = $to;
                }

                $displayDefaults['transcription_display'] ??= $to;

                return $displayDefaults;
            });
        }

        if ($this->migrator->exists('public_content.podcasts_page')) {
            $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage) use ($from, $to): array {
                $podcastsPage = $this->arrayFrom($podcastsPage);
                $groupPage = $this->arrayFrom($podcastsPage['group_page'] ?? []);

                if (($groupPage['transcription_display'] ?? null) === $from) {
                    $groupPage['transcription_display'] = $to;
                }

                $groupPage['transcription_display'] ??= $to;
                $podcastsPage['group_page'] = $groupPage;

                return $podcastsPage;
            });
        }

        if (! $this->migrator->exists('public_content.contributors_page')) {
            return;
        }

        $this->migrator->update('public_content.contributors_page', function (mixed $contributorsPage) use ($from, $to): array {
            $contributorsPage = $this->arrayFrom($contributorsPage);

            foreach (['directory', 'top_transcribers', 'page'] as $section) {
                $sectionConfig = $this->arrayFrom($contributorsPage[$section] ?? []);

                if (($sectionConfig['transcription_display'] ?? null) === $from) {
                    $sectionConfig['transcription_display'] = $to;
                }

                $sectionConfig['transcription_display'] ??= $to;
                $contributorsPage[$section] = $sectionConfig;
            }

            return $contributorsPage;
        });
    }

    /**
     * @return array<mixed>
     */
    private function arrayFrom(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_object($value)) {
            return [];
        }

        $decoded = json_decode(json_encode($value), true);

        return is_array($decoded) ? $decoded : [];
    }
};
