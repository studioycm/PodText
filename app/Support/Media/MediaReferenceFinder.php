<?php

namespace App\Support\Media;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Settings\PublicContentSettings;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MediaReferenceFinder
{
    /**
     * @return array<int, string>
     */
    public function referencesForMedia(Media $media): array
    {
        if ($media->disk !== 'public') {
            return [];
        }

        return $this->referencesForPath((string) $media->path);
    }

    /**
     * @return array<int, string>
     */
    public function referencesForPath(
        ?string $path,
        ?ContentGroup $excludingGroup = null,
        ?ContentItem $excludingItem = null,
    ): array {
        $path = $this->normalize($path);

        if ($path === null) {
            return [];
        }

        return collect()
            ->merge($this->contentGroupReferences($path, $excludingGroup))
            ->merge($this->contentItemReferences($path, $excludingItem))
            ->merge($this->settingsReferences($path))
            ->unique()
            ->values()
            ->all();
    }

    public function hasCuratorMediaRow(?string $path): bool
    {
        $path = $this->normalize($path);

        if ($path === null || ! Schema::hasTable('curator')) {
            return false;
        }

        return Media::query()
            ->where('disk', 'public')
            ->where('path', $path)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function contentGroupReferences(string $path, ?ContentGroup $excludingGroup): array
    {
        if (! Schema::hasTable('content_groups')) {
            return [];
        }

        return ContentGroup::query()
            ->where('cover_path', $path)
            ->when($excludingGroup?->getKey(), fn ($query): mixed => $query->whereKeyNot($excludingGroup->getKey()))
            ->pluck('title')
            ->map(fn (string $title): string => __('admin.media_references.content_group_cover', ['title' => $title]))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function contentItemReferences(string $path, ?ContentItem $excludingItem): array
    {
        if (! Schema::hasTable('content_items') || ! Schema::hasColumn('content_items', 'image_path')) {
            return [];
        }

        return ContentItem::query()
            ->where('image_path', $path)
            ->when($excludingItem?->getKey(), fn ($query): mixed => $query->whereKeyNot($excludingItem->getKey()))
            ->pluck('title')
            ->map(fn (string $title): string => __('admin.media_references.content_item_image', ['title' => $title]))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function settingsReferences(string $path): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $settings = DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
            ->pluck('payload', 'name');

        return $settings
            ->flatMap(fn (mixed $payload, string $name): array => match ($name) {
                'menu_config' => $this->menuConfigReferences($path, $this->decodePayload($payload)),
                'about_page' => $this->aboutPageReferences($path, $this->decodePayload($payload)),
                'default_images' => $this->defaultImageReferences($path, $this->decodePayload($payload)),
                default => [],
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $menuConfig
     * @return array<int, string>
     */
    private function menuConfigReferences(string $path, array $menuConfig): array
    {
        return collect([
            'light_path' => data_get($menuConfig, 'logo.light_path'),
            'dark_path' => data_get($menuConfig, 'logo.dark_path'),
        ])
            ->filter(fn (mixed $value): bool => $this->normalize(is_string($value) ? $value : null) === $path)
            ->keys()
            ->map(fn (string $key): string => __("admin.media_references.menu_logo_{$key}"))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $aboutPage
     * @return array<int, string>
     */
    private function aboutPageReferences(string $path, array $aboutPage): array
    {
        $references = [];

        foreach (($aboutPage['blocks'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockPath = $block['image_path'] ?? data_get($block, 'data.image_path');

            if ($this->normalize(is_string($blockPath) ? $blockPath : null) === $path) {
                $references[] = __('admin.media_references.about_page_image');
            }
        }

        foreach (($aboutPage['team_profiles'] ?? []) as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $profilePath = $profile['image_path'] ?? null;

            if ($this->normalize(is_string($profilePath) ? $profilePath : null) === $path) {
                $name = is_string($profile['name'] ?? null) && filled($profile['name'])
                    ? $profile['name']
                    : __('admin.labels.untitled');

                $references[] = __('admin.media_references.team_profile_image', ['name' => $name]);
            }
        }

        return $references;
    }

    /**
     * @param  array<string, mixed>  $defaultImages
     * @return array<int, string>
     */
    private function defaultImageReferences(string $path, array $defaultImages): array
    {
        $references = [];

        foreach ($defaultImages as $family => $config) {
            if (! is_array($config)) {
                continue;
            }

            $imagePath = $config['path'] ?? null;

            if ($this->normalize(is_string($imagePath) ? $imagePath : null) !== $path) {
                continue;
            }

            $references[] = __('admin.media_references.default_image', [
                'family' => __("admin.default_image_families.{$family}"),
            ]);
        }

        return $references;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || blank($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
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
