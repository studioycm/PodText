<?php

namespace App\Filament\Resources\SettingsBackups;

use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Filament\Resources\SettingsBackups\Tables\SettingsBackupsTable;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\SettingsBackupVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SettingsBackupResource extends Resource
{
    use UsesAdminNavigationOrder;

    protected static ?string $model = SettingsBackupVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'label';

    public static function getModelLabel(): string
    {
        return __('admin.resources.settings_backup.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.settings_backup.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.settings_backup.navigation');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return SettingsBackupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettingsBackups::route('/'),
        ];
    }
}
