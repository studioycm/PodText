<?php

namespace App\Filament\Resources\ContentItems\Schemas;

use App\Enums\PublicationStatus;
use App\Rules\ApprovedEmbedUrl;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ContentItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        TextInput::make('reference_key')
                            ->label(__('admin.fields.reference_key'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Select::make('content_group_id')
                            ->label(__('admin.fields.content_group'))
                            ->relationship('contentGroup', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->label(__('admin.fields.title'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('admin.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: 'content_items',
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('content_group_id', $get('content_group_id')),
                            ),
                        TextInput::make('type_label_singular_override')
                            ->label(__('admin.fields.type_label_singular_override'))
                            ->helperText(__('admin.helpers.type_label_singular_override'))
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.content'))
                    ->schema([
                        MarkdownEditor::make('description_markdown')
                            ->label(__('admin.fields.description_markdown'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
                            ->columnSpanFull(),
                        TextInput::make('media_url')
                            ->label(__('admin.fields.media_url'))
                            ->url()
                            ->required()
                            ->maxLength(2048),
                        TextInput::make('embed_url')
                            ->label(__('admin.fields.embed_url'))
                            ->helperText(__('admin.helpers.embed_url'))
                            ->url()
                            ->maxLength(2048)
                            ->rules([new ApprovedEmbedUrl])
                            ->validationMessages([
                                'embed_url.url' => __('admin.validation.embed_url_url'),
                            ]),
                        TextInput::make('duration_seconds')
                            ->label(__('admin.fields.duration_seconds'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        Select::make('authors')
                            ->label(__('admin.fields.authors'))
                            ->relationship('authors', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.transcript'))
                    ->schema([
                        MarkdownEditor::make('transcript_markdown')
                            ->label(__('admin.fields.transcript_markdown'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
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
                            ->label(__('admin.fields.published_at')),
                        DateTimePicker::make('original_published_at')
                            ->label(__('admin.fields.original_published_at')),
                    ])
                    ->columns(3),
            ]);
    }
}
