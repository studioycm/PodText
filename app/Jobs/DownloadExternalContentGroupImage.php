<?php

namespace App\Jobs;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaAttachmentManager;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class DownloadExternalContentGroupImage implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(
        public int $contentGroupId,
        public int $userId,
        public string $url,
        public bool $overwrite = false,
    ) {
        $this->onQueue('imports-exports');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('external-image-downloads')];
    }

    public function uniqueId(): string
    {
        return $this->contentGroupId.':'.hash('sha256', $this->url);
    }

    public function handle(
        MediaAcquisitionManager $acquisitions,
        MediaAttachmentManager $attachments,
    ): void {
        $user = User::query()->find($this->userId);
        $group = ContentGroup::query()->with('coverMediaAttachment.media')->find($this->contentGroupId);

        if (! $user instanceof User || ! $group instanceof ContentGroup) {
            return;
        }

        Gate::forUser($user)->authorize('create', config('curator.model', Media::class));
        $expectedMediaId = $group->coverMediaAttachment instanceof MediaAttachment
            ? (int) $group->coverMediaAttachment->media_id
            : null;
        $expectedLegacyPath = is_string($group->cover_path) ? $group->cover_path : null;

        if (! $this->overwrite && ($expectedMediaId !== null || filled($expectedLegacyPath))) {
            return;
        }

        try {
            $media = $acquisitions->acquireExternalUrl(
                $this->url,
                ImageUploadPurpose::ContentGroupCover,
                $user,
                ['title' => $group->title],
            );
        } catch (InvalidArgumentException $exception) {
            $this->notifyFailure($user, $exception->getMessage());

            return;
        }

        try {
            $attachments->attachIfUnchanged(
                $group,
                $media,
                MediaAttachmentRole::Cover,
                $user,
                $expectedMediaId,
                $expectedLegacyPath,
            );
        } catch (Throwable $exception) {
            $this->notifyFailure($user, $exception->getMessage());

            return;
        }

        Notification::make()
            ->success()
            ->title(__('admin.notifications.external_group_image_downloaded'))
            ->body(__('admin.notifications.external_image_downloaded_body', ['path' => $media->path]))
            ->sendToDatabase($user);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if ($user instanceof User) {
            $this->notifyFailure($user, $exception->getMessage());
        }
    }

    private function notifyFailure(User $user, string $reason): void
    {
        Notification::make()
            ->danger()
            ->title(__('admin.notifications.external_group_image_download_failed'))
            ->body($reason)
            ->sendToDatabase($user);
    }
}
