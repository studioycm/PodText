<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.contributors_page', [
            'enabled' => true,
            'title' => __('public.pages.contributors.title'),
            'description' => __('public.pages.contributors.description'),
            'label_singular' => __('public.labels.contributor'),
            'label_plural' => __('public.labels.contributors'),
            'item_label_singular' => __('public.labels.item'),
            'item_label_plural' => __('public.labels.items'),
            'directory' => [
                'per_page_options' => [10, 15, 20],
                'default_per_page' => 10,
                'default_sort' => 'count_desc',
                'sort_options' => ['name_asc', 'name_desc', 'count_desc', 'count_asc'],
                'preview_items_per_page' => 6,
                'preview_grid_columns' => 3,
                'preview_search_enabled' => true,
            ],
            'top_transcribers' => [
                'enabled' => true,
                'limit' => 8,
                'layout' => 'horizontal',
                'preview_default_page_size' => 5,
                'preview_page_size_options' => [5, 10, 15],
                'preview_grid_columns' => 3,
                'show_full_page_link' => true,
                'show_count_badge' => true,
            ],
            'cards' => [
                'compact_show_count' => true,
                'compact_count_icon' => 'document-text',
                'preview_show_bio' => true,
                'preview_show_counts' => true,
            ],
            'page' => [
                'items_per_page' => 12,
                'page_size_options' => [6, 12, 24],
                'default_sort' => 'latest_transcription',
                'sort_options' => ['latest_transcription', 'oldest_transcription', 'title_asc', 'title_desc'],
                'search_enabled' => true,
                'grid_columns' => 3,
                'grid_gap' => 'comfortable',
            ],
        ]);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.contributors_page');
    }
};
