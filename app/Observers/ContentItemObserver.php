<?php

namespace App\Observers;

use App\Models\ContentItem;
use App\Models\MediaAttachment;

class ContentItemObserver
{
    public function deleting(ContentItem $contentItem): void
    {
        MediaAttachment::query()
            ->where('attachable_type', 'content_item')
            ->where('attachable_id', $contentItem->getKey())
            ->delete();
    }
}
