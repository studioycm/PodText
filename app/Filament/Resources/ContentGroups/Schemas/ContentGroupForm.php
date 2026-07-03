<?php

namespace App\Filament\Resources\ContentGroups\Schemas;

use App\Enums\PublicationStatus;
use App\Filament\Resources\Support\RelationshipOptionForms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ContentGroupForm
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
                        TextInput::make('title')
                            ->label(__('admin.fields.title'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $old, ?string $state): void {
                                if (filled($get('slug')) && $get('slug') !== Str::slug((string) $old)) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            })
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('admin.fields.slug'))
                            ->helperText(__('admin.helpers.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(),
                        Select::make('original_language_code')
                            ->label(__('admin.fields.original_language_code'))
                            ->options(fn (): array => collect(config('localization.available_locales', ['he', 'en']))
                                ->mapWithKeys(fn (string $locale): array => [$locale => __("admin.locales.{$locale}")])
                                ->all())
                            ->default('he')
                            ->required(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.type_labels'))
                    ->schema([
                        TextInput::make('group_type_label_singular')
                            ->label(__('admin.fields.group_type_label_singular'))
                            ->helperText(__('admin.helpers.group_type_label_singular'))
                            ->default('Podcast')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('group_type_label_plural')
                            ->label(__('admin.fields.group_type_label_plural'))
                            ->helperText(__('admin.helpers.group_type_label_plural'))
                            ->default('Podcasts')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('default_item_type_label_singular')
                            ->label(__('admin.fields.default_item_type_label_singular'))
                            ->helperText(__('admin.helpers.default_item_type_label_singular'))
                            ->default('Episode')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('default_item_type_label_plural')
                            ->label(__('admin.fields.default_item_type_label_plural'))
                            ->helperText(__('admin.helpers.default_item_type_label_plural'))
                            ->default('Episodes')
                            ->required()
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
                        FileUpload::make('cover_path')
                            ->label(__('admin.fields.cover_path'))
                            ->disk('public')
                            ->directory('content-groups/covers')
                            ->visibility('public')
                            ->image()
                            ->maxSize(2048),
                        RelationshipOptionForms::configureCategorySelect(
                            Select::make('categories')
                                ->label(__('admin.fields.categories'))
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->helperText(__('admin.helpers.group_categories')),
                            allowEdit: false,
                        ),
                    ]),
                Section::make(__('admin.sections.homepage'))
                    ->schema([
                        TextInput::make('homepage_order')
                            ->label(__('admin.fields.homepage_order'))
                            ->helperText(__('admin.helpers.homepage_order'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
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
