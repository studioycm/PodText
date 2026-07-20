<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Schemas\EpisodeWorkspaceForm;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CreateEpisodeWorkspace extends CreateRecord
{
    protected static string $resource = ContentItemResource::class;

    public function form(Schema $schema): Schema
    {
        return EpisodeWorkspaceForm::configure($schema);
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
        $this->getRecord()->refresh()->adoptWorkspaceTranscription();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('workspace', [
            'record' => $this->getRecord(),
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label(__('admin.actions.save_episode_workspace'))
                ->icon(Heroicon::OutlinedCheck),
            $this->getCreateAnotherFormAction()
                ->label(__('admin.actions.save_and_add_another')),
            $this->getCancelFormAction(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('classicCreate')
                ->label(__('admin.actions.classic_create'))
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->url($this->getResource()::getUrl('create')),
        ];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('admin.notifications.episode_workspace_created'));
    }

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
}
