<?php

namespace App\Support\Media;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;

class MediaOperationReceipts
{
    public function renameSucceeded(User $user, string $oldName, string $newName): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_library.rename_done_title'))
            ->body(__('admin.media_library.rename_done_body', [
                'old' => $oldName,
                'new' => $newName,
            ])));
    }

    public function swapSucceeded(User $user, int $oldSize, int $newSize): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_library.swap_done_title'))
            ->body(__('admin.media_library.swap_done_body', [
                'old_size' => Number::fileSize($oldSize),
                'new_size' => Number::fileSize($newSize),
            ])));
    }

    public function deleteSucceeded(User $user, string $name): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_library.delete_done_title', ['name' => $name])));
    }

    public function bulkDeleteFinished(User $user, int $deleted, int $blocked, int $failed): void
    {
        $notification = Notification::make()
            ->title(__('admin.media_library.bulk_delete_done_title'))
            ->body(__('admin.media_library.bulk_delete_done_body', [
                'deleted' => $deleted,
                'blocked' => $blocked,
                'failed' => $failed,
            ]));

        $failed > 0 || $deleted === 0 ? $notification->warning() : $notification->success();

        $this->durable($user, $notification);
    }

    public function sanitizeSucceeded(User $user, string $name, int $closed, int $remaining): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_issue_review.sanitize.done_title', ['name' => $name]))
            ->body(__('admin.media_issue_review.sanitize.done_body', [
                'closed' => $closed,
                'remaining' => $remaining,
            ])));
    }

    public function audienceMadePublic(User $user, string $name): void
    {
        $this->durable($user, Notification::make()
            ->warning()
            ->title(__('admin.media_issue_review.audience.done_title', ['name' => $name])));
    }

    public function audienceRevoked(User $user, string $name): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_issue_review.audience.revoke_done_title', ['name' => $name])));
    }

    public function diskCorrected(User $user, string $name, string $disk): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_issue_review.storage_disk.done_title', ['name' => $name]))
            ->body(__('admin.media_issue_review.storage_disk.done_body', ['disk' => $disk])));
    }

    public function referenceKeyMinted(User $user, string $name, string $referenceKey): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_issue_review.portable_identity.minted_title', ['name' => $name]))
            ->body(__('admin.media_issue_review.portable_identity.minted_body', ['key' => $referenceKey])));
    }

    public function trustGranted(User $user, string $name): void
    {
        $this->durable($user, Notification::make()
            ->warning()
            ->title(__('admin.media_issue_review.trust.done_title', ['name' => $name])));
    }

    public function trustRevoked(User $user, string $name): void
    {
        $this->durable($user, Notification::make()
            ->success()
            ->title(__('admin.media_issue_review.trust.untrust_done_title', ['name' => $name])));
    }

    public function titleByOwnerFinished(User $user, int $titled, int $skipped): void
    {
        $notification = Notification::make()
            ->title(__('admin.media_library.title_by_owner_done_title'))
            ->body(__('admin.media_library.title_by_owner_done_body', [
                'titled' => $titled,
                'skipped' => $skipped,
            ]));

        $titled === 0 ? $notification->warning() : $notification->success();

        $this->durable($user, $notification);
    }

    public function relocationFinished(User $user, int $moved, int $skipped, int $failed): void
    {
        $notification = Notification::make()
            ->title(__('admin.media_library.relocation_done_title'))
            ->body(__('admin.media_library.relocation_done_body', [
                'moved' => $moved,
                'skipped' => $skipped,
                'failed' => $failed,
            ]));

        $failed > 0 ? $notification->warning() : $notification->success();

        $this->durable($user, $notification);
    }

    public function operationFailed(?string $reason = null): void
    {
        Notification::make()
            ->danger()
            ->title(__('admin.media_library.op_failed_title'))
            ->body($reason ?? __('admin.media_library.op_failed_body'))
            ->send();
    }

    private function durable(User $user, Notification $notification): void
    {
        $notification->send();
        $notification->sendToDatabase($user);
    }
}
