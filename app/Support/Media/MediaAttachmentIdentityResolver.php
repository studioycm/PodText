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
    public function __construct(
        private readonly MediaIdentityResolver $mediaIdentityResolver,
        private readonly MediaRecordScope $mediaRecordScope,
        private readonly LegacyOwnerMediaDiagnostics $diagnostics,
    ) {}

    /**
     * @return array{has_attachment: bool, media: Media|null, path: string|null}
     */
    public function resolve(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): array
    {
        [$relation, $legacyColumn] = $this->ownerContract($owner, $role);
        $legacyPath = is_string($owner->getAttribute($legacyColumn))
            && filled($owner->getAttribute($legacyColumn))
                ? (string) $owner->getAttribute($legacyColumn)
                : null;
        $attachment = $owner->relationLoaded($relation)
            ? $owner->getRelation($relation)
            : $owner->{$relation}()->with('media')->first();

        if ($attachment instanceof MediaAttachment) {
            $media = $attachment->relationLoaded('media')
                ? $attachment->getRelation('media')
                : $attachment->media()->first();

            if (! $media instanceof Media) {
                $diagnostic = $this->diagnosticFor($owner, $role, $attachment);
                if ($diagnostic !== null) {
                    throw new UnsafeLegacyOwnerMediaException(
                        $diagnostic,
                        'The media attachment references a missing media record.',
                    );
                }

                throw new InvalidArgumentException('The media attachment references a missing media record.');
            }

            if (! $this->mediaRecordScope->allows($media, $role->purpose())) {
                throw new UnsafeLegacyOwnerMediaException(
                    $this->diagnosticFor($owner, $role, $attachment) ?? throw new InvalidArgumentException('The attached media record is unavailable.'),
                    'The attached media record is unavailable for this attachment role.',
                );
            }

            if ($legacyPath !== $media->path) {
                throw new UnsafeLegacyOwnerMediaException(
                    $this->diagnosticFor($owner, $role, $attachment) ?? throw new InvalidArgumentException('The media attachment and legacy path disagree.'),
                    'The media attachment and legacy path disagree.',
                );
            }

            return [
                'has_attachment' => true,
                'media' => $media,
                'path' => $media->path,
            ];
        }

        $rawRelation = $owner instanceof ContentGroup ? 'legacyCoverMediaRows' : 'legacyPrimaryImageMediaRows';
        $media = null;
        if ($owner->relationLoaded($rawRelation)) {
            $rows = $owner->getRelation($rawRelation);
            $diagnostic = $this->diagnostics->make($owner, $role, null, $rows);
            if ($diagnostic !== null) {
                throw new UnsafeLegacyOwnerMediaException($diagnostic);
            }

            $media = $rows->count() === 1 ? $rows->sole() : null;
            if ($media instanceof Media) {
                return ['has_attachment' => false, 'media' => $media, 'path' => $media->path];
            }
        } else {
            try {
                $media = $this->mediaIdentityResolver->resolve(null, $legacyPath, $role->purpose());
            } catch (UnsafeLegacyOwnerMediaException $exception) {
                $diagnostic = $this->diagnosticFor($owner, $role, null);
                if ($diagnostic !== null) {
                    throw new UnsafeLegacyOwnerMediaException($diagnostic, $exception->getMessage());
                }

                throw new InvalidArgumentException('The legacy media path belongs to an unsafe media row.');
            }
        }

        return [
            'has_attachment' => false,
            'media' => $media,
            'path' => $media?->path
                ?? ($legacyPath !== null
                    ? $this->mediaIdentityResolver->validatedLegacyPath($legacyPath, $role->purpose())
                    : null),
        ];
    }

    /** @param iterable<int, ContentGroup|ContentItem> $owners */
    public function prime(iterable $owners, MediaAttachmentRole $role): void
    {
        $owners = new Collection(collect($owners)->all());

        if ($owners->isEmpty()) {
            return;
        }

        [$relation] = $this->ownerContract($owners->first(), $role);
        $owners->loadMissing("{$relation}.media");
        $owners->loadMissing($owners->first() instanceof ContentGroup ? 'legacyCoverMediaRows' : 'legacyPrimaryImageMediaRows');
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

    /**
     * @return array{string, string}
     */
    private function ownerContract(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): array
    {
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

    private function diagnosticFor(ContentGroup|ContentItem $owner, MediaAttachmentRole $role, ?MediaAttachment $attachment): ?LegacyOwnerMediaDiagnostic
    {
        $rawRelation = $owner instanceof ContentGroup ? 'legacyCoverMediaRows' : 'legacyPrimaryImageMediaRows';
        $rows = $owner->relationLoaded($rawRelation) ? $owner->getRelation($rawRelation) : $owner->{$rawRelation}()->get();

        return $this->diagnostics->make($owner, $role, $attachment, $rows);
    }
}
