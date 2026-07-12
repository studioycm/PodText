<?php

namespace App\Filament\Resources\ContentItems;

use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\ContentItems\Schemas\ContentItemForm;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Filament\Support\AdminNavigationOrder;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\ContentItem;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

use function Filament\Support\original_request;

class ContentItemResource extends Resource
{
    use UsesAdminNavigationOrder;

    protected static ?string $model = ContentItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.resources.content_item.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.content_item.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.content_item.navigation');
    }

    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make(__('admin.resources.content_item.workspace_navigation'))
                ->group(null)
                ->icon(Heroicon::OutlinedPencilSquare)
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.workspace-create'))
                ->sort(AdminNavigationOrder::episodeWorkspaceCreateSort())
                ->url(static::getUrl('workspace-create')),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ContentItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TranscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentItems::route('/'),
            'workspace-create' => CreateEpisodeWorkspace::route('/workspace/create'),
            'create' => CreateContentItem::route('/create'),
            'workspace' => EditEpisodeWorkspace::route('/{record}/workspace'),
            'edit' => EditContentItem::route('/{record}/edit'),
        ];
    }
}
