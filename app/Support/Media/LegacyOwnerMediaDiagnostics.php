<?php

namespace App\Support\Media;

use App\Enums\LegacyOwnerMediaDiagnosticCode;
use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use InvalidArgumentException;

/** Single DB-metadata-only source for unsafe owner diagnostics and fingerprints. */
class LegacyOwnerMediaDiagnostics
{
    public function __construct(private readonly MediaRecordScope $scope) {}

    /**
     * @param  iterable<int, Media>  $rows
     */
    public function make(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        ?MediaAttachment $attachment,
        iterable $rows,
    ): ?LegacyOwnerMediaDiagnostic {
        $this->assertRole($owner, $role);
        $rows = collect($rows)->sortBy('id')->values();
        $column = $owner instanceof ContentGroup ? 'cover_path' : 'image_path';
        $attached = $attachment?->relationLoaded('media') ? $attachment->getRelation('media') : null;
        $code = match (true) {
            $attachment instanceof MediaAttachment && ! $attached instanceof Media => LegacyOwnerMediaDiagnosticCode::MissingAttachmentMedia,
            $attachment instanceof MediaAttachment && $attached instanceof Media && (string) $owner->{$column} !== (string) $attached->path => LegacyOwnerMediaDiagnosticCode::AttachmentPathMismatch,
            $attachment instanceof MediaAttachment && $attached instanceof Media && ! $this->scope->allows($attached, $role->purpose()) => LegacyOwnerMediaDiagnosticCode::DisallowedAttachment,
            $rows->count() > 1 => LegacyOwnerMediaDiagnosticCode::DuplicateLegacyRows,
            $rows->count() === 1 && ! $this->scope->allows($rows->sole(), $role->purpose()) => LegacyOwnerMediaDiagnosticCode::DisallowedLegacyPath,
            default => null,
        };
        if ($code === null) {
            return null;
        }

        return new LegacyOwnerMediaDiagnostic(
            code: $code,
            ownerType: $owner instanceof ContentGroup ? 'content_group' : 'content_item',
            ownerId: (int) $owner->getKey(),
            role: $role,
            fingerprint: hash('sha256', implode("\0", [
                get_class($owner),
                $owner->getKey(),
                $role->value,
                (string) $owner->{$column},
                $attachment?->getKey() ?? '',
                $attached instanceof Media ? $attached->getKey() : '',
                implode(',', $rows->pluck('id')->all()),
            ])),
        );
    }

    public function assertRole(ContentGroup|ContentItem $owner, MediaAttachmentRole $role): void
    {
        if (($owner instanceof ContentGroup && $role !== MediaAttachmentRole::Cover) || ($owner instanceof ContentItem && $role !== MediaAttachmentRole::PrimaryImage)) {
            throw new InvalidArgumentException('The media attachment role is incompatible with this owner.');
        }
    }
}
