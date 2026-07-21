<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\MediaAttachment;

/** Database-only list projection. It deliberately never accesses Storage. */
class LegacyOwnerMediaDiagnosticProjector
{
    public function __construct(private readonly LegacyOwnerMediaDiagnostics $diagnostics) {}

    public function hasUnsafe(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): bool
    {
        $relation = $owner instanceof ContentGroup ? 'legacyCoverMediaRows' : 'legacyPrimaryImageMediaRows';
        $rows = $owner->relationLoaded($relation) ? $owner->getRelation($relation) : collect();
        $attachmentRelation = $owner instanceof ContentGroup ? 'coverMediaAttachment' : 'primaryImageMediaAttachment';
        $attachment = $owner->relationLoaded($attachmentRelation) ? $owner->getRelation($attachmentRelation) : null;

        return $this->diagnostics->make(
            $owner,
            $role,
            $attachment instanceof MediaAttachment ? $attachment : null,
            $rows,
        ) !== null;
    }
}
