<?php

namespace App\Jobs;

use App\Enums\MediaNamingStrategy;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\Media\ImageFileNamer;
use App\Support\Media\ImageUploadRules;
use Awcodes\Curator\Models\Media;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DownloadExternalContentItemImage implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $contentItemId,
        public int $userId,
        public bool $overwrite = false,
    ) {
        $this->onQueue('imports-exports');
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $item = ContentItem::query()->find($this->contentItemId);

        if (! $user instanceof User || ! $item instanceof ContentItem) {
            return;
        }

        if (! $this->overwrite && filled($item->image_path)) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_already_local'));

            return;
        }

        $url = (string) $item->external_thumbnail_url;

        if (! str_starts_with($url, 'https://')) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_https_only'));

            return;
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->get($url);
        } catch (Throwable $exception) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
                'reason' => $exception->getMessage(),
            ]));

            return;
        }

        if ($response->failed()) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_failed_body', [
                'reason' => "HTTP {$response->status()}",
            ]));

            return;
        }

        $contents = $response->body();

        if (strlen($contents) > ImageUploadRules::MAX_KILOBYTES * 1024) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_too_large'));

            return;
        }

        $mimeType = $this->mimeType($contents);

        if (! in_array($mimeType, ImageUploadRules::rasterMimeTypes(), true)) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_invalid_mime'));

            return;
        }

        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_invalid_mime'));

            return;
        }

        if (($dimensions[0] ?? 0) > ImageUploadRules::MAX_DIMENSION_PIXELS || ($dimensions[1] ?? 0) > ImageUploadRules::MAX_DIMENSION_PIXELS) {
            $this->notifyFailure($user, __('admin.notifications.external_image_download_too_large'));

            return;
        }

        $directory = ImageFileNamer::directoryFor(ImageFileNamer::CONTENT_ITEM_IMAGE);
        $fileName = ImageFileNamer::storageFileName(
            $item->slug,
            $item->reference_key,
            $mimeType,
            MediaNamingStrategy::Slug,
            fn (string $fileName): bool => Storage::disk('public')->exists("{$directory}/{$fileName}"),
        );
        $path = "{$directory}/{$fileName}";

        Storage::disk('public')->put($path, $contents);

        Media::query()->updateOrCreate(
            [
                'disk' => 'public',
                'path' => $path,
            ],
            [
                'directory' => $directory,
                'visibility' => 'public',
                'name' => pathinfo($fileName, PATHINFO_FILENAME),
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'size' => strlen($contents),
                'type' => $mimeType,
                'ext' => ImageFileNamer::extensionForMimeType($mimeType),
                'title' => $item->title,
            ],
        );

        $item->forceFill(['image_path' => $path])->save();

        Notification::make()
            ->success()
            ->title(__('admin.notifications.external_image_downloaded'))
            ->body(__('admin.notifications.external_image_downloaded_body', ['path' => $path]))
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

    private function mimeType(string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        return is_string($mimeType) ? $mimeType : 'application/octet-stream';
    }
}
