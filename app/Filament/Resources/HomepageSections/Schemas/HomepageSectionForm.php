<?php

namespace App\Filament\Resources\HomepageSections\Schemas;

use App\Enums\HomepageSectionType;
use App\Filament\Resources\Support\RelationshipOptionForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
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
                    ->description(__('admin.descriptions.homepage_section_identity'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.fields.name'))
                            ->helperText(__('admin.helpers.homepage_section_name'))
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
                            ->helperText(__('admin.helpers.homepage_section_type'))
                            ->options([
                                HomepageSectionType::Latest->value => HomepageSectionType::Latest->getLabel(),
                                HomepageSectionType::Category->value => HomepageSectionType::Category->getLabel(),
                                HomepageSectionType::Tag->value => HomepageSectionType::Tag->getLabel(),
                                HomepageSectionType::ContentGroup->value => HomepageSectionType::ContentGroup->getLabel(),
                            ])
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('category_id', null);
                                $set('tag_id', null);
                                $set('content_group_id', null);
                            })
                            ->required(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.homepage_targeting'))
                    ->description(__('admin.descriptions.homepage_targeting'))
                    ->schema([
                        TextEntry::make('section_summary')
                            ->label(__('admin.fields.section_summary'))
                            ->state(fn (Get $get): string => self::sectionSummary($get))
                            ->columnSpanFull(),
                        RelationshipOptionForms::configureCategorySelect(
                            Select::make('category_id')
                                ->label(__('admin.fields.category'))
                                ->helperText(__('admin.helpers.homepage_category'))
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => self::isSectionType($get, HomepageSectionType::Category))
                                ->visible(fn (Get $get): bool => self::isSectionType($get, HomepageSectionType::Category))
                        ),
                        RelationshipOptionForms::configureContentTagSelect(
                            Select::make('tag_id')
                                ->label(__('admin.fields.tag'))
                                ->helperText(__('admin.helpers.homepage_tag'))
                                ->relationship('tag', 'name')
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => self::isSectionType($get, HomepageSectionType::Tag))
                                ->visible(fn (Get $get): bool => self::isSectionType($get, HomepageSectionType::Tag))
                        ),
                        RelationshipOptionForms::configureContentGroupSelect(
                            Select::make('content_group_id')
                                ->label(__('admin.fields.content_group'))
                                ->helperText(__('admin.helpers.homepage_content_group'))
                                ->relationship('contentGroup', 'title')
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => self::isSectionType($get, HomepageSectionType::ContentGroup))
                                ->visible(fn (Get $get): bool => self::isSectionType($get, HomepageSectionType::ContentGroup))
                        ),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.visibility_order'))
                    ->description(__('admin.descriptions.homepage_visibility_order'))
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
                            ->helperText(__('admin.helpers.homepage_is_visible'))
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }

    private static function isSectionType(Get $get, HomepageSectionType $type): bool
    {
        $state = $get('type');

        return $state instanceof HomepageSectionType
            ? $state === $type
            : $state === $type->value;
    }

    private static function sectionSummary(Get $get): string
    {
        $state = $get('type');
        $type = $state instanceof HomepageSectionType ? $state->value : $state;

        return match ($type) {
            HomepageSectionType::Category->value => __('admin.summaries.homepage_section_category'),
            HomepageSectionType::Tag->value => __('admin.summaries.homepage_section_tag'),
            HomepageSectionType::ContentGroup->value => __('admin.summaries.homepage_section_content_group'),
            HomepageSectionType::Latest->value => __('admin.summaries.homepage_section_latest'),
            default => __('admin.summaries.homepage_section_choose_type'),
        };
    }
}
