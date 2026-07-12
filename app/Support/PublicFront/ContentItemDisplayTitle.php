<?php

namespace App\Support\PublicFront;

use App\Models\ContentItem;

class ContentItemDisplayTitle
{
    public function prefix(ContentItem $item): ?string
    {
        if (filled($item->title_prefix)) {
            return trim((string) $item->title_prefix);
        }

        $contentGroup = $item->relationLoaded('contentGroup')
            ? $item->contentGroup
            : $item->contentGroup()->first();

        return filled($contentGroup?->title) ? (string) $contentGroup->title : null;
    }

    public function combined(ContentItem $item, string $separator = ' - '): string
    {
        $prefix = $this->prefix($item);
        $title = (string) $item->title;

        if (blank($prefix)) {
            return $title;
        }

        return $prefix.$separator.$title;
    }
}
