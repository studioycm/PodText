<?php

namespace App\Filament\Resources\Media;

use App\Enums\NavigationBadge;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Pages\ReviewMediaIssues;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Resources\Media\Tables\MediaTable;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\Media;
use App\Support\Media\MediaRecordScope;
use App\Support\NavigationBadgeCount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class MediaResource extends Resource
{
    use UsesAdminNavigationOrder;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModel(): string
    {
        return config('curator.model', Media::class);
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCount::format(Cache::flexible(
            NavigationBadgeCount::cacheKey(NavigationBadge::Media),
            NavigationBadgeCount::ttl(),
            // Counted through the resource's own query, so the badge can
            // never disagree with the list it links to.
            fn (): int => static::getEloquentQuery()->count(),
        ));
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('admin.curator.navigation_badge_tooltip');
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
        /** @var class-string<Media> $model */
        $model = static::getModel();
        $table = (new $model)->getTable();
        $storagePeers = $model::query()
            ->from("{$table} as storage_identity_peers")
            ->selectRaw('count(*)')
            ->whereColumn('storage_identity_peers.disk', "{$table}.disk")
            ->whereColumn('storage_identity_peers.path', "{$table}.path");

        return app(MediaRecordScope::class)
            ->inventoryQuery()
            ->select("{$table}.*")
            ->selectSub($storagePeers, 'storage_identity_count');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
            'review-issues' => ReviewMediaIssues::route('/{record}/review-issues'),
        ];
    }
}
