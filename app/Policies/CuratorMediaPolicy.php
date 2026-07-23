<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\MediaReferenceFinder;
use Illuminate\Auth\Access\Response;

class CuratorMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Media $media): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function bulkUpload(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Media $media): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Media $media): Response
    {
        if (! $this->canUse($user, $media)) {
            return Response::deny();
        }

        $references = app(MediaReferenceFinder::class)->referencesForMedia($media);

        if ($references === []) {
            return Response::allow();
        }

        return Response::deny(__('admin.notifications.media_delete_blocked_body', [
            'surfaces' => implode(', ', $references),
        ]));
    }

    public function deleteAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function download(User $user, Media $media): bool
    {
        return $this->isAdmin($user);
    }

    public function rename(User $user, Media $media): bool
    {
        return $this->canMutateFile($user, $media);
    }

    public function swap(User $user, Media $media): bool
    {
        return $this->canMutateFile($user, $media);
    }

    public function select(User $user, Media $media): bool
    {
        return $this->isAdmin($user)
            && app(MediaRecordScope::class)->hasPortableReferenceKey($media);
    }

    public function attach(User $user, Media $media): bool
    {
        return $this->select($user, $media);
    }

    public function detach(User $user, Media $media): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * This is deliberately not a substitute for view/select/download.  It is
     * the one narrow authority used by the reviewed legacy-transition fence,
     * before a row is allowed back into the normal Curator scope.
     */
    public function transitionLegacy(User $user, Media $media): bool
    {
        return $this->isAdmin($user)
            && blank($media->reference_key);
    }

    /** Narrow repair authority for an excluded row still held by an owner. */
    public function repairLegacyOwner(User $user, Media $media): bool
    {
        return $this->isAdmin($user) && ! app(MediaRecordScope::class)->allows($media);
    }

    private function canMutateFile(User $user, Media $media): bool
    {
        return $this->canUse($user, $media)
            && app(MediaReferenceFinder::class)->referencesForMedia($media) === []
            && ! Media::query()
                ->where('disk', $media->disk)
                ->where('path', $media->path)
                ->whereKeyNot($media->getKey())
                ->exists();
    }

    private function canUse(User $user, Media $media): bool
    {
        return $this->isAdmin($user)
            && app(MediaRecordScope::class)->allows($media);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin);
    }
}
