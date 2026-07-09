<?php

namespace App\Support\PublicFront;

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use Illuminate\Support\Facades\Storage;

class PublicDefaultImageResolver
{
    public function __construct(
        private readonly PublicFrontRenderContext $context,
    ) {}

    /**
     * @return array{url: string|null, source: string, path: string|null}
     */
    public function contentItemImage(ContentItem $item): array
    {
        if (filled($item->external_thumbnail_url)) {
            return [
                'url' => (string) $item->external_thumbnail_url,
                'source' => 'item',
                'path' => null,
            ];
        }

        if ($this->mode('content_item') !== 'none' && filled($item->contentGroup?->cover_path)) {
            return $this->publicDiskImage((string) $item->contentGroup->cover_path, 'group');
        }

        return $this->familyImage('content_item', 'content_item_default');
    }

    /**
     * @return array{url: string|null, source: string, path: string|null}
     */
    public function contentGroupImage(ContentGroup $group): array
    {
        if (filled($group->cover_path)) {
            return $this->publicDiskImage((string) $group->cover_path, 'group');
        }

        return $this->familyImage('content_group', 'content_group_default');
    }

    /**
     * @return array{url: string|null, source: string, path: string|null}
     */
    public function contributorImage(Author $author): array
    {
        return $this->familyImage('contributor', 'contributor_default');
    }

    /**
     * @return array{mode: string, path: string|null}
     */
    private function familyConfig(string $family): array
    {
        $defaults = PublicFrontConfigRegistry::defaults()['default_images'][$family] ?? [
            'mode' => 'inherit',
            'path' => null,
        ];
        $config = $this->context->defaultImages()[$family] ?? [];

        if (! is_array($config)) {
            $config = [];
        }

        return [
            'mode' => is_string($config['mode'] ?? null) ? $config['mode'] : $defaults['mode'],
            'path' => is_string($config['path'] ?? null) && filled($config['path']) ? $config['path'] : null,
        ];
    }

    private function mode(string $family): string
    {
        return $this->familyConfig($family)['mode'];
    }

    /**
     * @return array{url: string|null, source: string, path: string|null}
     */
    private function familyImage(string $family, string $source): array
    {
        $config = $this->familyConfig($family);

        if ($config['mode'] === 'custom' && filled($config['path'])) {
            return $this->publicDiskImage($config['path'], $source);
        }

        if ($config['mode'] === 'none') {
            return $this->emptyImage();
        }

        $global = $this->familyConfig('global');

        if ($global['mode'] === 'custom' && filled($global['path'])) {
            return $this->publicDiskImage($global['path'], 'global_default');
        }

        return $this->emptyImage();
    }

    /**
     * @return array{url: string|null, source: string, path: string|null}
     */
    private function publicDiskImage(string $path, string $source): array
    {
        return [
            'url' => Storage::disk('public')->url($path),
            'source' => $source,
            'path' => $path,
        ];
    }

    /**
     * @return array{url: null, source: string, path: null}
     */
    private function emptyImage(): array
    {
        return [
            'url' => null,
            'source' => 'fallback',
            'path' => null,
        ];
    }
}
