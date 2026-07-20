<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use Filament\Resources\Pages\CreateRecord;

class CreateContentGroup extends CreateRecord
{
    protected static string $resource = ContentGroupResource::class;

    private ?string $pendingCoverMediaReferenceKey = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$data, $this->pendingCoverMediaReferenceKey] = app(MediaAttachmentFormState::class)->prepare(
            $data,
            'cover_media_reference_key',
            null,
            MediaAttachmentRole::Cover,
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        app(MediaAttachmentFormState::class)->persist(
            $this->getRecord(),
            $this->pendingCoverMediaReferenceKey,
            MediaAttachmentRole::Cover,
            $actor,
        );
    }
}
