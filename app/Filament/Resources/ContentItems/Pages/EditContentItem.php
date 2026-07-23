<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

class EditContentItem extends EditRecord
{
    protected static string $resource = ContentItemResource::class;

    private ?string $pendingPrimaryImageMediaReferenceKey = null;

    private ?string $pendingUnsafePrimaryImageFingerprint = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['primary_image_media_reference_key'] = app(MediaAttachmentFormState::class)->pickerIdentity(
            $this->getRecord(),
            MediaAttachmentRole::PrimaryImage,
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$data, $this->pendingPrimaryImageMediaReferenceKey, $this->pendingUnsafePrimaryImageFingerprint] = app(MediaAttachmentFormState::class)->prepare(
            $data,
            'primary_image_media_reference_key',
            $this->getRecord(),
            MediaAttachmentRole::PrimaryImage,
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        app(MediaAttachmentFormState::class)->persist(
            $this->getRecord(),
            $this->pendingPrimaryImageMediaReferenceKey,
            MediaAttachmentRole::PrimaryImage,
            $actor,
            $this->pendingUnsafePrimaryImageFingerprint,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ContentImageActions::contentItemImage(),
            DeleteAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return ContentTabPosition::Before;
    }

    public function getContentTabLabel(): ?string
    {
        return __('admin.tabs.item_details');
    }
}
