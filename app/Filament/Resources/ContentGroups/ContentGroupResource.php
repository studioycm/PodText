<?php

namespace App\Filament\Resources\ContentGroups;

use App\Filament\Resources\ContentGroups\Pages\CreateContentGroup;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentGroups\Schemas\ContentGroupForm;
use App\Filament\Resources\ContentGroups\Tables\ContentGroupsTable;
use App\Models\ContentGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContentGroupResource extends Resource
{
    protected static ?string $model = ContentGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.resources.content_group.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.content_group.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.content_group.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
    }

    public static function form(Schema $schema): Schema
    {
        return ContentGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentGroups::route('/'),
            'create' => CreateContentGroup::route('/create'),
            'edit' => EditContentGroup::route('/{record}/edit'),
        ];
    }
}
