<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ManageSettingsImportLocks extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $slug = 'settings-import-locks';

    protected string $view = 'filament.pages.manage-settings-import-locks';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_settings_import_locks.title');
    }
}
