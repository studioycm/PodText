<?php

namespace App\Support\Media;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;

final class MediaDetailsViewModel
{
    /** @return array<string, mixed> */
    public static function make(Media $media): array
    {
        return [
            'projection' => app(MediaRecordProjector::class)->project($media, withOwnerDetails: true),
            'mime' => $media->type,
            'directory' => (string) $media->directory,
            'storedFilename' => $media->name,
            'references' => app(MediaReferenceFinder::class)->linkedReferencesForMedia($media),
            'issueReviewUrl' => app(MediaInventoryDiagnostics::class)->needsRepair($media)
                ? MediaResource::getUrl('review-issues', ['record' => $media], panel: 'admin')
                : null,
        ];
    }
}
