<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

/** Repairs only an owner whose current identity is explicitly unsafe; it never touches old bytes. */
class LegacyOwnerMediaRepairer
{
    public function __construct(
        private readonly MediaRecordScope $scope,
        private readonly MediaMutationFence $fence,
        private readonly LegacyOwnerMediaDiagnostics $diagnostics,
        private readonly MediaInventoryDiagnostics $inventoryDiagnostics,
    ) {}

    public function replace(ContentGroup|ContentItem $owner, MediaAttachmentRole $role, string $referenceKey, string $fingerprint, User $actor): void
    {
        $this->repair($owner, $role, $fingerprint, $actor, $referenceKey);
    }

    public function detach(ContentGroup|ContentItem $owner, MediaAttachmentRole $role, string $fingerprint, User $actor): void
    {
        $this->repair($owner, $role, $fingerprint, $actor, null);
    }

    private function repair(ContentGroup|ContentItem $owner, MediaAttachmentRole $role, string $fingerprint, User $actor, ?string $referenceKey): void
    {
        $this->diagnostics->assertRole($owner, $role);
        Gate::forUser($actor)->authorize('create', new Media);

        DB::transaction(function () use ($owner, $role, $fingerprint, $actor, $referenceKey): void {
            $locked = $owner instanceof ContentGroup
                ? ContentGroup::query()->lockForUpdate()->findOrFail($owner->getKey())
                : ContentItem::query()->lockForUpdate()->findOrFail($owner->getKey());
            $column = $locked instanceof ContentGroup ? 'cover_path' : 'image_path';
            $originalPath = $locked->{$column};
            $attachment = MediaAttachment::query()->where('attachable_type', $locked instanceof ContentGroup ? 'content_group' : 'content_item')->where('attachable_id', $locked->getKey())->where('role', $role->value)->lockForUpdate()->first();
            $pathRows = filled($locked->{$column}) ? Media::query()->where('path', $locked->{$column})->orderBy('id')->lockForUpdate()->get() : collect();
            $evidenceIds = $pathRows->pluck('id')->push($attachment?->media_id)->filter()->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
            $evidenceRows = $evidenceIds->isEmpty() ? collect() : Media::query()->whereIn('id', $evidenceIds)->orderBy('id')->lockForUpdate()->get();

            if ($attachment instanceof MediaAttachment) {
                $attachment->setRelation('media', $evidenceRows->firstWhere('id', (int) $attachment->media_id));
            }

            $old = $pathRows->count() === 1 ? $pathRows->sole() : null;
            $diagnostic = $this->diagnostics->make($locked, $role, $attachment, $pathRows);
            if (! $diagnostic instanceof LegacyOwnerMediaDiagnostic || ! hash_equals($diagnostic->fingerprint, $fingerprint)) {
                throw new OwnerImageChangedException;
            }
            if ($evidenceRows->isNotEmpty()) {
                foreach ($evidenceRows as $evidenceRow) {
                    Gate::forUser($actor)->authorize(
                        $this->scope->allows($evidenceRow, $role->purpose()) ? 'detach' : 'repairLegacyOwner',
                        $evidenceRow,
                    );
                }

                $this->fence->assertAttachmentAvailable($evidenceRows->pluck('id')->map(fn (mixed $id): int => (int) $id));
                $this->fence->assertNoUnfinishedMutation($evidenceRows->pluck('id'));
            }
            $target = null;
            if ($referenceKey !== null) {
                $target = $this->scope->findByReferenceKey(mb_strtoupper(trim($referenceKey)), $role->purpose());
                if (! $target instanceof Media) {
                    throw new RuntimeException('The requested replacement media is unavailable.');
                }
                $target = Media::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
                if (
                    ! hash_equals(mb_strtoupper(trim($referenceKey)), mb_strtoupper((string) $target->reference_key))
                    || $this->inventoryDiagnostics->selectionBlockedReason($target) !== null
                ) {
                    throw new RuntimeException('The requested replacement media changed before repair.');
                }
                Gate::forUser($actor)->authorize('select', $target);
                Gate::forUser($actor)->authorize('attach', $target);
                $this->fence->assertAttachmentAvailable([$target->getKey()]);
                $this->fence->assertNoUnfinishedMutation([$target->getKey()]);
            }
            $locked->forceFill([$column => $target?->path])->save();
            if ($attachment instanceof MediaAttachment) {
                $attachment->delete();
            }
            if ($target instanceof Media) {
                MediaAttachment::query()->create(['media_id' => $target->getKey(), 'attachable_type' => $locked instanceof ContentGroup ? 'content_group' : 'content_item', 'attachable_id' => $locked->getKey(), 'role' => $role->value, 'position' => 0]);
            }
            MediaMutationOperation::query()->create([
                'operation_key' => (string) Str::ulid(), 'media_id' => $old?->getKey(), 'media_id_snapshot' => $old?->getKey(), 'user_id' => $actor->getKey(), 'media_reference_key' => $target?->reference_key,
                'operation' => MediaMutationOperationType::LegacyOwnerRepair, 'status' => MediaMutationStatus::Completed, 'purpose' => $role->purpose()->value,
                'idempotency_key' => hash('sha256', "legacy-owner-repair\0{$diagnostic->fingerprint}\0".($target?->reference_key ?? 'detach')),
                'source_disk' => $old?->disk, 'source_path' => $originalPath, 'destination_disk' => $target?->disk, 'destination_path' => $target?->path,
                'context' => ['owner_type' => $diagnostic->ownerType, 'owner_id' => $diagnostic->ownerId, 'role' => $role->value, 'diagnostic_code' => $diagnostic->code->value, 'fingerprint' => $diagnostic->fingerprint, 'evidence_media_ids' => $evidenceRows->pluck('id')->sort()->values()->all(), 'database_only' => true],
                'attempts' => 1, 'started_at' => now(), 'committed_at' => now(), 'completed_at' => now(), 'cleanup_completed_at' => now(),
            ]);
        });
    }
}
