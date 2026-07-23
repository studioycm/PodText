<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateContentItem extends CreateRecord
{
    protected static string $resource = ContentItemResource::class;

    private ?string $pendingPrimaryImageMediaReferenceKey = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$data, $this->pendingPrimaryImageMediaReferenceKey] = app(MediaAttachmentFormState::class)->prepare(
            $data,
            'primary_image_media_reference_key',
            null,
            MediaAttachmentRole::PrimaryImage,
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        app(MediaAttachmentFormState::class)->persist(
            $this->getRecord(),
            $this->pendingPrimaryImageMediaReferenceKey,
            MediaAttachmentRole::PrimaryImage,
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
