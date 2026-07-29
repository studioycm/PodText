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
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function pickerIdentity(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): int|string|null
    {
        try {
            $identity = $this->identityResolver->resolve($owner, $role);
        } catch (InvalidArgumentException) {
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
     * @return array{array<string, mixed>, string|null}
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
                return [$data, self::preservedMediaIdentity($preservedMediaId)];
            }

            throw ValidationException::withMessages([
                $formField => __('admin.validation.media_reference_key'),
            ]);
        }

        $referenceKey = is_string($referenceKey) && filled($referenceKey)
            ? mb_strtoupper(trim($referenceKey))
            : null;

        if ($referenceKey === null) {
            return [$data, null];
        }

        $media = $this->selectableReplacement($referenceKey, $formField, $owner, $role);

        return [$data, $media->reference_key];
    }

    public function persist(
        ContentGroup|ContentItem $owner,
        ?string $referenceKey,
        MediaAttachmentRole $role,
        User $actor,
        ?string $validationField = null,
        ?int $expectedMediaId = null,
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

            try {
                if ($enforceExpectedIdentity) {
                    $this->attachmentManager->attachIfUnchanged(
                        $owner,
                        $media,
                        $role,
                        $actor,
                        $expectedMediaId,
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

        if ($this->attachment($owner, $role) instanceof MediaAttachment) {
            if ($enforceExpectedIdentity) {
                $this->attachmentManager->detachIfUnchanged(
                    $owner,
                    $role,
                    $actor,
                    $expectedMediaId,
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
        ?string $validationField = null,
    ): void {
        $this->attachmentManager->detachIfUnchanged(
            $owner,
            $role,
            $actor,
            $expectedMediaId,
        );
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
            } catch (InvalidArgumentException) {
                // A replacement may supersede a broken current identity.
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
}
