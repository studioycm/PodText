<?php

namespace App\Support\Media;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use Illuminate\Support\Facades\Gate;

class MediaRecordProjector
{
    public function __construct(private readonly MediaInventoryDiagnostics $diagnostics) {}

    /**
     * @return array<string, bool|int|string|array<int, string>|null>
     */
    public function project(Media $media, bool $withOwnerDetails = false): array
    {
        $selectionBlockedReason = $this->diagnostics->selectionBlockedReason($media);

        $projection = [
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

        if ($withOwnerDetails) {
            $projection['details_url'] = Gate::allows('update', $media)
                ? MediaResource::getUrl('edit', ['record' => $media], panel: 'admin')
                : null;
        }

        return $projection;
    }
}
