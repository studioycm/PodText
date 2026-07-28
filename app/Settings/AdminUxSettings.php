<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AdminUxSettings extends Settings
{
    public string $media_naming_strategy;

    public int $media_acquisition_max_kilobytes = 2048;

    public int $media_acquisition_max_dimension = 3000;

    public int $media_acquisition_upload_batch_limit = 10;

    public int $media_upload_queue_limit = 40;

    public int $media_picker_browse_limit = 25;

    public int $media_picker_search_limit = 50;

    public string $media_acquisition_filename_strategy = 'app_generated';

    public string $transcription_presentation_mode = 'collapsible';

    public string $transcription_mode = 'single';

    public bool $show_episode_workspace_hint_line;

    public bool $show_episode_workspace_language_code;

    public string $tb1_picker_container;

    public static function group(): string
    {
        return 'admin_ux';
    }
}
