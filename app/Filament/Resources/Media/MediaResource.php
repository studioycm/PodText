<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Resources\Media\Tables\MediaTable;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\Media;
use App\Support\Media\MediaRecordScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaResource extends Resource
{
    use UsesAdminNavigationOrder;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModel(): string
    {
        return config('curator.model', Media::class);
    }

    public static function getModelLabel(): string
    {
        return __('admin.curator.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.curator.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.curator.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return MediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return app(MediaRecordScope::class)->inventoryQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
