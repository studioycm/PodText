<?php

namespace App\Support\Media;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;

class MediaRecordProjector
{
    public function __construct(private readonly MediaInventoryDiagnostics $diagnostics) {}

    /**
     * @return array<string, bool|int|string|array<int, string>|null>
     */
    public function project(Media $media): array
    {
        $selectionBlockedReason = $this->diagnostics->selectionBlockedReason($media);

        return [
            'id' => (int) $media->getKey(),
            'reference_key' => $media->getAttribute('reference_key'),
            'pretty_name' => $media->title ?: $media->name,
            'alt' => $media->alt,
            'ext' => $media->ext,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'preview_url' => $this->diagnostics->previewUrl($media),
            'needs_repair' => $this->diagnostics->needsRepair($media),
            'repair_reasons' => $this->diagnostics->reasons($media),
            'selectable' => $selectionBlockedReason === null,
            'selection_blocked_reason' => $selectionBlockedReason,
            'review_url' => MediaResource::getUrl('edit', ['record' => $media], panel: 'admin'),
        ];
    }
}
