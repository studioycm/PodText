<?php

namespace App\Support\Media;

use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Settings\PublicContentSettings;
use Illuminate\Support\Collection;
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

        $attachments = Schema::hasTable('media_attachments')
            ? DB::table('media_attachments')
                ->where('media_id', $media->getKey())
                ->get(['attachable_type', 'attachable_id', 'role'])
            : collect();
        $groupTitles = ContentGroup::query()
            ->whereKey($attachments->whereIn('attachable_type', ['content_group', ContentGroup::class])->pluck('attachable_id'))
            ->pluck('title', 'id');
        $itemTitles = ContentItem::query()
            ->whereKey($attachments->whereIn('attachable_type', ['content_item', ContentItem::class])->pluck('attachable_id'))
            ->pluck('title', 'id');
        $attachmentReferences = $attachments->map(
            fn (object $attachment): string => $this->attachmentReferenceLabel(
                (string) $attachment->attachable_type,
                (int) $attachment->attachable_id,
                (string) $attachment->role,
                $groupTitles,
                $itemTitles,
            ),
        );
        $legacyDuplicates = $this->legacyDuplicateStrings($attachments, $groupTitles, $itemTitles);

        return collect($this->nonAttachmentReferencesForMedia($media))
            ->reject(fn (string $reference): bool => in_array($reference, $legacyDuplicates, true))
            ->merge($attachmentReferences)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One canonical humanized owner string for an attachment reference.
     *
     * @param  Collection<int|string, string|null>  $groupTitles
     * @param  Collection<int|string, string|null>  $itemTitles
     */
    private function attachmentReferenceLabel(
        string $type,
        int $id,
        string $role,
        Collection $groupTitles,
        Collection $itemTitles,
    ): string {
        $title = match (true) {
            in_array($type, ['content_group', ContentGroup::class], true) => $groupTitles[$id] ?? null,
            in_array($type, ['content_item', ContentItem::class], true) => $itemTitles[$id] ?? null,
            default => null,
        };

        if (filled($title)) {
            return $this->attachableKindLabel($type).': '.$title.' ('.$this->attachmentRoleLabel($role).')';
        }

        return __('admin.media_references.attachment', [
            'type' => $this->attachableKindLabel($type),
            'id' => $id,
            'role' => $this->attachmentRoleLabel($role),
        ]);
    }

    /**
     * The legacy path-column strings that describe the same usage an
     * attachment already covers, so one real usage renders once.
     *
     * @param  Collection<int, object>  $attachments
     * @param  Collection<int|string, string|null>  $groupTitles
     * @param  Collection<int|string, string|null>  $itemTitles
     * @return array<int, string>
     */
    private function legacyDuplicateStrings(
        Collection $attachments,
        Collection $groupTitles,
        Collection $itemTitles,
    ): array {
        $duplicates = [];

        foreach ($attachments as $attachment) {
            $id = (int) $attachment->attachable_id;

            if (
                (string) $attachment->role === 'cover'
                && in_array((string) $attachment->attachable_type, ['content_group', ContentGroup::class], true)
                && filled($groupTitles[$id] ?? null)
            ) {
                $duplicates[] = __('admin.media_references.content_group_cover', ['title' => $groupTitles[$id]]);
            }

            if (
                (string) $attachment->role === 'primary_image'
                && in_array((string) $attachment->attachable_type, ['content_item', ContentItem::class], true)
                && filled($itemTitles[$id] ?? null)
            ) {
                $duplicates[] = __('admin.media_references.content_item_image', ['title' => $itemTitles[$id]]);
            }
        }

        return $duplicates;
    }

    /**
     * @return array<int, string>
     */
    /** @return array<int, array{label: string, url: string|null}> */
    public function linkedReferencesForMedia(Media $media): array
    {
        $links = collect();
        $attachedGroupIds = [];
        $attachedItemIds = [];

        if (Schema::hasTable('media_attachments')) {
            $attachments = DB::table('media_attachments')
                ->where('media_id', $media->getKey())
                ->get(['attachable_type', 'attachable_id', 'role']);
            $groupTitles = ContentGroup::query()
                ->whereKey($attachments->whereIn('attachable_type', ['content_group', ContentGroup::class])->pluck('attachable_id'))
                ->pluck('title', 'id');
            $itemTitles = ContentItem::query()
                ->whereKey($attachments->whereIn('attachable_type', ['content_item', ContentItem::class])->pluck('attachable_id'))
                ->pluck('title', 'id');

            foreach ($attachments as $attachment) {
                $id = (int) $attachment->attachable_id;
                [$title, $url] = match (true) {
                    in_array((string) $attachment->attachable_type, ['content_group', ContentGroup::class], true) => [
                        (string) ($groupTitles[$id] ?? "#{$id}"),
                        ContentGroupResource::getUrl('edit', ['record' => $id], panel: 'admin'),
                    ],
                    in_array((string) $attachment->attachable_type, ['content_item', ContentItem::class], true) => [
                        (string) ($itemTitles[$id] ?? "#{$id}"),
                        ContentItemResource::getUrl('edit', ['record' => $id], panel: 'admin'),
                    ],
                    default => ["#{$id}", null],
                };

                if (in_array((string) $attachment->attachable_type, ['content_group', ContentGroup::class], true)) {
                    $attachedGroupIds[] = $id;
                } elseif (in_array((string) $attachment->attachable_type, ['content_item', ContentItem::class], true)) {
                    $attachedItemIds[] = $id;
                }
                $links->push([
                    'label' => $this->attachableKindLabel((string) $attachment->attachable_type)
                        .': '.$title
                        .' ('.$this->attachmentRoleLabel((string) $attachment->role).')',
                    'url' => $url,
                ]);
            }
        }

        $path = $this->normalize((string) $media->path);

        if ($media->disk === 'public' && $path !== null) {
            if (Schema::hasTable('content_groups')) {
                ContentGroup::query()
                    ->where('cover_path', $path)
                    ->get(['id', 'title'])
                    ->reject(fn (ContentGroup $group): bool => in_array((int) $group->getKey(), $attachedGroupIds, true))
                    ->each(fn (ContentGroup $group) => $links->push([
                        'label' => __('admin.media_references.content_group_cover', ['title' => (string) $group->title]),
                        'url' => ContentGroupResource::getUrl('edit', ['record' => $group->getKey()], panel: 'admin'),
                    ]));
            }

            if (Schema::hasTable('content_items') && Schema::hasColumn('content_items', 'image_path')) {
                ContentItem::query()
                    ->where('image_path', $path)
                    ->get(['id', 'title'])
                    ->reject(fn (ContentItem $item): bool => in_array((int) $item->getKey(), $attachedItemIds, true))
                    ->each(fn (ContentItem $item) => $links->push([
                        'label' => __('admin.media_references.content_item_image', ['title' => (string) $item->title]),
                        'url' => ContentItemResource::getUrl('edit', ['record' => $item->getKey()], panel: 'admin'),
                    ]));
            }
        }

        $settings = $this->settingsPayloads();
        $families = [
            ['menu_config', MenuHeaderSettings::class, fn (array $payload): array => $this->menuConfigIdentityReferences($path, $media->reference_key, $payload)],
            ['about_page', AboutSettings::class, fn (array $payload): array => $this->aboutPageIdentityReferences($path, $media->reference_key, $payload)],
            ['default_images', DisplaySettings::class, fn (array $payload): array => $this->defaultImageIdentityReferences($path, $media->reference_key, $payload)],
        ];

        foreach ($families as [$name, $page, $builder]) {
            foreach ($builder($settings[$name] ?? []) as $label) {
                $links->push(['label' => $label, 'url' => $page::getUrl(panel: 'admin')]);
            }
        }

        return $links
            ->unique(fn (array $link): string => $link['label'].'|'.($link['url'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * Derives the owner-based display title for a media row, by role priority:
     * podcast cover, then episode primary image, then settings usages.
     */
    public function ownerTitleForMedia(Media $media): ?string
    {
        $path = $this->normalize((string) $media->path);
        $attachments = Schema::hasTable('media_attachments')
            ? DB::table('media_attachments')
                ->where('media_id', $media->getKey())
                ->get(['attachable_type', 'attachable_id', 'role'])
            : collect();

        $groupAttachment = $attachments->first(
            fn (object $attachment): bool => in_array((string) $attachment->attachable_type, ['content_group', ContentGroup::class], true),
        );
        $groupTitle = $groupAttachment !== null
            ? ContentGroup::query()->whereKey((int) $groupAttachment->attachable_id)->value('title')
            : ($media->disk === 'public' && $path !== null && Schema::hasTable('content_groups')
                ? ContentGroup::query()->where('cover_path', $path)->value('title')
                : null);

        if (filled($groupTitle)) {
            return $groupTitle.' — '.$this->attachmentRoleLabel('cover');
        }

        $itemAttachment = $attachments->first(
            fn (object $attachment): bool => in_array((string) $attachment->attachable_type, ['content_item', ContentItem::class], true),
        );
        $itemTitle = $itemAttachment !== null
            ? ContentItem::query()->whereKey((int) $itemAttachment->attachable_id)->value('title')
            : ($media->disk === 'public' && $path !== null
                && Schema::hasTable('content_items') && Schema::hasColumn('content_items', 'image_path')
                ? ContentItem::query()->where('image_path', $path)->value('title')
                : null);

        if (filled($itemTitle)) {
            return $itemTitle.' — '.$this->attachmentRoleLabel('primary_image');
        }

        $settingsLabels = $this->settingsIdentityReferences(
            $path,
            $media->reference_key,
            $this->settingsPayloads(),
        );

        return $settingsLabels[0] ?? null;
    }

    private function attachableKindLabel(string $type): string
    {
        return match (true) {
            in_array($type, ['content_group', ContentGroup::class], true) => __('admin.settings_backup_snapshot_screens.podcast'),
            in_array($type, ['content_item', ContentItem::class], true) => __('admin.settings_backup_snapshot_screens.episode'),
            default => class_basename($type),
        };
    }

    private function attachmentRoleLabel(string $role): string
    {
        return in_array($role, ['cover', 'primary_image'], true)
            ? __("admin.media_attachment_roles.{$role}")
            : $role;
    }

    public function nonAttachmentReferencesForMedia(Media $media): array
    {
        $path = $this->normalize((string) $media->path);
        $references = $media->disk === 'public'
            ? collect($this->referencesForPath($path))
            : collect();

        return $references
            ->merge($this->settingsIdentityReferences(
                $path,
                $media->reference_key,
                $this->settingsPayloads(),
            ))
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

        $attachmentDuplicates = [];

        if ($ids !== [] && Schema::hasTable('media_attachments')) {
            $attachments = DB::table('media_attachments')
                ->whereIn('media_id', $ids)
                ->get(['media_id', 'attachable_type', 'attachable_id', 'role']);
            $groupTitles = ContentGroup::query()
                ->whereKey($attachments->whereIn('attachable_type', ['content_group', ContentGroup::class])->pluck('attachable_id'))
                ->pluck('title', 'id');
            $itemTitles = ContentItem::query()
                ->whereKey($attachments->whereIn('attachable_type', ['content_item', ContentItem::class])->pluck('attachable_id'))
                ->pluck('title', 'id');

            foreach ($attachments as $attachment) {
                $mediaId = (int) $attachment->media_id;
                $references[$mediaId][] = $this->attachmentReferenceLabel(
                    (string) $attachment->attachable_type,
                    (int) $attachment->attachable_id,
                    (string) $attachment->role,
                    $groupTitles,
                    $itemTitles,
                );
                $attachmentDuplicates[$mediaId] = array_merge(
                    $attachmentDuplicates[$mediaId] ?? [],
                    $this->legacyDuplicateStrings(collect([$attachment]), $groupTitles, $itemTitles),
                );
            }
        }

        if ($paths !== [] && Schema::hasTable('content_groups')) {
            ContentGroup::query()
                ->whereIn('cover_path', $paths)
                ->get(['title', 'cover_path'])
                ->each(function (ContentGroup $group) use ($recordsByPath, &$legacyReferences, &$references, $attachmentDuplicates): void {
                    $recordsByPath->get((string) $group->cover_path, collect())->each(function (Media $media) use ($group, &$legacyReferences, &$references, $attachmentDuplicates): void {
                        $reference = __('admin.media_references.content_group_cover', ['title' => $group->title]);

                        if (! in_array($reference, $attachmentDuplicates[(int) $media->getKey()] ?? [], true)) {
                            $references[(int) $media->getKey()][] = $reference;
                        }

                        $legacyReferences[(int) $media->getKey()][] = $reference;
                    });
                });
        }

        if ($paths !== [] && Schema::hasTable('content_items') && Schema::hasColumn('content_items', 'image_path')) {
            ContentItem::query()
                ->whereIn('image_path', $paths)
                ->get(['title', 'image_path'])
                ->each(function (ContentItem $item) use ($recordsByPath, &$legacyReferences, &$references, $attachmentDuplicates): void {
                    $recordsByPath->get((string) $item->image_path, collect())->each(function (Media $media) use ($item, &$legacyReferences, &$references, $attachmentDuplicates): void {
                        $reference = __('admin.media_references.content_item_image', ['title' => $item->title]);

                        if (! in_array($reference, $attachmentDuplicates[(int) $media->getKey()] ?? [], true)) {
                            $references[(int) $media->getKey()][] = $reference;
                        }

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
