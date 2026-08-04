<?php

namespace App\Filament\Resources\Transcriptions;

use App\Enums\UserRole;
use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Filament\Resources\Transcriptions\Pages\EditTranscription;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Resources\Transcriptions\Schemas\TranscriptionForm;
use App\Filament\Resources\Transcriptions\Tables\TranscriptionsTable;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\Transcription;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use App\Support\Transcriptions\TranscriptionModeLabel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TranscriptionResource extends Resource
{
    use UsesAdminNavigationOrder;

    protected static ?string $model = Transcription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return TranscriptionModeLabel::text('admin.resources.transcription.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return TranscriptionModeLabel::text('admin.resources.transcription.plural');
    }

    public static function getNavigationLabel(): string
    {
        return TranscriptionModeLabel::text('admin.resources.transcription.navigation');
    }

    /**
     * EQ-2 (2026-08-05): in single-transcription mode this resource mirrors
     * the episodes list one row per episode, so it leaves plain admins'
     * sidebars as clutter. Super-admins keep it for global transcript
     * maintenance (they alone can see transcript history), and multi mode
     * restores it for everyone. Hiding the link is a decluttering choice,
     * not an access control — the URL keeps working, as the resource's own
     * authorization intends.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return MultiTranscriptionSurfaces::isMultiMode()
            || MultiTranscriptionSurfaces::currentUserCan(UserRole::SuperAdmin, requiresMode: false);
    }

    public static function form(Schema $schema): Schema
    {
        return TranscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TranscriptionsTable::configure($table);
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
            'index' => ListTranscriptions::route('/'),
            'create' => CreateTranscription::route('/create'),
            'edit' => EditTranscription::route('/{record}/edit'),
        ];
    }
}
