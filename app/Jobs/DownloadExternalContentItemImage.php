<?php

namespace App\Jobs;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Support\Media\ExternalImageFailureMessage;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaAttachmentManager;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

class DownloadExternalContentItemImage implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(
        public int $contentItemId,
        public int $userId,
        public bool $overwrite = false,
        public ?string $expectedUrl = null,
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
        return $this->contentItemId.':'.hash('sha256', (string) $this->expectedUrl);
    }

    public function handle(
        MediaAcquisitionManager $acquisitions,
        MediaAttachmentManager $attachments,
    ): void {
        $user = User::query()->find($this->userId);
        $item = ContentItem::query()->with('primaryImageMediaAttachment.media')->find($this->contentItemId);

        if (! $user instanceof User || ! $item instanceof ContentItem) {
            return;
        }

        Gate::forUser($user)->authorize('create', config('curator.model', Media::class));

        $url = trim((string) $item->external_thumbnail_url);

        if ($this->expectedUrl !== null && ! hash_equals($this->expectedUrl, $url)) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
                'reason' => __('admin.notifications.external_image_download_source_changed'),
            ]));

            return;
        }

        $expectedMediaId = $item->primaryImageMediaAttachment instanceof MediaAttachment
            ? (int) $item->primaryImageMediaAttachment->media_id
            : null;
        if (! $this->overwrite && $expectedMediaId !== null) {
            return;
        }

        try {
            $media = $acquisitions->acquireExternalUrl(
                $url,
                ImageUploadPurpose::ContentItemPrimaryImage,
                $user,
                ['title' => $item->title],
            );
        } catch (InvalidArgumentException $exception) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
                'reason' => ExternalImageFailureMessage::for($exception),
            ]));

            return;
        }

        try {
            $attachments->attachIfUnchanged(
                $item,
                $media,
                MediaAttachmentRole::PrimaryImage,
                $user,
                $expectedMediaId,
            );
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
                'reason' => __('admin.notifications.external_image_attachment_failed'),
            ]));

            return;
        }

        $this->notifySuccess($user, $media);
    }

    private function notifySuccess(User $user, Media $media): void
    {
        Notification::make()
            ->success()
            ->title(__('admin.notifications.external_image_downloaded'))
            ->body(__('admin.notifications.external_image_downloaded_body', ['path' => $media->path]))
            ->sendToDatabase($user);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
            'reason' => $exception instanceof AuthorizationException
                ? __('admin.notifications.external_image_authorization_failed')
                : ExternalImageFailureMessage::for($exception),
        ]));
    }

    private function notifyFailure(User $user, string $body): void
    {
        Notification::make()
            ->danger()
            ->title(__('admin.notifications.external_image_download_failed'))
            ->body($body)
            ->sendToDatabase($user);
    }
}
