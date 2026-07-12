<?php

namespace App\Support\Media;

use App\Models\ContentGroup;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppOwnedMediaFileCleaner
{
    public function deleteUnusedContentGroupCover(?string $path, ?ContentGroup $excluding = null): bool
    {
        $path = $this->normalize($path);

        if ($path === null || ! $this->isAppOwnedContentGroupCover($path)) {
            return false;
        }

        if ($this->isReferencedByAnotherGroup($path, $excluding)) {
            return false;
        }

        if (Schema::hasTable('curator')) {
            $media = Media::query()
                ->where('disk', 'public')
                ->where('path', $path)
                ->first();

            if ($media instanceof Media) {
                return (bool) $media->delete();
            }
        }

        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    public function isAppOwnedContentGroupCover(?string $path): bool
    {
        $path = $this->normalize($path);

        if ($path === null) {
            return false;
        }

        return Str::startsWith($path, ImageFileNamer::directoryFor(ImageFileNamer::CONTENT_GROUP_COVER).'/');
    }

    private function isReferencedByAnotherGroup(string $path, ?ContentGroup $excluding): bool
    {
        return ContentGroup::query()
            ->where('cover_path', $path)
            ->when($excluding?->exists, fn ($query): mixed => $query->whereKeyNot($excluding->getKey()))
            ->exists();
    }

    private function normalize(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim((string) $path));
        $path = preg_replace('#/+#', '/', $path) ?: '';

        if (str_contains($path, '../') || str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }
}
