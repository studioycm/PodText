<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class MediaAttachmentManager
{
    public function __construct(
        private readonly MediaRecordScope $mediaRecordScope,
        private readonly MediaMutationFence $mutationFence,
        private readonly MediaInventoryDiagnostics $inventoryDiagnostics,
    ) {}

    public function attach(
        ContentGroup|ContentItem $owner,
        Media $media,
        MediaAttachmentRole $role,
        User $actor,
    ): MediaAttachment {
        return $this->attachWithExpectedIdentity($owner, $media, $role, $actor);
    }

    public function attachIfUnchanged(
        ContentGroup|ContentItem $owner,
        Media $media,
        MediaAttachmentRole $role,
        User $actor,
        ?int $expectedMediaId,
    ): MediaAttachment {
        return $this->attachWithExpectedIdentity(
            $owner,
            $media,
            $role,
            $actor,
            enforceExpectedIdentity: true,
            expectedMediaId: $expectedMediaId,
        );
    }

    private function attachWithExpectedIdentity(
        ContentGroup|ContentItem $owner,
        Media $media,
        MediaAttachmentRole $role,
        User $actor,
        bool $enforceExpectedIdentity = false,
        ?int $expectedMediaId = null,
    ): MediaAttachment {
        $alias = $this->ownerAlias($owner, $role);
        $targetMediaId = (int) $media->getKey();

        return DB::transaction(function () use (
            $owner,
            $targetMediaId,
            $role,
            $actor,
            $alias,
            $enforceExpectedIdentity,
            $expectedMediaId,
        ): MediaAttachment {
            $lockedOwner = $owner->newQuery()->lockForUpdate()->findOrFail($owner->getKey());
            $existing = MediaAttachment::query()
                ->where('attachable_type', $alias)
                ->where('attachable_id', $lockedOwner->getKey())
                ->where('role', $role->value)
                ->lockForUpdate()
                ->first();
            $mediaIds = collect([$targetMediaId, $existing?->media_id])
                ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $lockedMedia = $this->mediaRecordScope
                ->lockInventoryForUpdateByIds($mediaIds)
                ->keyBy(fn (Media $record): int => (int) $record->getKey());
            $this->mutationFence->assertAttachmentAvailable($mediaIds);
            $trustedMedia = $lockedMedia->get($targetMediaId)
                ?? throw new RuntimeException('The target media record is unavailable.');
            Gate::forUser($actor)->authorize('attach', $trustedMedia);

            if (! $existing instanceof MediaAttachment || (int) $existing->media_id !== $targetMediaId) {
                $blockedReason = $this->inventoryDiagnostics->freshSelectionBlockedReason($trustedMedia);

                if ($blockedReason !== null) {
                    throw new InvalidArgumentException($blockedReason);
                }
            }

            if ($enforceExpectedIdentity) {
                $currentMediaId = $existing instanceof MediaAttachment ? (int) $existing->media_id : null;

                if ($currentMediaId !== $expectedMediaId) {
                    throw new OwnerImageChangedException;
                }
            }

            if ($existing instanceof MediaAttachment && (int) $existing->media_id !== (int) $trustedMedia->getKey()) {
                $current = $lockedMedia->get((int) $existing->media_id)
                    ?? throw new RuntimeException('The attached media record is unavailable.');
                Gate::forUser($actor)->authorize('detach', $current);
            }

            $attachment = MediaAttachment::query()->updateOrCreate(
                [
                    'attachable_type' => $alias,
                    'attachable_id' => $lockedOwner->getKey(),
                    'role' => $role->value,
                ],
                [
                    'media_id' => $trustedMedia->getKey(),
                    'media_asset_id' => $trustedMedia->providerBinding()
                        ->value('media_asset_id'),
                    'position' => 0,
                ],
            );

            return $attachment->load(['media', 'mediaAsset']);
        });
    }

    public function attachByReferenceKey(
        ContentGroup|ContentItem $owner,
        string $referenceKey,
        MediaAttachmentRole $role,
        User $actor,
    ): MediaAttachment {
        $media = $this->mediaRecordScope->findByReferenceKey($referenceKey, $role->purpose())
            ?? throw new InvalidArgumentException('The media reference key is unavailable for this attachment role.');

        return $this->attach($owner, $media, $role, $actor);
    }

    public function detach(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        User $actor,
    ): void {
        $this->detachWithExpectedIdentity($owner, $role, $actor);
    }

    public function detachIfUnchanged(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        User $actor,
        ?int $expectedMediaId,
    ): void {
        $this->detachWithExpectedIdentity(
            $owner,
            $role,
            $actor,
            enforceExpectedIdentity: true,
            expectedMediaId: $expectedMediaId,
        );
    }

    private function detachWithExpectedIdentity(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        User $actor,
        bool $enforceExpectedIdentity = false,
        ?int $expectedMediaId = null,
    ): void {
        $alias = $this->ownerAlias($owner, $role);
        Gate::forUser($actor)->authorize('viewAny', config('curator.model', Media::class));

        DB::transaction(function () use (
            $owner,
            $role,
            $actor,
            $alias,
            $enforceExpectedIdentity,
            $expectedMediaId,
        ): void {
            $lockedOwner = $owner->newQuery()->lockForUpdate()->findOrFail($owner->getKey());
            $attachment = MediaAttachment::query()
                ->where('attachable_type', $alias)
                ->where('attachable_id', $lockedOwner->getKey())
                ->where('role', $role->value)
                ->lockForUpdate()
                ->first();

            if ($enforceExpectedIdentity) {
                $currentMediaId = $attachment instanceof MediaAttachment ? (int) $attachment->media_id : null;

                if ($currentMediaId !== $expectedMediaId) {
                    throw new OwnerImageChangedException;
                }
            }

            if ($attachment instanceof MediaAttachment) {
                $media = $this->mediaRecordScope
                    ->inventoryQuery()
                    ->whereKey((int) $attachment->media_id)
                    ->lockForUpdate()
                    ->first();
                $this->mutationFence->assertAttachmentAvailable([(int) $attachment->media_id]);

                if ($media instanceof Media) {
                    Gate::forUser($actor)->authorize('detach', $media);
                }

                $attachment->delete();
            }
        });
    }

    private function ownerAlias(Model $owner, MediaAttachmentRole $role): string
    {
        return match (true) {
            $owner instanceof ContentGroup && $role === MediaAttachmentRole::Cover => 'content_group',
            $owner instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => 'content_item',
            default => throw new InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };
    }
}
