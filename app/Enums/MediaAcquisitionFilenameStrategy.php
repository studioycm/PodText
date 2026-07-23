<?php

namespace App\Enums;

enum MediaAcquisitionFilenameStrategy: string
{
    case AppGenerated = 'app_generated';
    case CleanedOriginal = 'cleaned_original';
}
