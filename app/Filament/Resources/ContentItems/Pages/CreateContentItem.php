<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Support\Concerns\InteractsWithOwnerImageFormLifecycle;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateContentItem extends CreateRecord
{
    use InteractsWithOwnerImageFormLifecycle;

    protected static string $resource = ContentItemResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareOwnerImageForm(
            $data,
            null,
            MediaAttachmentRole::PrimaryImage,
            'primary_image_media_reference_key',
        );
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $this->persistOwnerImageForm(
            $this->getRecord(),
            MediaAttachmentRole::PrimaryImage,
            'primary_image_media_reference_key',
            $actor,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', [
            'record' => $this->getRecord(),
        ]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('admin.notifications.content_item_created'))
            ->body(__('admin.notifications.content_item_created_add_transcription'));
    }
}
