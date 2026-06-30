<?php

namespace App\Filament\Resources\Transcriptions;

use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Filament\Resources\Transcriptions\Pages\EditTranscription;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Resources\Transcriptions\Schemas\TranscriptionForm;
use App\Filament\Resources\Transcriptions\Tables\TranscriptionsTable;
use App\Models\Transcription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TranscriptionResource extends Resource
{
    protected static ?string $model = Transcription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.resources.transcription.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.transcription.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.transcription.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
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
