<?php

namespace App\Filament\Resources\PublicFormSubmissions\Pages;

use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use Filament\Resources\Pages\EditRecord;

class EditPublicFormSubmission extends EditRecord
{
    protected static string $resource = PublicFormSubmissionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
