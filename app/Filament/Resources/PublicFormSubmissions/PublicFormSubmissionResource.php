<?php

namespace App\Filament\Resources\PublicFormSubmissions;

use App\Enums\NavigationBadge;
use App\Enums\PublicFormSubmissionStatus;
use App\Filament\Resources\PublicFormSubmissions\Pages\EditPublicFormSubmission;
use App\Filament\Resources\PublicFormSubmissions\Pages\ListPublicFormSubmissions;
use App\Filament\Resources\PublicFormSubmissions\Schemas\PublicFormSubmissionForm;
use App\Filament\Resources\PublicFormSubmissions\Tables\PublicFormSubmissionsTable;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\PublicFormSubmission;
use App\Support\NavigationBadgeCount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

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
        return NavigationBadgeCount::format(Cache::flexible(
            NavigationBadgeCount::cacheKey(NavigationBadge::FormSubmissions),
            NavigationBadgeCount::ttl(),
            // Deliberately not `static::getEloquentQuery()->count()` like its
            // two siblings: NAV1 made this one a work queue rather than an
            // inventory, so it counts only what still needs a human. The
            // model forgets the key on every write, which is what keeps the
            // number exact despite the wide stale window.
            fn (): int => PublicFormSubmission::query()
                ->status(PublicFormSubmissionStatus::New)
                ->count(),
        ));
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('admin.resources.public_form_submission.navigation_badge_tooltip');
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
