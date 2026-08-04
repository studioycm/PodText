<?php

namespace App\Filament\Resources\Imports;

use App\Filament\Clusters\SystemCluster;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Filament\Resources\Imports\Tables\ImportsTable;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use BackedEnum;
use Filament\Actions\Imports\Models\Import;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The Q4 ruling's minimal read-only imports listing: the intake queue's
 * "view all" doorway for imports — listing only, never management. Rows
 * are created by the import modal (and, later, WB's fetch runs); the only
 * action here is reading a failure CSV. Authorization rides ImportPolicy
 * (viewAny admin; create/update/delete explicitly denied).
 */
class ImportResource extends Resource
{
    protected static ?string $cluster = SystemCluster::class;

    use UsesAdminNavigationOrder;

    protected static ?string $model = Import::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getModelLabel(): string
    {
        return __('admin.resources.imports.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.imports.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.imports.navigation');
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
        return ImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImports::route('/'),
        ];
    }
}
