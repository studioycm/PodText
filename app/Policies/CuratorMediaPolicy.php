<?php

namespace App\Policies;

use App\Enums\MediaDiagnosticReason;
use App\Enums\UserRole;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaInventoryDiagnostics;
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
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (! app(MediaRecordScope::class)->allows($media)) {
            return Response::deny(__('admin.media_library.op_blocked_unmanaged'));
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

    /**
     * Managed relocation moves an unmanaged/root file into a managed purpose
     * root, rewriting its legacy path references in the same journaled
     * operation. Managed rows have nothing to relocate.
     */
    public function relocate(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (app(MediaRecordScope::class)->allows($media)) {
            return Response::deny(__('admin.media_library.op_blocked_already_managed'));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
    }

    /**
     * Marking a file as admin-trusted (or revoking that mark) settles
     * validator-based display blocks; it never touches bytes or paths.
     */
    public function trust(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if ($media->trusted_at !== null) {
            return Response::allow();
        }

        if (! in_array(
            MediaDiagnosticReason::UnsanitizedSvg->value,
            app(MediaInventoryDiagnostics::class)->reasons($media),
            true,
        )) {
            return Response::deny(__('admin.media_issue_review.trust.not_applicable'));
        }

        return Response::allow();
    }

    public function download(User $user, Media $media): bool
    {
        return $this->isAdmin($user);
    }

    public function rename(User $user, Media $media): Response
    {
        return $this->mutateFileResponse($user, $media);
    }

    public function repair(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (! in_array(
            MediaDiagnosticReason::UnsanitizedSvg->value,
            app(MediaInventoryDiagnostics::class)->reasons($media),
            true,
        )) {
            return Response::deny(__('admin.media_issue_review.sanitize.not_applicable'));
        }

        $references = app(MediaReferenceFinder::class)->referencesForMedia($media);

        if ($references !== []) {
            return Response::deny(__('admin.media_library.op_blocked_in_use', [
                'surfaces' => implode(', ', $references),
            ]));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
    }

    public function swap(User $user, Media $media): Response
    {
        return $this->mutateFileResponse($user, $media);
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

    private function mutateFileResponse(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (! app(MediaRecordScope::class)->allows($media)) {
            return Response::deny(__('admin.media_library.op_blocked_unmanaged'));
        }

        $references = app(MediaReferenceFinder::class)->referencesForMedia($media);

        if ($references !== []) {
            return Response::deny(__('admin.media_library.op_blocked_in_use', [
                'surfaces' => implode(', ', $references),
            ]));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin);
    }
}
