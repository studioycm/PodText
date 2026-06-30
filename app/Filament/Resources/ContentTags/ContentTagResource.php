<?php

namespace App\Filament\Resources\ContentTags;

use App\Filament\Resources\ContentTags\Pages\CreateContentTag;
use App\Filament\Resources\ContentTags\Pages\EditContentTag;
use App\Filament\Resources\ContentTags\Pages\ListContentTags;
use App\Filament\Resources\ContentTags\Schemas\ContentTagForm;
use App\Filament\Resources\ContentTags\Tables\ContentTagsTable;
use App\Models\ContentTag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContentTagResource extends Resource
{
    protected static ?string $model = ContentTag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.resources.content_tag.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.content_tag.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.content_tag.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
    }

    public static function form(Schema $schema): Schema
    {
        return ContentTagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentTagsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentTags::route('/'),
            'create' => CreateContentTag::route('/create'),
            'edit' => EditContentTag::route('/{record}/edit'),
        ];
    }
}
