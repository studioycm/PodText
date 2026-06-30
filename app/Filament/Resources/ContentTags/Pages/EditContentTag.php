<?php

namespace App\Filament\Resources\ContentTags\Pages;

use App\Filament\Resources\ContentTags\ContentTagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentTag extends EditRecord
{
    protected static string $resource = ContentTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
