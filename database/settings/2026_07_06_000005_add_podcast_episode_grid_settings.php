<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage): array {
            $podcastsPage = is_object($podcastsPage) ? (array) $podcastsPage : $podcastsPage;
            $podcastsPage = is_array($podcastsPage) ? $podcastsPage : [];
            $groupPage = $podcastsPage['group_page'] ?? [];
            $groupPage = is_object($groupPage) ? (array) $groupPage : $groupPage;
            $groupPage = is_array($groupPage) ? $groupPage : [];

            $podcastsPage['group_page'] = [
                ...$groupPage,
                'show_description' => $groupPage['show_description'] ?? true,
                'show_categories' => $groupPage['show_categories'] ?? true,
                'show_episode_descriptions' => $groupPage['show_episode_descriptions'] ?? true,
                'items_layout' => $groupPage['items_layout'] ?? 'cards',
                'items_grid_columns' => $groupPage['items_grid_columns'] ?? 3,
                'items_grid_gap' => $groupPage['items_grid_gap'] ?? 'comfortable',
                'items_per_page' => $groupPage['items_per_page'] ?? 12,
                'page_size_options' => $groupPage['page_size_options'] ?? [6, 12, 24, 48],
                'per_page_selector_enabled' => $groupPage['per_page_selector_enabled'] ?? true,
                'search_enabled' => $groupPage['search_enabled'] ?? true,
                'sort_enabled' => $groupPage['sort_enabled'] ?? true,
                'category_filter_enabled' => $groupPage['category_filter_enabled'] ?? true,
                'default_sort' => $groupPage['default_sort'] ?? 'latest_transcription',
                'sort_options' => $groupPage['sort_options'] ?? [
                    'latest_transcription',
                    'oldest_transcription',
                    'title_asc',
                    'title_desc',
                    'original_newest',
                    'original_oldest',
                    'duration_longest',
                    'duration_shortest',
                ],
                'item_density' => $groupPage['item_density'] ?? 'comfortable',
                'item_image_size' => $groupPage['item_image_size'] ?? 'medium',
                'item_image_fit' => $groupPage['item_image_fit'] ?? 'cover',
                'item_image_radius' => $groupPage['item_image_radius'] ?? 'mid_rounded',
                'item_title_size' => $groupPage['item_title_size'] ?? 'base',
                'show_episode_authors' => $groupPage['show_episode_authors'] ?? true,
                'show_episode_tags' => $groupPage['show_episode_tags'] ?? true,
                'show_episode_duration' => $groupPage['show_episode_duration'] ?? true,
                'show_episode_effective_date' => $groupPage['show_episode_effective_date'] ?? true,
            ];

            return $podcastsPage;
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.podcasts_page', function (mixed $podcastsPage): array {
            $podcastsPage = is_object($podcastsPage) ? (array) $podcastsPage : $podcastsPage;
            $podcastsPage = is_array($podcastsPage) ? $podcastsPage : [];

            if (is_array($podcastsPage['group_page'] ?? null)) {
                unset(
                    $podcastsPage['group_page']['items_layout'],
                    $podcastsPage['group_page']['items_grid_columns'],
                    $podcastsPage['group_page']['items_grid_gap'],
                    $podcastsPage['group_page']['page_size_options'],
                    $podcastsPage['group_page']['per_page_selector_enabled'],
                    $podcastsPage['group_page']['search_enabled'],
                    $podcastsPage['group_page']['sort_enabled'],
                    $podcastsPage['group_page']['category_filter_enabled'],
                    $podcastsPage['group_page']['default_sort'],
                    $podcastsPage['group_page']['sort_options'],
                    $podcastsPage['group_page']['item_density'],
                    $podcastsPage['group_page']['item_image_size'],
                    $podcastsPage['group_page']['item_image_fit'],
                    $podcastsPage['group_page']['item_image_radius'],
                    $podcastsPage['group_page']['item_title_size'],
                    $podcastsPage['group_page']['show_episode_authors'],
                    $podcastsPage['group_page']['show_episode_tags'],
                    $podcastsPage['group_page']['show_episode_duration'],
                    $podcastsPage['group_page']['show_episode_effective_date'],
                );
            }

            return $podcastsPage;
        });
    }
};
