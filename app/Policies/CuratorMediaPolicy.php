<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Media\MediaReferenceFinder;
use Awcodes\Curator\Models\Media;
use Illuminate\Auth\Access\Response;

class CuratorMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Media $media): bool
    {
        return true;
    }

    public function delete(User $user, Media $media): Response
    {
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
        return true;
    }
}
