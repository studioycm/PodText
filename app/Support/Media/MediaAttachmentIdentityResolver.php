<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class MediaAttachmentIdentityResolver
{
    /**
     * @return array{has_attachment: bool, media: Media|null, path: string|null}
     */
    public function resolve(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): array
    {
        $relation = $this->ownerRelation($owner, $role);
        $attachment = $owner->relationLoaded($relation)
            ? $owner->getRelation($relation)
            : $owner->{$relation}()->with('media')->first();

        if ($attachment instanceof MediaAttachment) {
            $media = $attachment->relationLoaded('media')
                ? $attachment->getRelation('media')
                : $attachment->media()->first();

            if (! $media instanceof Media) {
                throw new InvalidArgumentException('The media attachment references a missing media record.');
            }

            return [
                'has_attachment' => true,
                'media' => $media,
                'path' => $media->path,
            ];
        }

        return [
            'has_attachment' => false,
            'media' => null,
            'path' => null,
        ];
    }

    /** @param iterable<int, ContentGroup|ContentItem> $owners */
    public function prime(iterable $owners, MediaAttachmentRole $role): void
    {
        $owners = new Collection(collect($owners)->all());

        if ($owners->isEmpty()) {
            return;
        }

        $relation = $this->ownerRelation($owners->first(), $role);
        $owners->loadMissing("{$relation}.media");
    }

    public function referenceKey(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?string
    {
        return $this->resolve($owner, $role)['media']?->reference_key;
    }

    public function portableReferenceKey(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): ?string
    {
        $identity = $this->resolve($owner, $role);

        if ($identity['media'] instanceof Media && filled($identity['media']->reference_key)) {
            return (string) $identity['media']->reference_key;
        }

        if ($identity['media'] instanceof Media || filled($identity['path'])) {
            throw new InvalidArgumentException('Portable export requires a registered media reference key.');
        }

        return null;
    }

    private function ownerRelation(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): string
    {
        return match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => 'coverMediaAttachment',
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => 'primaryImageMediaAttachment',
            default => throw new InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };
    }
}
