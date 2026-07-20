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
        return $this->identityResolver->referenceKey($owner, $role);
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

        $referenceKey = is_string($referenceKey) && filled($referenceKey)
            ? mb_strtoupper(trim($referenceKey))
            : null;
        $legacyColumn = $owner instanceof ContentGroup || $owner instanceof ContentItem
            ? $this->legacyColumn($owner, $role)
            : ($role === MediaAttachmentRole::Cover ? 'cover_path' : 'image_path');

        if ($owner !== null) {
            $this->identityResolver->resolve($owner, $role);
        }

        if ($referenceKey === null) {
            if ($owner !== null) {
                $data[$legacyColumn] = $owner->getAttribute($legacyColumn);
            } else {
                $data[$legacyColumn] = null;
            }

            return [$data, null];
        }

        $media = $this->mediaRecordScope->findByReferenceKey($referenceKey, $role->purpose());

        if (! $media instanceof Media) {
            throw ValidationException::withMessages([
                $formField => __('admin.validation.media_reference_key'),
            ]);
        }

        $data[$legacyColumn] = $media->path;

        return [$data, $media->reference_key];
    }

    public function persist(
        ContentGroup|ContentItem $owner,
        ?string $referenceKey,
        MediaAttachmentRole $role,
        User $actor,
    ): void {
        if (filled($referenceKey)) {
            $this->attachmentManager->attachByReferenceKey($owner, $referenceKey, $role, $actor);

            return;
        }

        if ($this->attachment($owner, $role) instanceof MediaAttachment) {
            $this->attachmentManager->detach($owner, $role, $actor);
        }
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
