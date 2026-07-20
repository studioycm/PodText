<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;

class ListMedia extends ListRecords
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
