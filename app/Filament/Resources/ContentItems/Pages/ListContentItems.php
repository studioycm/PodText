<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Filament\Resources\ContentItems\ContentItemResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListContentItems extends ListRecords
{
    protected static string $resource = ContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createEpisodeWorkspace')
                ->label(__('admin.actions.create_episode_workspace'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(ContentItemResource::getUrl('workspace-create')),
            CreateAction::make()
                ->label(__('admin.actions.classic_create')),
        ];
    }
}
