<?php

namespace App\Filament\Resources\PublicFormSubmissions\Pages;

use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPublicFormSubmissions extends ListRecords
{
    protected static string $resource = PublicFormSubmissionResource::class;
}
