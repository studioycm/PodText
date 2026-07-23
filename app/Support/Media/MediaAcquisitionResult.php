<?php

namespace App\Support\Media;

use App\Enums\MediaAcquisitionDisposition;
use App\Models\Media;

readonly class MediaAcquisitionResult
{
    public function __construct(
        public Media $media,
        public MediaAcquisitionDisposition $disposition,
    ) {}
}
