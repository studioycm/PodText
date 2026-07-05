<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.podcasts_page', [
            'enabled' => true,
            'title' => __('public.pages.podcasts.title'),
            'description' => __('public.pages.podcasts.description'),
            'group_label_singular' => __('public.labels.podcast'),
            'group_label_plural' => __('public.labels.podcasts'),
            'cards_per_page' => 12,
            'category_filter_enabled' => true,
            'search_enabled' => true,
            'template_key' => null,
            'item_template_key' => null,
            'show_description' => true,
            'show_categories' => true,
            'show_episode_count' => true,
            'group_page' => [
                'show_description' => true,
                'show_categories' => true,
                'show_episode_descriptions' => true,
                'items_per_page' => 12,
            ],
        ]);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.podcasts_page');
    }
};
