<?php

namespace App\Filament\Resources\PublicFormSubmissions;

use App\Filament\Resources\PublicFormSubmissions\Pages\EditPublicFormSubmission;
use App\Filament\Resources\PublicFormSubmissions\Pages\ListPublicFormSubmissions;
use App\Filament\Resources\PublicFormSubmissions\Schemas\PublicFormSubmissionForm;
use App\Filament\Resources\PublicFormSubmissions\Tables\PublicFormSubmissionsTable;
use App\Models\PublicFormSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PublicFormSubmissionResource extends Resource
{
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

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
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
