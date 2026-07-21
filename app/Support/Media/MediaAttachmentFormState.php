<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MediaAttachmentFormState
{
    public function __construct(
        private readonly MediaAttachmentIdentityResolver $identityResolver,
        private readonly MediaRecordScope $mediaRecordScope,
        private readonly MediaAttachmentManager $attachmentManager,
    ) {}

    public function referenceKey(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?string
    {
        try {
            return $this->identityResolver->referenceKey($owner, $role);
        } catch (UnsafeLegacyOwnerMediaException) {
            return null;
        }
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

        $referenceKey = is_string($referenceKey) && filled($referenceKey)
            ? mb_strtoupper(trim($referenceKey))
            : null;
        $legacyColumn = $owner instanceof ContentGroup || $owner instanceof ContentItem
            ? $this->legacyColumn($owner, $role)
            : ($role === MediaAttachmentRole::Cover ? 'cover_path' : 'image_path');

        if ($owner !== null) {
            try {
                $this->identityResolver->resolve($owner, $role);
            } catch (UnsafeLegacyOwnerMediaException) {
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

        $media = $this->mediaRecordScope->findByReferenceKey($referenceKey, $role->purpose());

        if (! $media instanceof Media) {
            throw ValidationException::withMessages([
                $formField => __('admin.validation.media_reference_key'),
            ]);
        }

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
    ): void {
        if (filled($referenceKey)) {
            if ($unsafeFingerprint !== null) {
                app(LegacyOwnerMediaRepairer::class)->replace($owner, $role, $referenceKey, $unsafeFingerprint, $actor);

                return;
            }
            try {
                $this->identityResolver->resolve($owner, $role);
            } catch (UnsafeLegacyOwnerMediaException) {
                $field = $role === MediaAttachmentRole::Cover
                    ? 'cover_media_reference_key'
                    : 'primary_image_media_reference_key';

                throw ValidationException::withMessages([
                    $field => __('admin.validation.media_reference_key'),
                ]);
            }
            $this->attachmentManager->attachByReferenceKey($owner, $referenceKey, $role, $actor);

            return;
        }

        if ($unsafeFingerprint !== null) {
            return;
        }

        if ($this->attachment($owner, $role) instanceof MediaAttachment) {
            $this->attachmentManager->detach($owner, $role, $actor);
        }
    }

    public function diagnostic(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?LegacyOwnerMediaDiagnostic
    {
        try {
            $this->identityResolver->resolve($owner, $role);
        } catch (UnsafeLegacyOwnerMediaException $exception) {
            return $exception->diagnostic;
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

    private function legacyColumn(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): string
    {
        return match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => 'cover_path',
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => 'image_path',
            default => throw new \InvalidArgumentException('The attachment form role is incompatible with the owner.'),
        };
    }
}
