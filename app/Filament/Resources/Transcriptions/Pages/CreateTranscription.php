<?php

namespace App\Filament\Resources\Transcriptions\Pages;

use App\Filament\Resources\Transcriptions\TranscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTranscription extends CreateRecord
{
    protected static string $resource = TranscriptionResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
