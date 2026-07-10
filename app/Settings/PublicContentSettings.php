<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PublicContentSettings extends Settings
{
    public int $homepage_item_limit;

    public int $pinned_item_limit;

    public string $default_public_sort;

    public string $default_result_layout;

    public bool $show_latest_section;

    public string $item_page_layout;

    public string $homepage_card_image_size;

    public string $homepage_card_density;

    public string $homepage_card_title_size;

    public string $homepage_card_image_fit;

    public string $homepage_card_image_radius;

    public bool $homepage_show_group_badge;

    public string $homepage_group_badge_mode;

    public string $homepage_group_title_separator;

    public bool $homepage_group_badge_duplicate_thumbnail;

    public bool $homepage_show_authors;

    public bool $homepage_show_categories;

    public bool $homepage_show_tags;

    public bool $homepage_show_duration;

    public bool $homepage_show_effective_date;

    public bool $homepage_show_description;

    public int $homepage_description_lines;

    public int $homepage_cards_per_page;

    public array $card_templates;

    public array $menu_config;

    public array $about_page;

    public array $public_forms;

    public array $route_labels;

    public array $display_defaults;

    public array $default_images;

    public array $transcription_policy;

    public array $item_page;

    public array $podcasts_page;

    public array $contributors_page;

    public array $settings_backups;

    public array $import_locks;

    public array $maintenance;

    public static function group(): string
    {
        return 'public_content';
    }
}
