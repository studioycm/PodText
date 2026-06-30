<?php

namespace App\Filament\Resources\ContentTags\Pages;

use App\Filament\Resources\ContentTags\ContentTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContentTags extends ListRecords
{
    protected static string $resource = ContentTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
