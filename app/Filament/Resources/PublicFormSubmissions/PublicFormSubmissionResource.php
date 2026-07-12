<?php

namespace App\Filament\Resources\PublicFormSubmissions;

use App\Enums\PublicFormSubmissionStatus;
use App\Filament\Resources\PublicFormSubmissions\Pages\EditPublicFormSubmission;
use App\Filament\Resources\PublicFormSubmissions\Pages\ListPublicFormSubmissions;
use App\Filament\Resources\PublicFormSubmissions\Schemas\PublicFormSubmissionForm;
use App\Filament\Resources\PublicFormSubmissions\Tables\PublicFormSubmissionsTable;
use App\Filament\Support\AdminNavigationOrder;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\PublicFormSubmission;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

use function Filament\Support\original_request;

class PublicFormSubmissionResource extends Resource
{
    use UsesAdminNavigationOrder;

    protected static ?string $model = PublicFormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $recordTitleAttribute = 'form_name_snapshot';

    public static function getModelLabel(): string
    {
        return __('admin.resources.public_form_submission.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.public_form_submission.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.public_form_submission.navigation');
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::remember(
            PublicFormSubmission::NEW_SUBMISSIONS_NAVIGATION_BADGE_CACHE_KEY,
            now()->addMinute(),
            function (): ?string {
                $newSubmissionsCount = PublicFormSubmission::query()
                    ->status(PublicFormSubmissionStatus::New)
                    ->count();

                return $newSubmissionsCount > 0 ? number_format($newSubmissionsCount) : null;
            },
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('admin.resources.public_form_submission.navigation_badge_tooltip');
    }

    public static function isNavigationBadgeDeferred(): bool
    {
        return AdminNavigationOrder::hasDeferredBadge(static::class);
    }

    public static function getNavigationItems(): array
    {
        if (! static::hasPage('index')) {
            return [];
        }

        $activeRoutePattern = static::getNavigationItemActiveRoutePattern();

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => original_request()->routeIs($activeRoutePattern))
                ->badge(
                    static::isNavigationBadgeDeferred()
                        ? fn (): ?string => static::getNavigationBadge()
                        : static::getNavigationBadge(),
                    color: static::getNavigationBadgeColor(),
                )
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PublicFormSubmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicFormSubmissionsTable::configure($table);
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
            'index' => ListPublicFormSubmissions::route('/'),
            'edit' => EditPublicFormSubmission::route('/{record}/edit'),
        ];
    }
}
