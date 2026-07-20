<?php

namespace App\Observers;

use App\Models\Media;
use App\Support\Media\MediaMutationLease;
use App\Support\Media\MediaReferenceFinder;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use LogicException;

class CuratorMediaObserver
{
    /** @var array<int, string> */
    private const STORAGE_ATTRIBUTES = [
        'disk',
        'directory',
        'visibility',
        'name',
        'path',
        'width',
        'height',
        'size',
        'type',
        'ext',
        'exif',
        'curations',
        'file',
    ];

    public function updating(Media $media): void
    {
        if (
            $media->isDirty(self::STORAGE_ATTRIBUTES)
            && ! app(MediaMutationLease::class)->allows($media)
        ) {
            throw new LogicException('Media storage identity may only be changed by the mutation coordinator.');
        }
    }

    public function deleting(Media $media): void
    {
        if (! app(MediaMutationLease::class)->allows($media)) {
            throw new LogicException('Media records may only be deleted by the mutation coordinator.');
        }

        $references = app(MediaReferenceFinder::class)->referencesForMedia($media);

        if ($references === []) {
            return;
        }

        $message = __('admin.notifications.media_delete_blocked_body', [
            'surfaces' => implode(', ', $references),
        ]);

        Notification::make()
            ->warning()
            ->title(__('admin.notifications.media_delete_blocked'))
            ->body($message)
            ->send();

        throw ValidationException::withMessages([
            'media' => $message,
        ]);
    }
}
