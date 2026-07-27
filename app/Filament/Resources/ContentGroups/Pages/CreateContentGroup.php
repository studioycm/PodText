<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Support\Concerns\InteractsWithOwnerImageFormLifecycle;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateContentGroup extends CreateRecord
{
    use InteractsWithOwnerImageFormLifecycle;

    protected static string $resource = ContentGroupResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareOwnerImageForm(
            $data,
            null,
            MediaAttachmentRole::Cover,
            'cover_media_reference_key',
        );
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $this->persistOwnerImageForm(
            $this->getRecord(),
            MediaAttachmentRole::Cover,
            'cover_media_reference_key',
            $actor,
        );
    }
}
