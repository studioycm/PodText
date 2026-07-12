<?php

namespace App\Observers;

use App\Models\ContentItem;
use App\Support\Media\AppOwnedMediaFileCleaner;

class ContentItemObserver
{
    public function updated(ContentItem $contentItem): void
    {
        if (! $contentItem->wasChanged('image_path')) {
            return;
        }

        app(AppOwnedMediaFileCleaner::class)->deleteUnusedContentItemImage(
            $contentItem->getOriginal('image_path'),
            $contentItem,
        );
    }

    public function deleted(ContentItem $contentItem): void
    {
        app(AppOwnedMediaFileCleaner::class)->deleteUnusedContentItemImage(
            $contentItem->image_path,
            $contentItem,
        );
    }
}
