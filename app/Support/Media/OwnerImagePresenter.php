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
    /**
     * @var array<string, array{
     *     owner: ContentGroup|ContentItem,
     *     snapshot: array<string, mixed>
     * }>
     */
    private array $preparedOwners = [];

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
        $prepared = $this->preparedOwner($owner, $role);
        $owner = $prepared['owner'];
        $snapshot = $prepared['snapshot'];
        $reviewMedia = $this->reviewMedia(
            $owner,
            $snapshot['direct_media'],
            $snapshot['effective_media'],
        );

        return new OwnerImagePresentation(
            effectiveSource: $snapshot['effective_source'],
            effectivePreviewUrl: $snapshot['effective_preview_url'],
            effectiveAlt: is_string($snapshot['effective']['alt']) ? $snapshot['effective']['alt'] : null,
            hasDirectAttachment: $snapshot['attachment'] instanceof MediaAttachment,
            brokenDirect: $snapshot['broken_direct'],
            canRemoveDirect: $snapshot['attachment'] instanceof MediaAttachment || $snapshot['diagnostic'] !== null,
            canImportExternal: $owner instanceof ContentItem
                && $snapshot['effective_source'] === 'external_url'
                && ! $snapshot['attachment'] instanceof MediaAttachment
                && filled($owner->external_thumbnail_url),
            expectedMediaId: $snapshot['expected_media_id'],
            expectedLegacyPath: $snapshot['expected_legacy_path'],
            unsafeFingerprint: $snapshot['unsafe_fingerprint'],
            media: $snapshot['detail_media'] instanceof Media
                ? $this->mediaMetadata($snapshot['detail_media'])
                : null,
            reviewMedia: $reviewMedia,
            warningCodes: $snapshot['warning_codes'],
        );
    }

    /**
     * @param  array{commit: string, cancel: string, admission?: string}  $commitBoundary
     */
    public function choice(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        int|string|null $pendingIdentity,
        array $commitBoundary,
    ): OwnerImageChoicePresentation {
        [$ownerKind, $ownerKindLabel, $slotLabel] = $this->choiceLabels($owner, $role);

        if (! $owner->exists) {
            $pendingMedia = $this->pendingMedia($pendingIdentity, $role);

            return new OwnerImageChoicePresentation(
                ownerKind: $ownerKind,
                ownerKindLabel: $ownerKindLabel,
                ownerLabel: filled($owner->title) ? (string) $owner->title : null,
                slotKind: $role->value,
                slotLabel: $slotLabel,
                isProspective: true,
                directState: 'absent',
                directMedia: null,
                shownNowSource: 'none',
                shownNowMedia: null,
                shownNowPreviewUrl: null,
                savedMediaId: null,
                savedReferenceKey: null,
                pendingKind: $pendingMedia instanceof Media ? 'replacement' : 'unchanged',
                pendingMedia: $pendingMedia instanceof Media ? $this->choiceMedia($pendingMedia) : null,
                canClearPending: $pendingMedia instanceof Media,
                canChooseAutomatic: false,
                expectedMediaId: null,
                expectedLegacyPath: null,
                unsafeFingerprint: null,
                commitBoundary: $commitBoundary,
                warningCodes: [],
            );
        }

        $prepared = $this->preparedOwner($owner, $role);
        $owner = $prepared['owner'];
        $snapshot = $prepared['snapshot'];
        $attachment = $snapshot['attachment'];
        $directMedia = $snapshot['direct_media'];
        $pendingMedia = $this->pendingMedia($pendingIdentity, $role);
        $savedMediaId = $snapshot['expected_media_id'];
        $savedReferenceKey = $directMedia instanceof Media && filled($directMedia->reference_key)
            ? (string) $directMedia->reference_key
            : null;
        $canChooseAutomatic = $attachment instanceof MediaAttachment
            && ($directMedia instanceof Media
                ? Gate::allows('detach', $directMedia)
                : Gate::allows('viewAny', config('curator.model', Media::class)));
        $pendingKind = $this->pendingKind(
            $pendingIdentity,
            $pendingMedia,
            $savedMediaId,
            $savedReferenceKey,
            $canChooseAutomatic,
        );
        $pendingFallbackSource = null;
        $pendingFallbackPreviewUrl = null;

        if ($pendingKind === 'automatic_fallback') {
            $fallback = $owner instanceof ContentGroup
                ? $this->defaultImageResolver->contentGroupImage($owner, ignoreOwnImage: true)
                : $this->defaultImageResolver->contentItemImage($owner, ignoreOwnImage: true);
            $pendingFallbackSource = match ($fallback['source']) {
                'item_external' => 'external_url',
                'group' => 'inherited_podcast_cover',
                'content_item_default', 'content_group_default' => 'configured_default',
                'global_default' => 'global_default',
                default => 'none',
            };
            $pendingFallbackPreviewUrl = is_string($fallback['url'] ?? null) && filled($fallback['url'])
                ? $fallback['url']
                : null;
        }

        return new OwnerImageChoicePresentation(
            ownerKind: $ownerKind,
            ownerKindLabel: $ownerKindLabel,
            ownerLabel: filled($owner->title) ? (string) $owner->title : null,
            slotKind: $role->value,
            slotLabel: $slotLabel,
            isProspective: false,
            directState: match (true) {
                $attachment instanceof MediaAttachment && ($directMedia === null || $snapshot['broken_direct']) => 'broken',
                $attachment instanceof MediaAttachment => 'present',
                $snapshot['unsafe_fingerprint'] !== null => 'unsafe_legacy',
                default => 'absent',
            },
            directMedia: $directMedia instanceof Media ? $this->choiceMedia($directMedia) : null,
            shownNowSource: $snapshot['effective_source'],
            shownNowMedia: $snapshot['effective_media'] instanceof Media
                ? $this->choiceMedia($snapshot['effective_media'])
                : null,
            shownNowPreviewUrl: $snapshot['effective_preview_url'],
            savedMediaId: $savedMediaId,
            savedReferenceKey: $savedReferenceKey,
            pendingKind: $pendingKind,
            pendingMedia: $pendingKind === 'automatic_fallback' || ! $pendingMedia instanceof Media
                ? null
                : $this->choiceMedia($pendingMedia),
            canClearPending: $pendingKind !== 'unchanged',
            canChooseAutomatic: $canChooseAutomatic,
            expectedMediaId: $snapshot['expected_media_id'],
            expectedLegacyPath: $snapshot['expected_legacy_path'],
            unsafeFingerprint: $snapshot['unsafe_fingerprint'],
            commitBoundary: $commitBoundary,
            warningCodes: $snapshot['warning_codes'],
            pendingFallbackSource: $pendingFallbackSource,
            pendingFallbackPreviewUrl: $pendingFallbackPreviewUrl,
        );
    }

    public function pickerIdentity(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): int|string|null {
        if (! $owner->exists) {
            return null;
        }

        $snapshot = $this->preparedOwner($owner, $role)['snapshot'];
        $identityMedia = $snapshot['identity_media'];

        if ($snapshot['attachment'] instanceof MediaAttachment && $identityMedia instanceof Media) {
            return (int) $identityMedia->getKey();
        }

        return $identityMedia instanceof Media && filled($identityMedia->reference_key)
            ? (string) $identityMedia->reference_key
            : null;
    }

    public function refresh(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): void {
        unset($this->preparedOwners[$this->preparedOwnerKey($owner, $role)]);
        $this->preparedOwner($owner, $role, forceFresh: true);
    }

    /**
     * @return array{
     *     attachment: MediaAttachment|null,
     *     direct_media: Media|null,
     *     diagnostic: LegacyOwnerMediaDiagnostic|null,
     *     effective: array{url: string|null, source: string, path: string|null, alt: string|null},
     *     effective_source: string,
     *     effective_media: Media|null,
     *     identity_media: Media|null,
     *     detail_media: Media|null,
     *     warning_codes: array<int, string>,
     *     broken_direct: bool,
     *     effective_preview_url: string|null,
     *     expected_media_id: int|null,
     *     expected_legacy_path: string|null,
     *     unsafe_fingerprint: string|null
     * }
     */
    private function projectPreparedOwner(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): array {
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

        return [
            'attachment' => $attachment,
            'direct_media' => $directMedia,
            'diagnostic' => $diagnostic,
            'effective' => $effective,
            'effective_source' => $effectiveSource,
            'effective_media' => $effectiveMedia,
            'identity_media' => $identityMedia,
            'detail_media' => $directMedia ?? $effectiveMedia,
            'warning_codes' => $warningCodes,
            'broken_direct' => $brokenDirect,
            'effective_preview_url' => $effectivePreviewUrl,
            'expected_media_id' => $attachment instanceof MediaAttachment ? (int) $attachment->media_id : null,
            'expected_legacy_path' => $expectedLegacyPath,
            'unsafe_fingerprint' => $diagnostic?->fingerprint,
        ];
    }

    /**
     * @return array{
     *     owner: ContentGroup|ContentItem,
     *     snapshot: array<string, mixed>
     * }
     */
    private function preparedOwner(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        bool $forceFresh = false,
    ): array {
        $key = $this->preparedOwnerKey($owner, $role);

        if (! $forceFresh && isset($this->preparedOwners[$key])) {
            return $this->preparedOwners[$key];
        }

        $preparedOwner = ! $forceFresh && $this->hasPreparedRelations($owner, $role)
            ? $owner
            : $this->freshOwner($owner, $role);

        return $this->preparedOwners[$key] = [
            'owner' => $preparedOwner,
            'snapshot' => $this->projectPreparedOwner($preparedOwner, $role),
        ];
    }

    private function preparedOwnerKey(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): string {
        return implode(':', [$owner::class, $owner->getKey(), $role->value]);
    }

    private function hasPreparedRelations(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): bool {
        if ($owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover) {
            $attachment = $owner->relationLoaded('coverMediaAttachment')
                ? $owner->getRelation('coverMediaAttachment')
                : null;

            return $owner->relationLoaded('coverMediaAttachment')
                && $owner->relationLoaded('legacyCoverMediaRows')
                && (! $attachment instanceof MediaAttachment || $attachment->relationLoaded('media'));
        }

        if ($owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage) {
            $attachment = $owner->relationLoaded('primaryImageMediaAttachment')
                ? $owner->getRelation('primaryImageMediaAttachment')
                : null;
            $group = $owner->relationLoaded('contentGroup')
                ? $owner->getRelation('contentGroup')
                : null;
            $groupAttachment = $group instanceof ContentGroup && $group->relationLoaded('coverMediaAttachment')
                ? $group->getRelation('coverMediaAttachment')
                : null;

            return $owner->relationLoaded('contentGroup')
                && $owner->relationLoaded('primaryImageMediaAttachment')
                && $owner->relationLoaded('legacyPrimaryImageMediaRows')
                && (! $attachment instanceof MediaAttachment || $attachment->relationLoaded('media'))
                && $group instanceof ContentGroup
                && $group->relationLoaded('coverMediaAttachment')
                && $group->relationLoaded('legacyCoverMediaRows')
                && (! $groupAttachment instanceof MediaAttachment || $groupAttachment->relationLoaded('media'));
        }

        throw new InvalidArgumentException('The media attachment role is incompatible with this owner.');
    }

    /**
     * @return array{string, string, string}
     */
    private function choiceLabels(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): array {
        return match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => [
                'podcast',
                __('admin.settings_backup_snapshot_screens.podcast'),
                __('admin.fields.cover_path'),
            ],
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => [
                'episode',
                __('admin.settings_backup_snapshot_screens.episode'),
                __('admin.fields.image_path'),
            ],
            default => throw new InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };
    }

    private function pendingMedia(
        int|string|null $pendingIdentity,
        MediaAttachmentRole $role,
    ): ?Media {
        if ($pendingIdentity === null || $pendingIdentity === '') {
            return null;
        }

        $preservedMediaId = MediaAttachmentFormState::preservedMediaId($pendingIdentity);
        $media = is_int($pendingIdentity) || (is_string($pendingIdentity) && ctype_digit($pendingIdentity))
            ? $this->mediaRecordScope->findInventory($pendingIdentity)
            : ($preservedMediaId !== null
                ? $this->mediaRecordScope->findInventory($preservedMediaId)
                : $this->mediaRecordScope->findByReferenceKey((string) $pendingIdentity, $role->purpose()));

        if (! $media instanceof Media || Gate::denies('select', $media)) {
            return null;
        }

        return $this->inventoryDiagnostics->freshSelectionBlockedReason($media) === null
            ? $media
            : null;
    }

    private function pendingKind(
        int|string|null $pendingIdentity,
        ?Media $pendingMedia,
        ?int $savedMediaId,
        ?string $savedReferenceKey,
        bool $canChooseAutomatic,
    ): string {
        if ($pendingIdentity === null || $pendingIdentity === '') {
            return $canChooseAutomatic ? 'automatic_fallback' : 'unchanged';
        }

        if (! $pendingMedia instanceof Media) {
            return 'unchanged';
        }

        if ((int) $pendingMedia->getKey() === $savedMediaId
            || (is_string($savedReferenceKey) && $pendingMedia->reference_key === $savedReferenceKey)) {
            return 'unchanged';
        }

        return 'replacement';
    }

    /**
     * @return array{id: int, reference_key: string, label: string, preview_url: string|null, details_url: string|null}
     */
    private function choiceMedia(Media $media): array
    {
        $previewBlocked = collect($this->inventoryDiagnostics->reasons($media))
            ->intersect(['storage_disk', 'missing_file', 'audience_denied', 'unsanitized_svg'])
            ->isNotEmpty();

        return [
            'id' => (int) $media->getKey(),
            'reference_key' => (string) $media->reference_key,
            'label' => filled($media->title) ? (string) $media->title : (string) $media->name,
            'preview_url' => Gate::allows('view', $media) && ! $previewBlocked
                ? $this->inventoryDiagnostics->previewUrl($media)
                : null,
            'details_url' => Gate::allows('update', $media)
                ? MediaResource::getUrl('edit', ['record' => $media])
                : null,
        ];
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
