<?php

namespace App\Filament\Resources\ContentGroups\Schemas;

use App\Filament\Forms\Components\PublicationStatusSelect;
use App\Filament\Forms\Components\SlugInput;
use App\Filament\Forms\MediaPickerField;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Support\Media\ImageFileNamer;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ContentGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        Hidden::make('reference_key')
                            ->default(fn (): string => (string) Str::ulid())
                            ->dehydrated()
                            ->visibleOn('create'),
                        TextInput::make('reference_key')
                            ->label(__('admin.fields.reference_key'))
                            ->helperText(__('admin.helpers.reference_key'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        SlugInput::source('title', table: 'content_groups')
                            ->label(__('admin.fields.title'))
                            ->required()
                            ->maxLength(255),
                        SlugInput::slug(source: 'title', table: 'content_groups')
                            ->label(__('admin.fields.slug')),
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
                            ->default(__('public.labels.podcast'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('group_type_label_plural')
                            ->label(__('admin.fields.group_type_label_plural'))
                            ->helperText(__('admin.helpers.group_type_label_plural'))
                            ->default(__('public.labels.podcasts'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('default_item_type_label_singular')
                            ->label(__('admin.fields.default_item_type_label_singular'))
                            ->helperText(__('admin.helpers.default_item_type_label_singular'))
                            ->default(__('public.labels.item'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('default_item_type_label_plural')
                            ->label(__('admin.fields.default_item_type_label_plural'))
                            ->helperText(__('admin.helpers.default_item_type_label_plural'))
                            ->default(__('public.labels.items'))
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
                        MediaPickerField::make('cover_path', ImageFileNamer::CONTENT_GROUP_COVER)
                            ->label(__('admin.fields.cover_path'))
                            ->helperText(__('admin.helpers.cover_path'))
                            ->hintAction(
                                Action::make('manageDefaultImages')
                                    ->label(__('admin.actions.manage_default_images'))
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->url(fn (): string => DisplaySettings::getUrl()),
                            ),
                        TextInput::make('cover_alt_text')
                            ->label(__('admin.fields.cover_alt_text'))
                            ->helperText(__('admin.helpers.cover_alt_text'))
                            ->maxLength(160),
                        RelationshipOptionForms::configureCategorySelect(
                            Select::make('categories')
                                ->label(__('admin.fields.categories'))
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload(false)
                                ->optionsLimit(50)
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
                        PublicationStatusSelect::make('status')
                            ->label(__('admin.fields.status'))
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
