<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

class EditContentGroup extends EditRecord
{
    protected static string $resource = ContentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContentImageActions::contentGroupCover(),
            ContentImageActions::detachUnsafeOwnerImage(MediaAttachmentRole::Cover),
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
        return __('admin.tabs.group_details');
    }

    private ?string $pendingCoverMediaReferenceKey = null;

    private ?string $pendingUnsafeCoverFingerprint = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cover_media_reference_key'] = app(MediaAttachmentFormState::class)->pickerIdentity(
            $this->getRecord(),
            MediaAttachmentRole::Cover,
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$data, $this->pendingCoverMediaReferenceKey, $this->pendingUnsafeCoverFingerprint] = app(MediaAttachmentFormState::class)->prepare(
            $data,
            'cover_media_reference_key',
            $this->getRecord(),
            MediaAttachmentRole::Cover,
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        app(MediaAttachmentFormState::class)->persist(
            $this->getRecord(),
            $this->pendingCoverMediaReferenceKey,
            MediaAttachmentRole::Cover,
            $actor,
            $this->pendingUnsafeCoverFingerprint,
        );
    }
}
