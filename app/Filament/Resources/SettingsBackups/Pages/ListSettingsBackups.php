<?php

namespace App\Filament\Resources\SettingsBackups\Pages;

use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use Filament\Resources\Pages\ListRecords;

class ListSettingsBackups extends ListRecords
{
    protected static string $resource = SettingsBackupResource::class;
}
