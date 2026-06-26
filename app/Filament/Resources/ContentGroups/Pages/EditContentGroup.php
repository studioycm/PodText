<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Filament\Resources\ContentGroups\ContentGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentGroup extends EditRecord
{
    protected static string $resource = ContentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
