<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Schemas\EpisodeWorkspaceForm;
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
}
