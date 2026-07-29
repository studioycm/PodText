<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Support\Concerns\InteractsWithOwnerImageFormLifecycle;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

class EditContentGroup extends EditRecord
{
    use InteractsWithOwnerImageFormLifecycle;

    protected static string $resource = ContentGroupResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            ContentImageActions::contentGroupCover(),
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydrateOwnerImageForm(
            $data,
            $this->getRecord(),
            MediaAttachmentRole::Cover,
            'cover_media_reference_key',
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareOwnerImageForm(
            $data,
            $this->getRecord(),
            MediaAttachmentRole::Cover,
            'cover_media_reference_key',
        );
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $this->persistOwnerImageForm(
            $this->getRecord(),
            MediaAttachmentRole::Cover,
            'cover_media_reference_key',
            $actor,
            enforceExpectedIdentity: true,
        );
    }
}
