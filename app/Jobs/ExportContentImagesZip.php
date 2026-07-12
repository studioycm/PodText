<?php

namespace App\Jobs;

use App\Enums\MediaNamingStrategy;
use App\Models\User;
use App\Support\Media\ContentImagesExportManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportContentImagesZip implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $userId,
        public ?int $contentGroupId,
        public string $strategy,
    ) {
        $this->onQueue('imports-exports');
    }

    public function handle(ContentImagesExportManager $manager): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $result = $manager->build(
            $this->userId,
            $this->contentGroupId,
            MediaNamingStrategy::fromSetting($this->strategy),
        );

        Notification::make()
            ->success()
            ->title(__('admin.notifications.content_images_export_ready'))
            ->body($this->completionBody($result['included'], $result['skipped']))
            ->actions([
                Action::make('download')
                    ->label(__('admin.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(route('admin.content-images-exports.download', ['token' => $result['token']]))
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->danger()
            ->title(__('admin.notifications.content_images_export_failed'))
            ->body(__('admin.notifications.content_images_export_failed_body', [
                'reason' => $exception->getMessage(),
            ]))
            ->sendToDatabase($user);
    }

    /**
     * @param  array<int, string>  $skipped
     */
    private function completionBody(int $included, array $skipped): string
    {
        $body = trans_choice('admin.notifications.content_images_export_ready_body', $included, [
            'count' => $included,
        ]);

        if ($skipped === []) {
            return $body;
        }

        $sample = collect($skipped)
            ->take(10)
            ->implode(', ');

        return $body.' '.__('admin.notifications.content_images_export_skipped_body', [
            'count' => count($skipped),
            'paths' => $sample,
        ]);
    }
}
