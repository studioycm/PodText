<?php

namespace App\Observers;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\MediaAttachment;

class ContentGroupObserver
{
    public function deleting(ContentGroup $contentGroup): void
    {
        MediaAttachment::query()
            ->where('attachable_type', 'content_group')
            ->where('attachable_id', $contentGroup->getKey())
            ->delete();

        $itemIds = ContentItem::query()
            ->where('content_group_id', $contentGroup->getKey())
            ->pluck('id');

        if ($itemIds->isNotEmpty()) {
            MediaAttachment::query()
                ->where('attachable_type', 'content_item')
                ->whereIn('attachable_id', $itemIds)
                ->delete();
        }
    }
}
