<?php

namespace App\Filament\Resources\HomepageSections\Schemas;

use App\Enums\HomepageSectionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class HomepageSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.fields.name'))
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
                        Select::make('type')
                            ->label(__('admin.fields.homepage_section_type'))
                            ->options(HomepageSectionType::class)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.homepage_targeting'))
                    ->schema([
                        Select::make('category_id')
                            ->label(__('admin.fields.category'))
                            ->helperText(__('admin.helpers.homepage_category'))
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('tag_id')
                            ->label(__('admin.fields.tag'))
                            ->helperText(__('admin.helpers.homepage_tag'))
                            ->relationship('tag', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('content_group_id')
                            ->label(__('admin.fields.content_group'))
                            ->helperText(__('admin.helpers.homepage_content_group'))
                            ->relationship('contentGroup', 'title')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.visibility_order'))
                    ->schema([
                        TextInput::make('limit')
                            ->label(__('admin.fields.limit'))
                            ->helperText(__('admin.helpers.homepage_limit'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(6),
                        TextInput::make('sort_order')
                            ->label(__('admin.fields.sort_order'))
                            ->helperText(__('admin.helpers.sort_order'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->default(0),
                        Toggle::make('is_visible')
                            ->label(__('admin.fields.is_visible'))
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }
}
