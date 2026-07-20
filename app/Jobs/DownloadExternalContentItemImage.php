<?php

namespace App\Jobs;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\SafeExternalImageFetcher;
use Filament\Notifications\Notification;
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
        SafeExternalImageFetcher $fetcher,
        ImageUploadValidator $validator,
        MediaFilesystemMutationCoordinator $coordinator,
        MediaAttachmentManager $attachments,
        MediaRecordScope $scope,
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

        $idempotencyKey = hash('sha256', implode('|', [
            'content_item_primary_image',
            (string) $item->getKey(),
            $url,
        ]));
        $expectedMediaId = $item->primaryImageMediaAttachment instanceof MediaAttachment
            ? (int) $item->primaryImageMediaAttachment->media_id
            : null;
        $expectedLegacyPath = is_string($item->image_path) ? $item->image_path : null;
        $recoverableMedia = $this->recoverableMedia($idempotencyKey, $scope);

        if ($recoverableMedia instanceof Media) {
            if ($expectedMediaId === (int) $recoverableMedia->getKey()) {
                return;
            }
        }

        if (! $this->overwrite && ($item->primaryImageMediaAttachment !== null || filled($item->image_path))) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_already_local'));

            return;
        }

        if ($recoverableMedia instanceof Media) {
            $attachments->attachIfUnchanged(
                $item,
                $recoverableMedia,
                MediaAttachmentRole::PrimaryImage,
                $user,
                $expectedMediaId,
                $expectedLegacyPath,
            );
            $this->notifySuccess($user, $recoverableMedia);

            return;
        }

        if ($this->hasIncompleteOperation($idempotencyKey)) {
            throw new \RuntimeException('A previous external image import is incomplete and requires retry or repair.');
        }

        try {
            $contents = $fetcher->fetch($url);
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: 'external-image');
            $validated = $validator->validateBytes(
                $contents,
                basename($path),
                ImageUploadPurpose::ContentItemPrimaryImage,
            );
            $media = $coordinator->createFromValidatedImage(
                $validated,
                $user,
                ['title' => $item->title],
                MediaMutationOperationType::ExternalImport,
                [
                    'idempotency_key' => $idempotencyKey,
                    'content_item_reference_key' => $item->reference_key,
                    'external_url_sha256' => hash('sha256', $url),
                    'role' => MediaAttachmentRole::PrimaryImage->value,
                ],
            );

            try {
                $attachments->attachIfUnchanged(
                    $item,
                    $media,
                    MediaAttachmentRole::PrimaryImage,
                    $user,
                    $expectedMediaId,
                    $expectedLegacyPath,
                );
            } catch (Throwable $exception) {
                try {
                    $coordinator->delete($media, $user);
                } catch (Throwable $cleanupException) {
                    throw new \RuntimeException(
                        'The external image attachment failed and its media compensation also failed: '.$cleanupException->getMessage(),
                        previous: $exception,
                    );
                }

                throw $exception;
            }
        } catch (InvalidArgumentException $exception) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
                'reason' => $exception->getMessage(),
            ]));

            return;
        }

        $this->notifySuccess($user, $media);
    }

    private function recoverableMedia(string $idempotencyKey, MediaRecordScope $scope): ?Media
    {
        $operation = MediaMutationOperation::query()
            ->where('operation', MediaMutationOperationType::ExternalImport->value)
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', [
                MediaMutationStatus::Committed->value,
                MediaMutationStatus::CleanupPending->value,
                MediaMutationStatus::Completed->value,
            ])
            ->latest('id')
            ->first();

        if (! $operation instanceof MediaMutationOperation || blank($operation->media_reference_key)) {
            return null;
        }

        return $scope->findByReferenceKey(
            (string) $operation->media_reference_key,
            ImageUploadPurpose::ContentItemPrimaryImage,
        );
    }

    private function hasIncompleteOperation(string $idempotencyKey): bool
    {
        return MediaMutationOperation::query()
            ->where('operation', MediaMutationOperationType::ExternalImport->value)
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', [
                MediaMutationStatus::Staged->value,
                MediaMutationStatus::Copied->value,
            ])
            ->exists();
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
            'reason' => $exception->getMessage(),
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
