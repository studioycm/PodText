<?php

namespace App\Filament\Resources\HomepageSections;

use App\Filament\Resources\HomepageSections\Pages\CreateHomepageSection;
use App\Filament\Resources\HomepageSections\Pages\EditHomepageSection;
use App\Filament\Resources\HomepageSections\Pages\ListHomepageSections;
use App\Filament\Resources\HomepageSections\Schemas\HomepageSectionForm;
use App\Filament\Resources\HomepageSections\Tables\HomepageSectionsTable;
use App\Models\HomepageSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HomepageSectionResource extends Resource
{
    protected static ?string $model = HomepageSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.resources.homepage_section.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.homepage_section.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.homepage_section.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
    }

    public static function form(Schema $schema): Schema
    {
        return HomepageSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomepageSectionsTable::configure($table);
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
            'index' => ListHomepageSections::route('/'),
            'create' => CreateHomepageSection::route('/create'),
            'edit' => EditHomepageSection::route('/{record}/edit'),
        ];
    }
}
