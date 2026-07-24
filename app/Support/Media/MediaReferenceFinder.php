<?php

namespace App\Support\Media;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Settings\PublicContentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MediaReferenceFinder
{
    /** @var array<int, array<int, string>>|null */
    private ?array $primedReferences = null;

    /** @var array<int, array<int, string>>|null */
    private ?array $primedLegacyReferences = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $settingsPayloadCache = null;

    /**
     * @return array{paths: array<int, string>, reference_keys: array<int, string>}
     */
    public function settingsIdentityCandidates(): array
    {
        $paths = [];
        $referenceKeys = [];

        foreach ($this->settingsPayloads() as $name => $payload) {
            $identities = match ($name) {
                'menu_config' => [
                    [data_get($payload, 'logo.light_path'), data_get($payload, 'logo.light_media_reference_key')],
                    [data_get($payload, 'logo.dark_path'), data_get($payload, 'logo.dark_media_reference_key')],
                ],
                'about_page' => collect($payload['blocks'] ?? [])
                    ->filter(fn (mixed $block): bool => is_array($block))
                    ->map(fn (array $block): array => [
                        $block['image_path'] ?? data_get($block, 'data.image_path'),
                        $block['image_media_reference_key'] ?? data_get($block, 'data.image_media_reference_key'),
                    ])
                    ->merge(collect($payload['team_profiles'] ?? [])
                        ->filter(fn (mixed $profile): bool => is_array($profile))
                        ->map(fn (array $profile): array => [
                            $profile['image_path'] ?? null,
                            $profile['image_media_reference_key'] ?? null,
                        ]))
                    ->all(),
                'default_images' => collect($payload)
                    ->filter(fn (mixed $config): bool => is_array($config))
                    ->map(fn (array $config): array => [
                        $config['path'] ?? null,
                        $config['media_reference_key'] ?? null,
                    ])
                    ->all(),
                default => [],
            };

            foreach ($identities as $identity) {
                $path = $this->normalize(is_string($identity[0] ?? null) ? $identity[0] : null);
                $referenceKey = is_string($identity[1] ?? null)
                    ? mb_strtolower(trim($identity[1]))
                    : null;

                if ($path !== null) {
                    $paths[] = $path;
                }

                if (filled($referenceKey)) {
                    $referenceKeys[] = $referenceKey;
                }
            }
        }

        return [
            'paths' => array_values(array_unique($paths)),
            'reference_keys' => array_values(array_unique($referenceKeys)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function referencesForMedia(Media $media): array
    {
        if ($media->disk !== 'public') {
            return [];
        }

        if (is_array($this->primedReferences) && array_key_exists((int) $media->getKey(), $this->primedReferences)) {
            return $this->primedReferences[(int) $media->getKey()];
        }

        $attachmentReferences = Schema::hasTable('media_attachments')
            ? DB::table('media_attachments')
                ->where('media_id', $media->getKey())
                ->get(['attachable_type', 'attachable_id', 'role'])
                ->map(fn (object $attachment): string => __('admin.media_references.attachment', [
                    'type' => (string) $attachment->attachable_type,
                    'id' => (int) $attachment->attachable_id,
                    'role' => (string) $attachment->role,
                ]))
            : collect();

        return collect($this->referencesForPath((string) $media->path))
            ->merge($attachmentReferences)
            ->merge($this->settingsReferenceKeyReferences($media->reference_key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function legacyReferencesForMedia(Media $media): array
    {
        if ($media->disk !== 'public') {
            return [];
        }

        if (is_array($this->primedLegacyReferences) && array_key_exists((int) $media->getKey(), $this->primedLegacyReferences)) {
            return $this->primedLegacyReferences[(int) $media->getKey()];
        }

        return $this->referencesForPath((string) $media->path);
    }

    /**
     * @param  iterable<int, Media>  $media
     */
    public function prime(iterable $media): void
    {
        $records = collect($media)
            ->filter(fn (mixed $record): bool => $record instanceof Media && $record->disk === 'public')
            ->keyBy(fn (Media $record): int => (int) $record->getKey());
        $references = $records->map(fn (): array => [])->all();
        $legacyReferences = $records->map(fn (): array => [])->all();
        $recordsByPath = $records->groupBy(fn (Media $record): string => (string) $record->path);
        $ids = $records->keys()->all();
        $paths = $records->pluck('path')->filter()->unique()->values()->all();

        if ($ids !== [] && Schema::hasTable('media_attachments')) {
            DB::table('media_attachments')
                ->whereIn('media_id', $ids)
                ->get(['media_id', 'attachable_type', 'attachable_id', 'role'])
                ->each(function (object $attachment) use (&$references): void {
                    $references[(int) $attachment->media_id][] = __('admin.media_references.attachment', [
                        'type' => (string) $attachment->attachable_type,
                        'id' => (int) $attachment->attachable_id,
                        'role' => (string) $attachment->role,
                    ]);
                });
        }

        if ($paths !== [] && Schema::hasTable('content_groups')) {
            ContentGroup::query()
                ->whereIn('cover_path', $paths)
                ->get(['title', 'cover_path'])
                ->each(function (ContentGroup $group) use ($recordsByPath, &$legacyReferences, &$references): void {
                    $recordsByPath->get((string) $group->cover_path, collect())->each(function (Media $media) use ($group, &$legacyReferences, &$references): void {
                        $reference = __('admin.media_references.content_group_cover', ['title' => $group->title]);
                        $references[(int) $media->getKey()][] = $reference;
                        $legacyReferences[(int) $media->getKey()][] = $reference;
                    });
                });
        }

        if ($paths !== [] && Schema::hasTable('content_items') && Schema::hasColumn('content_items', 'image_path')) {
            ContentItem::query()
                ->whereIn('image_path', $paths)
                ->get(['title', 'image_path'])
                ->each(function (ContentItem $item) use ($recordsByPath, &$legacyReferences, &$references): void {
                    $recordsByPath->get((string) $item->image_path, collect())->each(function (Media $media) use ($item, &$legacyReferences, &$references): void {
                        $reference = __('admin.media_references.content_item_image', ['title' => $item->title]);
                        $references[(int) $media->getKey()][] = $reference;
                        $legacyReferences[(int) $media->getKey()][] = $reference;
                    });
                });
        }

        $settings = $this->settingsPayloads();
        $records->each(function (Media $media) use ($settings, &$legacyReferences, &$references): void {
            $references[(int) $media->getKey()] = collect($references[(int) $media->getKey()])
                ->merge($this->settingsIdentityReferences((string) $media->path, $media->reference_key, $settings))
                ->unique()
                ->values()
                ->all();
            $legacyReferences[(int) $media->getKey()] = collect($legacyReferences[(int) $media->getKey()])
                ->merge($this->settingsIdentityReferences((string) $media->path, null, $settings))
                ->unique()
                ->values()
                ->all();
        });

        $this->primedReferences = $references;
        $this->primedLegacyReferences = $legacyReferences;
    }

    public function clearPrime(): void
    {
        $this->primedReferences = null;
        $this->primedLegacyReferences = null;
    }

    public function forgetSettingsPayloads(): void
    {
        $this->settingsPayloadCache = null;
        $this->clearPrime();
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
        return $this->settingsIdentityReferences($path, null, $this->settingsPayloads());
    }

    /** @return array<int, string> */
    private function settingsReferenceKeyReferences(?string $referenceKey): array
    {
        if (blank($referenceKey)) {
            return [];
        }

        return $this->settingsIdentityReferences(null, $referenceKey, $this->settingsPayloads());
    }

    /**
     * @param  array<string, array<string, mixed>>  $settings
     * @return array<int, string>
     */
    private function settingsIdentityReferences(?string $path, ?string $referenceKey, array $settings): array
    {
        return collect($settings)
            ->flatMap(fn (array $payload, string $name): array => match ($name) {
                'menu_config' => $this->menuConfigIdentityReferences($path, $referenceKey, $payload),
                'about_page' => $this->aboutPageIdentityReferences($path, $referenceKey, $payload),
                'default_images' => $this->defaultImageIdentityReferences($path, $referenceKey, $payload),
                default => [],
            })
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function settingsPayloads(): array
    {
        if (is_array($this->settingsPayloadCache)) {
            return $this->settingsPayloadCache;
        }

        if (! Schema::hasTable('settings')) {
            return $this->settingsPayloadCache = [];
        }

        return $this->settingsPayloadCache = DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
            ->pluck('payload', 'name')
            ->map(fn (mixed $payload): array => $this->decodePayload($payload))
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

    /** @return array<int, string> */
    private function menuConfigIdentityReferences(?string $path, ?string $referenceKey, array $menuConfig): array
    {
        return collect([
            'light_path' => [data_get($menuConfig, 'logo.light_path'), data_get($menuConfig, 'logo.light_media_reference_key')],
            'dark_path' => [data_get($menuConfig, 'logo.dark_path'), data_get($menuConfig, 'logo.dark_media_reference_key')],
        ])
            ->filter(fn (array $identity): bool => $this->identityMatches($identity[0] ?? null, $identity[1] ?? null, $path, $referenceKey))
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

    /** @return array<int, string> */
    private function aboutPageIdentityReferences(?string $path, ?string $referenceKey, array $aboutPage): array
    {
        $references = [];

        foreach (($aboutPage['blocks'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }

            if ($this->identityMatches(
                $block['image_path'] ?? data_get($block, 'data.image_path'),
                $block['image_media_reference_key'] ?? data_get($block, 'data.image_media_reference_key'),
                $path,
                $referenceKey,
            )) {
                $references[] = __('admin.media_references.about_page_image');
            }
        }

        foreach (($aboutPage['team_profiles'] ?? []) as $profile) {
            if (! is_array($profile) || ! $this->identityMatches(
                $profile['image_path'] ?? null,
                $profile['image_media_reference_key'] ?? null,
                $path,
                $referenceKey,
            )) {
                continue;
            }

            $references[] = __('admin.media_references.team_profile_image', [
                'name' => filled($profile['name'] ?? null) ? $profile['name'] : __('admin.labels.untitled'),
            ]);
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

    /** @return array<int, string> */
    private function defaultImageIdentityReferences(?string $path, ?string $referenceKey, array $defaultImages): array
    {
        $references = [];

        foreach ($defaultImages as $family => $config) {
            if (! is_array($config) || ! $this->identityMatches(
                $config['path'] ?? null,
                $config['media_reference_key'] ?? null,
                $path,
                $referenceKey,
            )) {
                continue;
            }

            $references[] = __('admin.media_references.default_image', [
                'family' => __("admin.default_image_families.{$family}"),
            ]);
        }

        return $references;
    }

    private function identityMatches(mixed $storedPath, mixed $storedReferenceKey, ?string $path, ?string $referenceKey): bool
    {
        $matchesPath = filled($path)
            && $this->normalize(is_string($storedPath) ? $storedPath : null) === $path;
        $matchesReferenceKey = filled($referenceKey)
            && is_string($storedReferenceKey)
            && mb_strtoupper($storedReferenceKey) === mb_strtoupper((string) $referenceKey);

        return $matchesPath || $matchesReferenceKey;
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
