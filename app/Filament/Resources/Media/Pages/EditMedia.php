<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\EditRecord;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_intersect_key($data, array_flip(['alt', 'title', 'caption', 'description']));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_intersect_key($data, array_flip(['alt', 'title', 'caption', 'description']));
    }
}
