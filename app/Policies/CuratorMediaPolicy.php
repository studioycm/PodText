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
use Illuminate\Support\Facades\Gate;

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

    /**
     * Flipping a file to public audience carries the same weight as the SVG
     * trust mark: super-admin only, consequence-dialogued, recorded and
     * revocable. A non-public disk needs disk correction or relocation first.
     */
    public function makePublic(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user) || ! Gate::forUser($user)->allows('super-admin')) {
            return Response::deny(__('admin.media_issue_review.audience.super_admin_only'));
        }

        if ($media->audience_made_public_at !== null) {
            return Response::allow();
        }

        if ($media->disk !== 'public') {
            return Response::deny(__('admin.media_issue_review.audience.disk_first'));
        }

        if ($media->visibility === 'public') {
            return Response::deny(__('admin.media_issue_review.audience.not_applicable'));
        }

        return Response::allow();
    }

    /**
     * A named disk that does not exist is an editable record defect: a
     * super-admin may point the record at an existing configured disk, never
     * a free-text value. Configured disks are not editable this way.
     */
    public function correctDisk(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user) || ! Gate::forUser($user)->allows('super-admin')) {
            return Response::deny(__('admin.media_issue_review.audience.super_admin_only'));
        }

        if (array_key_exists((string) $media->disk, config('filesystems.disks', []))) {
            return Response::deny(__('admin.media_issue_review.storage_disk.not_applicable'));
        }

        return Response::allow();
    }

    /**
     * Minting a missing portable reference key is a legitimate repair; a
     * filled key — even a malformed one — stays immutable, so those rows are
     * honestly blocked instead of silently rewritten.
     */
    public function mintReferenceKey(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (filled($media->reference_key)) {
            return Response::deny(__('admin.media_issue_review.portable_identity.not_mintable'));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
    }

    /**
     * The destructive missing-file resolution: only rows whose file is
     * actually gone qualify, settings-path references still block, and the
     * action itself detaches attachment references before the delete.
     */
    public function detachAndDelete(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (! in_array(
            MediaDiagnosticReason::MissingFile->value,
            app(MediaInventoryDiagnostics::class)->reasons($media),
            true,
        )) {
            return Response::deny(__('admin.media_issue_review.missing_file.not_applicable'));
        }

        $settingsReferences = app(MediaReferenceFinder::class)->nonAttachmentReferencesForMedia($media);

        if ($settingsReferences !== []) {
            return Response::deny(__('admin.media_library.op_blocked_in_use', [
                'surfaces' => implode(', ', $settingsReferences),
            ]));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
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

        $settingsReferences = app(MediaReferenceFinder::class)->nonAttachmentReferencesForMedia($media);

        if ($settingsReferences !== []) {
            return Response::deny(__('admin.media_library.op_blocked_in_use', [
                'surfaces' => implode(', ', $settingsReferences),
            ]));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
    }

    /**
     * Swap may reach attachment-referenced rows: replacing bytes keeps the
     * media identity, so attachments stay truthful — and the missing-file
     * cohort this lift exists for is usually still referenced. Settings-path
     * references and duplicate identities keep blocking.
     */
    public function swap(User $user, Media $media): Response
    {
        if (! $this->isAdmin($user)) {
            return Response::deny();
        }

        if (! app(MediaRecordScope::class)->allows($media)) {
            return Response::deny(__('admin.media_library.op_blocked_unmanaged'));
        }

        $settingsReferences = app(MediaReferenceFinder::class)->nonAttachmentReferencesForMedia($media);

        if ($settingsReferences !== []) {
            return Response::deny(__('admin.media_library.op_blocked_in_use', [
                'surfaces' => implode(', ', $settingsReferences),
            ]));
        }

        if (! app(MediaRecordScope::class)->hasUniqueStorageIdentity($media)) {
            return Response::deny(__('admin.media_library.op_blocked_duplicate_identity'));
        }

        return Response::allow();
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
