<?php

namespace App\Filament\Resources\Transcriptions\Schemas;

use App\Enums\PublicationStatus;
use App\Filament\Resources\Support\RelationshipOptionForms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TranscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        TextInput::make('reference_key')
                            ->label(__('admin.fields.reference_key'))
                            ->helperText(__('admin.helpers.reference_key'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Select::make('content_item_id')
                            ->label(__('admin.fields.content_item'))
                            ->relationship('contentItem', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        RelationshipOptionForms::configureTranscriberRelationshipSelect(
                            Select::make('transcriber_ids'),
                        ),
                        TextInput::make('title')
                            ->label(__('admin.fields.title'))
                            ->maxLength(255),
                        TextInput::make('language_code')
                            ->label(__('admin.fields.language_code'))
                            ->helperText(__('admin.helpers.language_code'))
                            ->default('he')
                            ->required()
                            ->maxLength(10),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.transcript'))
                    ->schema([
                        MarkdownEditor::make('transcript_markdown')
                            ->label(__('admin.fields.transcript_markdown'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('admin.sections.publication'))
                    ->schema([
                        Select::make('status')
                            ->label(__('admin.fields.status'))
                            ->options(PublicationStatus::class)
                            ->default(PublicationStatus::Draft->value)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label(__('admin.fields.published_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                    ])
                    ->columns(2),
            ]);
    }
}
