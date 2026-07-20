<?php

namespace App\Support\Media;

use App\Support\PublicFront\PublicFrontConfigCache;
use Awcodes\Curator\Facades\Glide;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MediaCacheInvalidator
{
    public function invalidate(
        string $disk,
        string $path,
        ?string $name = null,
        ?int $lastModified = null,
    ): void {
        if ($disk !== 'public') {
            return;
        }

        if ($lastModified === null) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    $lastModified = Storage::disk($disk)->lastModified($path);
                }
            } catch (Throwable) {
                $lastModified = null;
            }
        }

        if (! Glide::getServer()->deleteCache($path)) {
            throw new RuntimeException('The Curator Glide cache could not be invalidated.');
        }

        if ($lastModified === null) {
            return;
        }

        Cache::forget('placeholder:'.($name ?: pathinfo($path, PATHINFO_FILENAME)).$lastModified);
        Cache::forget(app(PublicFrontConfigCache::class)->podcastPaletteKey($path, $lastModified));
    }
}
