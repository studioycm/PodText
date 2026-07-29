<?php

namespace App\Support\PublicFront;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Support\Media\MediaAttachmentIdentityResolver;
use App\Support\Media\MediaIdentityResolver;
use App\Support\Media\PublicMediaDelivery;
use App\Support\Media\UnsafeLegacyOwnerMediaException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;

class PublicDefaultImageResolver
{
    /** @var array<string, array{mode: string, path: string|null, media_reference_key: string|null, media: Media|null}> */
    private array $familyConfigs = [];

    public function __construct(
        private readonly PublicFrontRenderContext $context,
        private readonly MediaIdentityResolver $mediaIdentityResolver,
        private readonly MediaAttachmentIdentityResolver $attachmentIdentityResolver,
        private readonly PublicMediaDelivery $publicMediaDelivery,
    ) {}

    /**
     * @return array{url: string|null, source: string, path: string|null, alt: string|null}
     */
    public function contentItemImage(ContentItem $item, bool $inheritGroupCover = true, bool $ignoreOwnImage = false): array
    {
        if (! $ignoreOwnImage) {
            [$hasPrimaryAttachment, $primaryMedia] = $this->ownerImage(
                $item,
                MediaAttachmentRole::PrimaryImage,
            );

            if ($primaryMedia instanceof Media && $this->publicMediaDelivery->canDisplay($primaryMedia)) {
                return $this->publicDiskImage(
                    $primaryMedia->path,
                    'item',
                    filled($primaryMedia->title) ? (string) $primaryMedia->title : (string) $item->title,
                );
            }
        }

        if (filled($item->external_thumbnail_url)) {
            return [
                'url' => (string) $item->external_thumbnail_url,
                'source' => 'item_external',
                'path' => null,
                'alt' => (string) $item->title,
            ];
        }

        if ($inheritGroupCover && $this->mode('content_item') !== 'none' && $item->contentGroup instanceof ContentGroup) {
            $groupMedia = $this->contentGroupCoverMedia($item->contentGroup);

            if ($groupMedia instanceof Media) {
                return $this->publicDiskImage(
                    $groupMedia->path,
                    'group',
                    $this->groupCoverAlt($item->contentGroup, $groupMedia),
                );
            }
        }

        return $this->familyImage('content_item', 'content_item_default', (string) $item->title);
    }

    /**
     * @return array{url: string|null, source: string, path: string|null, alt: string|null}
     */
    public function contentGroupImage(ContentGroup $group, bool $ignoreOwnImage = false): array
    {
        $media = $ignoreOwnImage ? null : $this->contentGroupCoverMedia($group);

        if ($media instanceof Media) {
            return $this->publicDiskImage($media->path, 'group', $this->groupCoverAlt($group, $media));
        }

        return $this->familyImage('content_group', 'content_group_default', (string) $group->title);
    }

    public function contentGroupCoverPath(ContentGroup $group): ?string
    {
        return $this->contentGroupCoverMedia($group)?->path;
    }

    public function contentGroupCoverMedia(ContentGroup $group): ?Media
    {
        [, $media] = $this->ownerImage($group, MediaAttachmentRole::Cover);

        return $media instanceof Media && $this->publicMediaDelivery->canDisplay($media)
            ? $media
            : null;
    }

    public function forget(Media $media): void
    {
        $this->publicMediaDelivery->forget($media);
    }

    /**
     * @return array{url: string|null, source: string, path: string|null, alt: string|null}
     */
    public function contributorImage(Author $author): array
    {
        return $this->familyImage('contributor', 'contributor_default', (string) $author->name);
    }

    public function allowsContentItemGroupCover(): bool
    {
        return $this->mode('content_item') !== 'none';
    }

    /** @param iterable<int, ContentItem> $items */
    public function primeContentItems(iterable $items): void
    {
        $items = collect($items);
        (new EloquentCollection($items->all()))->loadMissing('contentGroup.coverMediaAttachment.media');
        $this->attachmentIdentityResolver->prime($items, MediaAttachmentRole::PrimaryImage);
        $groups = $items
            ->map(fn (ContentItem $item): ?ContentGroup => $item->contentGroup)
            ->filter(fn (?ContentGroup $group): bool => $group instanceof ContentGroup)
            ->unique(fn (ContentGroup $group): int => (int) $group->getKey())
            ->values();
        $this->attachmentIdentityResolver->prime($groups, MediaAttachmentRole::Cover);
    }

    /** @param iterable<int, ContentGroup> $groups */
    public function primeContentGroups(iterable $groups): void
    {
        $this->attachmentIdentityResolver->prime($groups, MediaAttachmentRole::Cover);
    }

    public function hasConfiguredDefault(string $family): bool
    {
        $config = $this->familyConfig($family);

        if ($config['mode'] === 'custom' && filled($config['path'])) {
            return true;
        }

        if ($config['mode'] === 'none') {
            return false;
        }

        $global = $this->familyConfig('global');

        return $global['mode'] === 'custom' && filled($global['path']);
    }

    /**
     * @param  array<string, mixed>|null  $defaultImages
     * @return array{
     *     mode: string,
     *     direct_media: Media|null,
     *     shown_media: Media|null,
     *     shown_source: string,
     *     shown_path: string|null,
     *     shown_url: string|null
     * }
     */
    public function projectDefaultFamily(string $family, ?array $defaultImages = null): array
    {
        $config = $defaultImages === null
            ? $this->familyConfig($family)
            : $this->familyConfigFrom($defaultImages, $family);
        $global = $defaultImages === null
            ? $this->familyConfig('global')
            : $this->familyConfigFrom($defaultImages, 'global');
        $shown = match (true) {
            $config['mode'] === 'custom' && $config['media'] instanceof Media && filled($config['path']) => [
                'media' => $config['media'],
                'source' => 'configured_default',
            ],
            $config['mode'] === 'none' => [
                'media' => null,
                'source' => 'none',
            ],
            $family !== 'global'
                && $global['mode'] === 'custom'
                && $global['media'] instanceof Media
                && filled($global['path']) => [
                    'media' => $global['media'],
                    'source' => 'global_default',
                ],
            default => [
                'media' => null,
                'source' => 'none',
            ],
        };
        $shownMedia = $shown['media'];

        return [
            'mode' => $config['mode'],
            'direct_media' => $config['media'],
            'shown_media' => $shownMedia,
            'shown_source' => $shown['source'],
            'shown_path' => $shownMedia?->path,
            'shown_url' => $shownMedia instanceof Media
                ? Storage::disk('public')->url($shownMedia->path)
                : null,
        ];
    }

    /**
     * @return array{mode: string, path: string|null, media_reference_key: string|null, media: Media|null}
     */
    private function familyConfig(string $family): array
    {
        if (array_key_exists($family, $this->familyConfigs)) {
            return $this->familyConfigs[$family];
        }

        return $this->familyConfigs[$family] = $this->familyConfigFrom(
            $this->context->defaultImages(),
            $family,
        );
    }

    /**
     * @param  array<string, mixed>  $defaultImages
     * @return array{mode: string, path: string|null, media_reference_key: string|null, media: Media|null}
     */
    private function familyConfigFrom(array $defaultImages, string $family): array
    {
        $defaults = PublicFrontConfigRegistry::defaults()['default_images'][$family] ?? [
            'mode' => 'inherit',
            'path' => null,
            'media_reference_key' => null,
        ];
        $config = $defaultImages[$family] ?? [];

        if (! is_array($config)) {
            $config = [];
        }

        $referenceKey = is_string($config['media_reference_key'] ?? null)
            ? $config['media_reference_key']
            : null;
        $legacyPath = is_string($config['path'] ?? null) && filled($config['path'])
            ? $config['path']
            : null;

        try {
            $media = $this->mediaIdentityResolver->resolve(
                $referenceKey,
                $legacyPath,
                ImageUploadPurpose::DefaultImage,
            );
        } catch (UnsafeLegacyOwnerMediaException $exception) {
            report($exception);
            $media = null;
        } catch (\InvalidArgumentException) {
            $media = null;
        }

        $path = $media instanceof Media && $this->publicMediaDelivery->canDisplay($media)
            ? $media->path
            : null;

        return [
            'mode' => is_string($config['mode'] ?? null) ? $config['mode'] : $defaults['mode'],
            'path' => $path,
            'media_reference_key' => $referenceKey,
            'media' => $media,
        ];
    }

    private function mode(string $family): string
    {
        return $this->familyConfig($family)['mode'];
    }

    /**
     * @return array{url: string|null, source: string, path: string|null, alt: string|null}
     */
    private function familyImage(string $family, string $source, ?string $alt = null): array
    {
        $config = $this->familyConfig($family);

        if ($config['mode'] === 'custom' && filled($config['path'])) {
            return $this->publicDiskImage($config['path'], $source, $alt);
        }

        if ($config['mode'] === 'none') {
            return $this->emptyImage();
        }

        $global = $this->familyConfig('global');

        if ($global['mode'] === 'custom' && filled($global['path'])) {
            return $this->publicDiskImage($global['path'], 'global_default', $alt);
        }

        return $this->emptyImage();
    }

    /**
     * @return array{url: string|null, source: string, path: string|null, alt: string|null}
     */
    private function publicDiskImage(string $path, string $source, ?string $alt = null): array
    {
        return [
            'url' => Storage::disk('public')->url($path),
            'source' => $source,
            'path' => $path,
            'alt' => $alt,
        ];
    }

    /**
     * @return array{url: null, source: string, path: null, alt: null}
     */
    private function emptyImage(): array
    {
        return [
            'url' => null,
            'source' => 'fallback',
            'path' => null,
            'alt' => null,
        ];
    }

    private function groupCoverAlt(ContentGroup $group, ?Media $media = null): string
    {
        if (filled($group->cover_alt_text)) {
            return (string) $group->cover_alt_text;
        }

        if ($media instanceof Media && filled($media->title)) {
            return (string) $media->title;
        }

        return (string) $group->title;
    }

    /**
     * @return array{bool, Media|null}
     */
    private function ownerImage(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): array {
        try {
            $identity = $this->attachmentIdentityResolver->resolve($owner, $role);
        } catch (\InvalidArgumentException) {
            return [true, null];
        }

        return [$identity['has_attachment'], $identity['media']];
    }
}
