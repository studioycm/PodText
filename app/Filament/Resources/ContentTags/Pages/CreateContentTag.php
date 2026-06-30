<?php

namespace App\Filament\Resources\ContentTags\Pages;

use App\Filament\Resources\ContentTags\ContentTagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContentTag extends CreateRecord
{
    protected static string $resource = ContentTagResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
