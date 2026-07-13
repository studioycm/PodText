<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AdminUxSettings extends Settings
{
    public string $media_naming_strategy;

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
