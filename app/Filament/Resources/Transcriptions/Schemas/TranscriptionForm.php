<?php

namespace App\Filament\Resources\Transcriptions\Schemas;

use App\Filament\Forms\Components\PublicationStatusSelect;
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
            ->components(self::components());
    }

    /**
     * @return array<int, mixed>
     */
    public static function components(bool $includeContentItem = true, bool $useRelationshipTranscriberSelect = true): array
    {
        $identityFields = [
            TextInput::make('reference_key')
                ->label(__('admin.fields.reference_key'))
                ->helperText(__('admin.helpers.reference_key'))
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),
        ];

        if ($includeContentItem) {
            $identityFields[] = Select::make('content_item_id')
                ->label(__('admin.fields.content_item'))
                ->relationship('contentItem', 'title')
                ->searchable()
                ->preload()
                ->required();
        }

        $identityFields = [
            ...$identityFields,
            $useRelationshipTranscriberSelect
                ? RelationshipOptionForms::configureTranscriberRelationshipSelect(
                    Select::make('transcriber_ids'),
                )
                : RelationshipOptionForms::configureTranscriberOptionsSelect(
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
        ];

        return [
            Section::make(__('admin.sections.identity'))
                ->schema($identityFields)
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
                    PublicationStatusSelect::make('status')
                        ->label(__('admin.fields.status'))
                        ->required(),
                    DateTimePicker::make('published_at')
                        ->label(__('admin.fields.published_at'))
                        ->displayFormat('d/m/Y H:i')
                        ->timezone('Asia/Jerusalem'),
                ])
                ->columns(2),
        ];
    }
}
