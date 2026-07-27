<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MediaAttachmentFormState
{
    private const PRESERVED_MEDIA_ID_PREFIX = 'attached-media-id:';

    public function __construct(
        private readonly MediaAttachmentIdentityResolver $identityResolver,
        private readonly MediaRecordScope $mediaRecordScope,
        private readonly MediaAttachmentManager $attachmentManager,
        private readonly MediaInventoryDiagnostics $inventoryDiagnostics,
    ) {}

    public function referenceKey(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?string
    {
        try {
            return $this->identityResolver->referenceKey($owner, $role);
        } catch (UnsafeLegacyOwnerMediaException|UnresolvableMediaIdentityException) {
            return null;
        }
    }

    public function pickerIdentity(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): int|string|null
    {
        try {
            $identity = $this->identityResolver->resolve($owner, $role);
        } catch (UnsafeLegacyOwnerMediaException|UnresolvableMediaIdentityException) {
            return null;
        }

        if ($identity['has_attachment'] && $identity['media'] instanceof Media) {
            return (int) $identity['media']->getKey();
        }

        return $identity['media']?->reference_key;
    }

    public static function preservedMediaIdentity(int $mediaId): string
    {
        return self::PRESERVED_MEDIA_ID_PREFIX.$mediaId;
    }

    public static function preservedMediaId(mixed $identity): ?int
    {
        if (! is_string($identity) || ! str_starts_with($identity, self::PRESERVED_MEDIA_ID_PREFIX)) {
            return null;
        }

        $id = substr($identity, strlen(self::PRESERVED_MEDIA_ID_PREFIX));

        return ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{array<string, mixed>, string|null, string|null}
     */
    public function prepare(
        array $data,
        string $formField,
        ContentGroup|ContentItem|null $owner,
        MediaAttachmentRole $role,
    ): array {
        $referenceKey = $data[$formField] ?? null;
        unset($data[$formField]);

        if (($preservedMediaId = self::preservedMediaId($referenceKey)) !== null) {
            if ($owner !== null && (int) ($this->attachment($owner, $role)?->media_id ?? 0) === $preservedMediaId) {
                $data[$this->legacyColumn($owner, $role)] = $owner->getAttribute($this->legacyColumn($owner, $role));

                return [$data, self::preservedMediaIdentity($preservedMediaId), null];
            }

            throw ValidationException::withMessages([
                $formField => __('admin.validation.media_reference_key'),
            ]);
        }

        $referenceKey = is_string($referenceKey) && filled($referenceKey)
            ? mb_strtoupper(trim($referenceKey))
            : null;
        $legacyColumn = $owner instanceof ContentGroup || $owner instanceof ContentItem
            ? $this->legacyColumn($owner, $role)
            : ($role === MediaAttachmentRole::Cover ? 'cover_path' : 'image_path');

        if ($owner !== null) {
            try {
                $this->identityResolver->resolve($owner, $role);
            } catch (UnsafeLegacyOwnerMediaException|UnresolvableMediaIdentityException) {
                // An unrelated form save must leave the unsafe legacy identity intact.
            }
        }

        if ($referenceKey === null) {
            if ($owner !== null) {
                $data[$legacyColumn] = $owner->getAttribute($legacyColumn);
            } else {
                $data[$legacyColumn] = null;
            }

            return [$data, null, $owner !== null ? $this->diagnostic($owner, $role)?->fingerprint : null];
        }

        $media = $this->selectableReplacement($referenceKey, $formField, $owner, $role);

        $fingerprint = $owner !== null ? $this->diagnostic($owner, $role)?->fingerprint : null;
        // The repairer, not the ordinary record save, changes an unsafe path.
        $data[$legacyColumn] = $fingerprint !== null && $owner !== null ? $owner->getAttribute($legacyColumn) : $media->path;

        return [$data, $media->reference_key, $fingerprint];
    }

    public function persist(
        ContentGroup|ContentItem $owner,
        ?string $referenceKey,
        MediaAttachmentRole $role,
        User $actor,
        ?string $unsafeFingerprint = null,
        ?string $validationField = null,
        ?int $expectedMediaId = null,
        ?string $expectedLegacyPath = null,
        bool $enforceExpectedIdentity = false,
    ): void {
        if (($preservedMediaId = self::preservedMediaId($referenceKey)) !== null) {
            if ((int) ($this->attachment($owner, $role)?->media_id ?? 0) === $preservedMediaId) {
                return;
            }

            throw ValidationException::withMessages([
                $validationField ?? ($role === MediaAttachmentRole::Cover
                    ? 'cover_media_reference_key'
                    : 'primary_image_media_reference_key') => __('admin.validation.media_reference_key'),
            ]);
        }

        if (filled($referenceKey)) {
            $field = $validationField ?? ($role === MediaAttachmentRole::Cover
                ? 'cover_media_reference_key'
                : 'primary_image_media_reference_key');
            $media = $this->selectableReplacement($referenceKey, $field, $owner, $role);

            if ($unsafeFingerprint !== null) {
                app(LegacyOwnerMediaRepairer::class)->replace($owner, $role, $referenceKey, $unsafeFingerprint, $actor);

                return;
            }
            try {
                $this->identityResolver->resolve($owner, $role);
            } catch (UnsafeLegacyOwnerMediaException) {
                throw ValidationException::withMessages([
                    $field => __('admin.validation.media_reference_key'),
                ]);
            } catch (UnresolvableMediaIdentityException) {
                // A selected replacement resolves a rowless legacy owner identity.
            }

            try {
                if ($enforceExpectedIdentity) {
                    $this->attachmentManager->attachIfUnchanged(
                        $owner,
                        $media,
                        $role,
                        $actor,
                        $expectedMediaId,
                        $expectedLegacyPath,
                    );
                } else {
                    $this->attachmentManager->attach($owner, $media, $role, $actor);
                }
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    $field => $exception->getMessage(),
                ]);
            }

            return;
        }

        if ($unsafeFingerprint !== null) {
            return;
        }

        if ($this->attachment($owner, $role) instanceof MediaAttachment) {
            if ($enforceExpectedIdentity) {
                $this->attachmentManager->detachIfUnchanged(
                    $owner,
                    $role,
                    $actor,
                    $expectedMediaId,
                    $expectedLegacyPath,
                );
            } else {
                $this->attachmentManager->detach($owner, $role, $actor);
            }
        }
    }

    public function detachDirectIfUnchanged(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        User $actor,
        ?int $expectedMediaId,
        ?string $expectedLegacyPath,
        ?string $validationField = null,
    ): void {
        $this->attachmentManager->detachIfUnchanged(
            $owner,
            $role,
            $actor,
            $expectedMediaId,
            $expectedLegacyPath,
        );
    }

    public function diagnostic(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?LegacyOwnerMediaDiagnostic
    {
        try {
            $this->identityResolver->resolve($owner, $role);
        } catch (UnsafeLegacyOwnerMediaException $exception) {
            return $exception->diagnostic;
        } catch (UnresolvableMediaIdentityException) {
            return null;
        }

        return null;
    }

    public function detachUnsafe(ContentGroup|ContentItem $owner, MediaAttachmentRole $role, string $fingerprint, User $actor): void
    {
        app(LegacyOwnerMediaRepairer::class)->detach($owner, $role, $fingerprint, $actor);
    }

    private function attachment(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?MediaAttachment
    {
        $relation = $role === MediaAttachmentRole::Cover
            ? $owner->coverMediaAttachment()
            : $owner->primaryImageMediaAttachment();

        return $relation->with('media')->first();
    }

    private function selectableReplacement(
        string $referenceKey,
        string $formField,
        ContentGroup|ContentItem|null $owner,
        MediaAttachmentRole $role,
    ): Media {
        $media = $this->mediaRecordScope->findByReferenceKey($referenceKey, $role->purpose());

        if (! $media instanceof Media) {
            throw ValidationException::withMessages([
                $formField => __('admin.validation.media_reference_key'),
            ]);
        }

        if ($owner !== null) {
            try {
                $current = $this->identityResolver->resolve($owner, $role)['media'];

                if ($current instanceof Media && $current->is($media)) {
                    return $media;
                }
            } catch (UnsafeLegacyOwnerMediaException|InvalidArgumentException) {
                // A reviewed repair may replace an unsafe current identity.
            }
        }

        $blockedReason = $this->inventoryDiagnostics->selectionBlockedReason($media);

        if ($blockedReason !== null) {
            throw ValidationException::withMessages([
                $formField => $blockedReason,
            ]);
        }

        return $media;
    }

    private function legacyColumn(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): string
    {
        return match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => 'cover_path',
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => 'image_path',
            default => throw new InvalidArgumentException('The attachment form role is incompatible with the owner.'),
        };
    }
}
