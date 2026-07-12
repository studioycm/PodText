<?php

namespace App\Observers;

use App\Support\Media\MediaReferenceFinder;
use Awcodes\Curator\Models\Media;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class CuratorMediaObserver
{
    public function deleting(Media $media): void
    {
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
