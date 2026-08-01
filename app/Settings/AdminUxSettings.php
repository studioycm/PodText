<?php

namespace App\Settings;

use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Enums\MediaNamingStrategy;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use Spatie\LaravelSettings\Settings;

class AdminUxSettings extends Settings
{
    public MediaNamingStrategy $media_naming_strategy = MediaNamingStrategy::Slug;

    public int $media_acquisition_max_kilobytes = 2048;

    public int $media_acquisition_max_dimension = 3000;

    public int $media_acquisition_upload_batch_limit = 10;

    public int $media_upload_queue_limit = 40;

    public int $media_heavy_upload_warning_kilobytes = 2048;

    public int $media_picker_browse_limit = 25;

    public int $media_picker_search_limit = 50;

    public MediaAcquisitionFilenameStrategy $media_acquisition_filename_strategy = MediaAcquisitionFilenameStrategy::AppGenerated;

    public TranscriptionPresentationMode $transcription_presentation_mode = TranscriptionPresentationMode::Collapsible;

    public TranscriptionMode $transcription_mode = TranscriptionMode::Single;

    public bool $show_episode_workspace_hint_line = true;

    public bool $show_episode_workspace_language_code = false;

    public static function group(): string
    {
        return 'admin_ux';
    }
}
