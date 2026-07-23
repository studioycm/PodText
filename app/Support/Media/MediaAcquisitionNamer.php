<?php

namespace App\Support\Media;

use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Settings\AdminUxSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAcquisitionNamer
{
    public function __construct(
        private readonly AdminUxSettings $settings,
    ) {}

    public function destination(ValidatedImage $image): string
    {
        $key = (string) Str::ulid();
        $strategy = MediaAcquisitionFilenameStrategy::tryFrom(
            $this->settings->media_acquisition_filename_strategy,
        ) ?? MediaAcquisitionFilenameStrategy::AppGenerated;

        $name = match ($strategy) {
            MediaAcquisitionFilenameStrategy::AppGenerated => $key,
            MediaAcquisitionFilenameStrategy::CleanedOriginal => $this->cleanedStem($image).'-'.$key,
        };

        $path = "{$image->purpose->root()}/{$name}.{$image->extension}";

        if (Storage::disk('public')->exists($path)) {
            return $this->destination($image);
        }

        return $path;
    }

    private function cleanedStem(ValidatedImage $image): string
    {
        $stem = pathinfo((string) $image->originalFilename, PATHINFO_FILENAME);
        $slug = Str::slug($stem);

        return $slug !== '' ? Str::limit($slug, 100, '') : 'image';
    }
}
