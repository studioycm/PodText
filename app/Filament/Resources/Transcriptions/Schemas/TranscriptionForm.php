<?php

namespace App\Filament\Resources\Transcriptions\Schemas;

use App\Filament\Forms\Components\PublicationStatusSelect;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Support\Transcriptions\TranscriptionModeLabel;
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
                ->label(TranscriptionModeLabel::text('admin.fields.content_item'))
                ->helperText(TranscriptionModeLabel::singleText('admin.helpers.transcription_content_item'))
                ->relationship('contentItem', 'title')
                ->searchable()
                ->preload(false)
                ->optionsLimit(50)
                ->required();
        }

        $identityFields = [
            ...$identityFields,
            $useRelationshipTranscriberSelect
                ? RelationshipOptionForms::configureTranscriberRelationshipSelect(
                    Select::make('transcriber_ids'),
                    episodeLanguage: true,
                )
                : RelationshipOptionForms::configureTranscriberOptionsSelect(
                    Select::make('transcriber_ids'),
                    episodeLanguage: true,
                ),
            TextInput::make('title')
                ->label(__('admin.fields.title'))
                ->helperText(TranscriptionModeLabel::singleText('admin.helpers.transcription_title'))
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
                ->description(TranscriptionModeLabel::singleText('admin.descriptions.transcription_identity'))
                ->schema($identityFields)
                ->columns(2),
            Section::make(TranscriptionModeLabel::text('admin.sections.transcript'))
                ->description(TranscriptionModeLabel::singleText('admin.descriptions.transcript_markdown'))
                ->schema([
                    MarkdownEditor::make('transcript_markdown')
                        ->label(__('admin.fields.transcript_markdown'))
                        ->helperText(TranscriptionModeLabel::singleText('admin.helpers.transcript_markdown'))
                        ->disableToolbarButtons(['attachFiles'])
                        ->fileAttachments(false)
                        ->required()
                        ->columnSpanFull(),
                ]),
            Section::make(__('admin.sections.publication'))
                ->description(TranscriptionModeLabel::singleText('admin.descriptions.transcription_publication'))
                ->schema([
                    PublicationStatusSelect::make('status')
                        ->label(__('admin.fields.status'))
                        ->helperText(TranscriptionModeLabel::singleText('admin.helpers.transcription_status'))
                        ->required(),
                    DateTimePicker::make('published_at')
                        ->label(__('admin.fields.published_at'))
                        ->helperText(TranscriptionModeLabel::singleText('admin.helpers.transcription_published_at'))
                        ->displayFormat('d/m/Y H:i')
                        ->timezone('Asia/Jerusalem'),
                ])
                ->columns(2),
        ];
    }
}
