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
use App\Support\Media\UnresolvableMediaIdentityException;
use App\Support\Media\UnsafeLegacyOwnerMediaException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;

class PublicDefaultImageResolver
{
    /** @var array<string, array{mode: string, path: string|null, media_reference_key: string|null}> */
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
    public function contentItemImage(ContentItem $item, bool $inheritGroupCover = true): array
    {
        [$hasPrimaryAttachment, $primaryMedia] = $this->ownerImage(
            $item,
            MediaAttachmentRole::PrimaryImage,
        );

        if ($hasPrimaryAttachment && $primaryMedia instanceof Media && $this->publicMediaDelivery->canDisplay($primaryMedia)) {
            return $this->publicDiskImage($primaryMedia->path, 'item', (string) $item->title);
        }

        if (! $hasPrimaryAttachment && $primaryMedia instanceof Media && $this->publicMediaDelivery->canDisplay($primaryMedia)) {
            return $this->publicDiskImage($primaryMedia->path, 'item', (string) $item->title);
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
            $groupPath = $this->contentGroupCoverPath($item->contentGroup);

            if (filled($groupPath)) {
                return $this->publicDiskImage($groupPath, 'group', $this->groupCoverAlt($item->contentGroup));
            }
        }

        return $this->familyImage('content_item', 'content_item_default', (string) $item->title);
    }

    /**
     * @return array{url: string|null, source: string, path: string|null, alt: string|null}
     */
    public function contentGroupImage(ContentGroup $group): array
    {
        $path = $this->contentGroupCoverPath($group);

        if (filled($path)) {
            return $this->publicDiskImage($path, 'group', $this->groupCoverAlt($group));
        }

        return $this->familyImage('content_group', 'content_group_default', (string) $group->title);
    }

    public function contentGroupCoverPath(ContentGroup $group): ?string
    {
        [$hasAttachment, $media] = $this->ownerImage($group, MediaAttachmentRole::Cover);

        if ($hasAttachment && $media instanceof Media && $this->publicMediaDelivery->canDisplay($media)) {
            return $media->path;
        }

        return ! $hasAttachment && $media instanceof Media && $this->publicMediaDelivery->canDisplay($media)
            ? $media->path
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
     * @return array{mode: string, path: string|null, media_reference_key: string|null}
     */
    private function familyConfig(string $family): array
    {
        if (array_key_exists($family, $this->familyConfigs)) {
            return $this->familyConfigs[$family];
        }

        $defaults = PublicFrontConfigRegistry::defaults()['default_images'][$family] ?? [
            'mode' => 'inherit',
            'path' => null,
            'media_reference_key' => null,
        ];
        $config = $this->context->defaultImages()[$family] ?? [];

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

        return $this->familyConfigs[$family] = [
            'mode' => is_string($config['mode'] ?? null) ? $config['mode'] : $defaults['mode'],
            'path' => $path,
            'media_reference_key' => $referenceKey,
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

    private function groupCoverAlt(ContentGroup $group): string
    {
        return filled($group->cover_alt_text)
            ? (string) $group->cover_alt_text
            : (string) $group->title;
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
        } catch (UnsafeLegacyOwnerMediaException) {
            return [true, null];
        } catch (UnresolvableMediaIdentityException) {
            return [false, null];
        }

        return [$identity['has_attachment'], $identity['media']];
    }
}
