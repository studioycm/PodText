<?php

namespace App\Support\Media;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppOwnedMediaFileCleaner
{
    public function __construct(
        private readonly MediaReferenceFinder $references,
    ) {}

    public function deleteUnusedContentGroupCover(?string $path, ?ContentGroup $excluding = null): bool
    {
        return $this->deleteUnusedAppOwnedFile(
            $path,
            ImageFileNamer::directoryFor(ImageFileNamer::CONTENT_GROUP_COVER),
            excludingGroup: $excluding,
        );
    }

    public function deleteUnusedContentItemImage(?string $path, ?ContentItem $excluding = null): bool
    {
        return $this->deleteUnusedAppOwnedFile(
            $path,
            ImageFileNamer::directoryFor(ImageFileNamer::CONTENT_ITEM_IMAGE),
            excludingItem: $excluding,
        );
    }

    public function isAppOwnedContentGroupCover(?string $path): bool
    {
        $path = $this->normalize($path);

        if ($path === null) {
            return false;
        }

        return Str::startsWith($path, ImageFileNamer::directoryFor(ImageFileNamer::CONTENT_GROUP_COVER).'/');
    }

    public function isAppOwnedContentItemImage(?string $path): bool
    {
        $path = $this->normalize($path);

        if ($path === null) {
            return false;
        }

        return Str::startsWith($path, ImageFileNamer::directoryFor(ImageFileNamer::CONTENT_ITEM_IMAGE).'/');
    }

    private function deleteUnusedAppOwnedFile(
        ?string $path,
        string $directory,
        ?ContentGroup $excludingGroup = null,
        ?ContentItem $excludingItem = null,
    ): bool {
        $path = $this->normalize($path);

        if ($path === null || ! Str::startsWith($path, "{$directory}/")) {
            return false;
        }

        if ($this->references->hasCuratorMediaRow($path)) {
            return false;
        }

        if ($this->references->referencesForPath($path, $excludingGroup, $excludingItem) !== []) {
            return false;
        }

        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
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
