<?php

namespace App\Filament\Resources\Transcriptions\Pages;

use App\Filament\Resources\Transcriptions\TranscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTranscription extends EditRecord
{
    protected static string $resource = TranscriptionResource::class;

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
