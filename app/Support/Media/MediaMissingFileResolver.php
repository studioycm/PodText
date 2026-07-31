<?php

namespace App\Support\Media;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The destructive half of the missing-file resolution: detach every owner
 * reference through the attachment manager, then delete the row through the
 * journaled coordinator. The non-destructive half is restore-by-upload, which
 * is the ordinary swap.
 */
class MediaMissingFileResolver
{
    public function __construct(
        private readonly MediaAttachmentManager $attachments,
        private readonly MediaFilesystemMutationCoordinator $coordinator,
    ) {}

    public function detachAndDelete(Media $media, User $actor): void
    {
        Gate::forUser($actor)->authorize('detachAndDelete', $media);

        $media->attachments()
            ->get()
            ->each(function (MediaAttachment $attachment) use ($actor): void {
                $owner = $attachment->attachable;

                if ($owner instanceof ContentGroup || $owner instanceof ContentItem) {
                    $this->attachments->detach($owner, $attachment->role, $actor);

                    return;
                }

                // A dangling owner row cannot be detached through the manager;
                // removing the broken reference directly is the truthful fix.
                $attachment->delete();
            });

        $this->coordinator->delete($media->refresh(), $actor);
    }
}
