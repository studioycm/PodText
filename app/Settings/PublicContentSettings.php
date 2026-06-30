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

    public static function group(): string
    {
        return 'public_content';
    }
}
