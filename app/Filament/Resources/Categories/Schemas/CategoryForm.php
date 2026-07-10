<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Forms\Components\SlugInput;
use App\Filament\Resources\Support\RelationshipOptionForms;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        RelationshipOptionForms::configureCategorySelect(
                            Select::make('parent_id')
                                ->label(__('admin.fields.parent_category'))
                                ->relationship('parent', 'name')
                                ->searchable()
                                ->preload()
                        ),
                        SlugInput::source('name', table: 'categories')
                            ->label(__('admin.fields.name'))
                            ->required()
                            ->maxLength(255),
                        SlugInput::slug(table: 'categories')
                            ->label(__('admin.fields.slug')),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.content'))
                    ->schema([
                        MarkdownEditor::make('description_markdown')
                            ->label(__('admin.fields.description_markdown'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('admin.sections.visibility_order'))
                    ->schema([
                        Toggle::make('is_visible')
                            ->label(__('admin.fields.is_visible'))
                            ->default(true)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label(__('admin.fields.sort_order'))
                            ->helperText(__('admin.helpers.sort_order'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }
}
