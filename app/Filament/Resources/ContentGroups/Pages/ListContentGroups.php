<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Filament\Resources\ContentGroups\ContentGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContentGroups extends ListRecords
{
    protected static string $resource = ContentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
