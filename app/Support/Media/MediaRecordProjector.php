<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaRecordProjector
{
    /**
     * @return array<string, int|string|null>
     */
    public function project(Media $media): array
    {
        return [
            'id' => (int) $media->getKey(),
            'reference_key' => $media->getAttribute('reference_key'),
            'pretty_name' => $media->title ?: $media->name,
            'alt' => $media->alt,
            'ext' => $media->ext,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'preview_url' => Storage::disk('public')->url($media->path),
        ];
    }
}
