<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\Media\MediaResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Support\PublicFront\PublicDefaultImageResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use InvalidArgumentException;

class OwnerImagePresenter
{
    public function __construct(
        private readonly MediaAttachmentIdentityResolver $identityResolver,
        private readonly MediaInventoryDiagnostics $inventoryDiagnostics,
        private readonly MediaRecordScope $mediaRecordScope,
        private readonly PublicDefaultImageResolver $defaultImageResolver,
    ) {}

    public function present(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): OwnerImagePresentation {
        $owner = $this->freshOwner($owner, $role);
        [$attachmentRelation, $legacyColumn] = $this->ownerContract($owner, $role);
        $attachment = $owner->getRelation($attachmentRelation);
        $attachment = $attachment instanceof MediaAttachment ? $attachment : null;
        $directMedia = $attachment?->getRelation('media');
        $directMedia = $directMedia instanceof Media ? $directMedia : null;
        collect([
            $directMedia,
            $owner instanceof ContentItem
                ? $owner->contentGroup?->coverMediaAttachment?->media
                : null,
        ])
            ->filter(fn (mixed $media): bool => $media instanceof Media)
            ->each(function (Media $media): void {
                $this->inventoryDiagnostics->forget($media);
                $this->defaultImageResolver->forget($media);
            });
        $diagnostic = null;
        $identityMedia = null;

        try {
            $identityMedia = $this->identityResolver->resolve($owner, $role)['media'];
        } catch (UnsafeLegacyOwnerMediaException $exception) {
            $diagnostic = $exception->diagnostic;
        } catch (UnresolvableMediaIdentityException|InvalidArgumentException) {
            // The effective resolver owns fallback behavior for unresolved identity.
        }

        $identityMedia = $identityMedia instanceof Media ? $identityMedia : null;
        $effective = $owner instanceof ContentGroup
            ? $this->defaultImageResolver->contentGroupImage($owner)
            : $this->defaultImageResolver->contentItemImage($owner);
        $effectiveSource = $this->effectiveSource($owner, $attachment, $identityMedia, $effective['source']);
        $effectiveMedia = $this->effectiveMedia($owner, $role, $identityMedia, $effective);
        $detailMedia = $directMedia ?? $effectiveMedia;
        $warningCodes = collect([
            $diagnostic?->code->value,
            ...($directMedia instanceof Media ? $this->inventoryDiagnostics->reasons($directMedia) : []),
        ])->filter()->unique()->values()->all();
        $brokenDirect = $attachment instanceof MediaAttachment
            && ! in_array($effectiveSource, ['direct_media'], true);

        if ($attachment instanceof MediaAttachment && ! $directMedia instanceof Media) {
            $brokenDirect = true;
        }

        $effectivePreviewUrl = $effectiveMedia instanceof Media
            ? $this->inventoryDiagnostics->previewUrl($effectiveMedia)
            : (is_string($effective['url']) && filled($effective['url']) ? $effective['url'] : null);
        $expectedLegacyPath = is_string($owner->getAttribute($legacyColumn))
            ? $owner->getAttribute($legacyColumn)
            : null;
        $reviewMedia = $this->reviewMedia($owner, $directMedia, $effectiveMedia);

        return new OwnerImagePresentation(
            effectiveSource: $effectiveSource,
            effectivePreviewUrl: $effectivePreviewUrl,
            effectiveAlt: is_string($effective['alt']) ? $effective['alt'] : null,
            hasDirectAttachment: $attachment instanceof MediaAttachment,
            brokenDirect: $brokenDirect,
            canRemoveDirect: $attachment instanceof MediaAttachment || $diagnostic !== null,
            canImportExternal: $owner instanceof ContentItem
                && $effectiveSource === 'external_url'
                && ! $attachment instanceof MediaAttachment
                && filled($owner->external_thumbnail_url),
            expectedMediaId: $attachment instanceof MediaAttachment ? (int) $attachment->media_id : null,
            expectedLegacyPath: $expectedLegacyPath,
            unsafeFingerprint: $diagnostic?->fingerprint,
            media: $detailMedia instanceof Media ? $this->mediaMetadata($detailMedia) : null,
            reviewMedia: $reviewMedia,
            warningCodes: $warningCodes,
        );
    }

    private function freshOwner(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): ContentGroup|ContentItem {
        $relations = match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => [
                'coverMediaAttachment.media',
                'legacyCoverMediaRows',
            ],
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => [
                'contentGroup.coverMediaAttachment.media',
                'contentGroup.legacyCoverMediaRows',
                'primaryImageMediaAttachment.media',
                'legacyPrimaryImageMediaRows',
            ],
            default => throw new InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };

        $fresh = $owner->newQuery()
            ->with($relations)
            ->find($owner->getKey());

        if (! $fresh instanceof ContentGroup && ! $fresh instanceof ContentItem) {
            throw (new ModelNotFoundException)->setModel($owner::class, [$owner->getKey()]);
        }

        return $fresh;
    }

    /**
     * @param  array{url: string|null, source: string, path: string|null, alt: string|null}  $effective
     */
    private function effectiveMedia(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        ?Media $identityMedia,
        array $effective,
    ): ?Media {
        if ($effective['source'] === 'item' && $owner instanceof ContentItem) {
            return $identityMedia;
        }

        if ($effective['source'] === 'group') {
            if ($owner instanceof ContentGroup) {
                return $identityMedia;
            }

            try {
                $groupMedia = $this->identityResolver->resolve(
                    $owner->contentGroup,
                    MediaAttachmentRole::Cover,
                )['media'];

                return $groupMedia instanceof Media ? $groupMedia : null;
            } catch (UnsafeLegacyOwnerMediaException|UnresolvableMediaIdentityException|InvalidArgumentException) {
                return null;
            }
        }

        if (! in_array($effective['source'], ['content_item_default', 'content_group_default', 'global_default'], true)) {
            return null;
        }

        if (! is_string($effective['path']) || blank($effective['path'])) {
            return null;
        }

        $matches = $this->mediaRecordScope->inventoryQuery()
            ->where('path', $effective['path'])
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            return null;
        }

        $media = $matches->first();

        return $media instanceof Media ? $media : null;
    }

    private function effectiveSource(
        ContentGroup|ContentItem $owner,
        ?MediaAttachment $attachment,
        ?Media $identityMedia,
        string $source,
    ): string {
        return match ($source) {
            'item' => $attachment instanceof MediaAttachment
                ? 'direct_media'
                : ($identityMedia instanceof Media ? 'compatibility_media' : 'none'),
            'group' => $owner instanceof ContentItem
                ? 'inherited_podcast_cover'
                : ($attachment instanceof MediaAttachment ? 'direct_media' : 'compatibility_media'),
            'item_external' => 'external_url',
            'content_item_default', 'content_group_default' => 'configured_default',
            'global_default' => 'global_default',
            default => 'none',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaMetadata(Media $media): array
    {
        $canView = Gate::allows('view', $media);
        $canDownload = Gate::allows('download', $media);
        $canUpdate = Gate::allows('update', $media);
        $width = is_numeric($media->width) && (int) $media->width > 0 ? (int) $media->width : null;
        $height = is_numeric($media->height) && (int) $media->height > 0 ? (int) $media->height : null;
        $originalFilename = data_get($media->exif, 'original_filename');

        return [
            'id' => (int) $media->getKey(),
            'title' => filled($media->title) ? (string) $media->title : (string) $media->name,
            'original_filename' => is_string($originalFilename) && filled($originalFilename)
                ? $originalFilename
                : null,
            'stored_filename' => basename((string) $media->path),
            'mime' => filled($media->type) ? (string) $media->type : null,
            'extension' => filled($media->ext) ? (string) $media->ext : null,
            'dimensions' => $width !== null && $height !== null
                ? "{$width} × {$height}"
                : null,
            'file_size' => is_numeric($media->size) && (int) $media->size >= 0
                ? Number::fileSize((int) $media->size)
                : null,
            'directory' => filled($media->directory) ? (string) $media->directory : null,
            'disk' => filled($media->disk) ? (string) $media->disk : null,
            'reference_key' => filled($media->reference_key) ? (string) $media->reference_key : null,
            'updated_at' => $media->updated_at
                ? $media->updated_at->clone()->timezone('Asia/Jerusalem')->format('d/m/Y H:i')
                : null,
            'preview_url' => $canView ? $this->inventoryDiagnostics->previewUrl($media) : null,
            'download_url' => $canDownload
                ? route('admin.media-files.download', ['media' => $media->getKey()])
                : null,
            'review_url' => $canUpdate
                ? MediaResource::getUrl('edit', ['record' => $media])
                : null,
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, url: string}>
     */
    private function reviewMedia(
        ContentGroup|ContentItem $owner,
        ?Media $directMedia,
        ?Media $effectiveMedia,
    ): array {
        $legacyRelation = $owner instanceof ContentGroup
            ? 'legacyCoverMediaRows'
            : 'legacyPrimaryImageMediaRows';
        $candidates = collect([$directMedia, $effectiveMedia])
            ->merge($owner->getRelation($legacyRelation))
            ->filter(fn (mixed $media): bool => $media instanceof Media)
            ->unique(fn (Media $media): int => (int) $media->getKey())
            ->filter(fn (Media $media): bool => Gate::allows('update', $media))
            ->values();

        return $candidates
            ->map(fn (Media $media): array => [
                'id' => (int) $media->getKey(),
                'label' => filled($media->title) ? (string) $media->title : (string) $media->name,
                'url' => MediaResource::getUrl('edit', ['record' => $media]),
            ])
            ->all();
    }

    /**
     * @return array{string, string}
     */
    private function ownerContract(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): array {
        return match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => [
                'coverMediaAttachment',
                'cover_path',
            ],
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => [
                'primaryImageMediaAttachment',
                'image_path',
            ],
            default => throw new InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };
    }
}
