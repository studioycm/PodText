<?php

namespace App\Support\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Super-admin record corrections with trust-mark weight: each write runs
 * inside the mutation lease (the observer guards storage attributes), records
 * the actor and timestamp where the decision demands it, and stays revocable.
 */
class MediaRecordCorrections
{
    public function __construct(
        private readonly MediaMutationLease $lease,
    ) {}

    public function makePublic(Media $media, User $actor): Media
    {
        Gate::forUser($actor)->authorize('makePublic', $media);

        $this->lease->run($media, function () use ($media, $actor): void {
            $media->forceFill([
                'visibility' => 'public',
                'audience_made_public_at' => now(),
                'audience_made_public_by_user_id' => $actor->getKey(),
            ])->save();
        });

        $this->syncFileVisibility($media, 'public');

        return $media->refresh();
    }

    public function revokePublicAudience(Media $media, User $actor): Media
    {
        Gate::forUser($actor)->authorize('makePublic', $media);

        $this->lease->run($media, function () use ($media): void {
            $media->forceFill([
                'visibility' => 'private',
                'audience_made_public_at' => null,
                'audience_made_public_by_user_id' => null,
            ])->save();
        });

        $this->syncFileVisibility($media, 'private');

        return $media->refresh();
    }

    public function correctDisk(Media $media, User $actor, string $disk): Media
    {
        Gate::forUser($actor)->authorize('correctDisk', $media);

        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            throw new InvalidArgumentException('The corrected disk must be an existing configured disk.');
        }

        $this->lease->run($media, function () use ($media, $disk): void {
            $media->forceFill(['disk' => $disk])->save();
        });

        return $media->refresh();
    }

    private function syncFileVisibility(Media $media, string $visibility): void
    {
        if ($media->disk !== 'public' || ! Storage::disk('public')->exists((string) $media->path)) {
            return;
        }

        try {
            Storage::disk('public')->setVisibility((string) $media->path, $visibility);
        } catch (\Throwable) {
            // Delivery is governed by the record; a driver that cannot set
            // per-file visibility does not fail the correction.
        }
    }
}
