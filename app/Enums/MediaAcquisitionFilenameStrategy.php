<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaAcquisitionFilenameStrategy: string implements HasLabel
{
    case AppGenerated = 'app_generated';
    case CleanedOriginal = 'cleaned_original';

    public function getLabel(): string
    {
        return __("admin.media_acquisition_filename_strategies.{$this->value}");
    }
}
