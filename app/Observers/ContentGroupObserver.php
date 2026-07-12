<?php

namespace App\Observers;

use App\Models\ContentGroup;
use App\Support\Media\AppOwnedMediaFileCleaner;

class ContentGroupObserver
{
    public function updated(ContentGroup $contentGroup): void
    {
        if (! $contentGroup->wasChanged('cover_path')) {
            return;
        }

        app(AppOwnedMediaFileCleaner::class)->deleteUnusedContentGroupCover(
            $contentGroup->getOriginal('cover_path'),
            $contentGroup,
        );
    }

    public function deleted(ContentGroup $contentGroup): void
    {
        app(AppOwnedMediaFileCleaner::class)->deleteUnusedContentGroupCover(
            $contentGroup->cover_path,
            $contentGroup,
        );
    }
}
