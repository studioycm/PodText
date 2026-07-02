<?php

namespace App\Filament\Pages;

use App\Settings\PublicContentSettings as PublicContentSettingsData;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PublicContentSettings extends SettingsPage
{
    protected static string $settings = PublicContentSettingsData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.public_content_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.public_content_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.homepage_settings'))
                    ->description(__('admin.descriptions.public_content_settings_homepage'))
                    ->schema([
                        TextInput::make('homepage_item_limit')
                            ->label(__('admin.fields.homepage_item_limit'))
                            ->helperText(__('admin.helpers.homepage_item_limit'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                        TextInput::make('pinned_item_limit')
                            ->label(__('admin.fields.pinned_item_limit'))
                            ->helperText(__('admin.helpers.pinned_item_limit'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                        Toggle::make('show_latest_section')
                            ->label(__('admin.fields.show_latest_section'))
                            ->helperText(__('admin.helpers.show_latest_section')),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.public_display'))
                    ->description(__('admin.descriptions.public_content_settings_display'))
                    ->schema([
                        Select::make('default_public_sort')
                            ->label(__('admin.fields.default_public_sort'))
                            ->helperText(__('admin.helpers.default_public_sort'))
                            ->options([
                                'latest_transcription' => __('admin.sort.latest_transcription'),
                                'oldest_transcription' => __('admin.sort.oldest_transcription'),
                                'title_asc' => __('admin.sort.title_asc'),
                                'title_desc' => __('admin.sort.title_desc'),
                                'duration_shortest' => __('admin.sort.duration_shortest'),
                                'duration_longest' => __('admin.sort.duration_longest'),
                                'original_newest' => __('admin.sort.original_newest'),
                                'original_oldest' => __('admin.sort.original_oldest'),
                            ])
                            ->required(),
                        Select::make('default_result_layout')
                            ->label(__('admin.fields.default_result_layout'))
                            ->helperText(__('admin.helpers.default_result_layout'))
                            ->options([
                                'cards' => __('admin.layouts.cards'),
                                'rows' => __('admin.layouts.rows'),
                            ])
                            ->required(),
                        Select::make('item_page_layout')
                            ->label(__('admin.fields.item_page_layout'))
                            ->helperText(__('admin.helpers.item_page_layout'))
                            ->options([
                                'standard' => __('admin.layouts.standard'),
                                'default' => __('admin.layouts.default'),
                                'media_first' => __('admin.layouts.media_first'),
                                'transcript_first' => __('admin.layouts.transcript_first'),
                            ])
                            ->required(),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.public_card_display'))
                    ->description(__('admin.descriptions.public_content_settings_cards'))
                    ->schema([
                        Select::make('homepage_card_image_size')
                            ->label(__('admin.fields.homepage_card_image_size'))
                            ->helperText(__('admin.helpers.homepage_card_image_size'))
                            ->options([
                                'hidden' => __('admin.card_image_size.hidden'),
                                'small' => __('admin.card_image_size.small'),
                                'medium' => __('admin.card_image_size.medium'),
                                'large' => __('admin.card_image_size.large'),
                            ])
                            ->required(),
                        Select::make('homepage_card_density')
                            ->label(__('admin.fields.homepage_card_density'))
                            ->helperText(__('admin.helpers.homepage_card_density'))
                            ->options([
                                'compact' => __('admin.card_density.compact'),
                                'comfortable' => __('admin.card_density.comfortable'),
                            ])
                            ->required(),
                        Select::make('homepage_card_title_size')
                            ->label(__('admin.fields.homepage_card_title_size'))
                            ->helperText(__('admin.helpers.homepage_card_title_size'))
                            ->options([
                                'sm' => __('admin.card_title_size.sm'),
                                'base' => __('admin.card_title_size.base'),
                                'lg' => __('admin.card_title_size.lg'),
                            ])
                            ->required(),
                        TextInput::make('homepage_cards_per_page')
                            ->label(__('admin.fields.homepage_cards_per_page'))
                            ->helperText(__('admin.helpers.homepage_cards_per_page'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(48),
                        TextInput::make('homepage_description_lines')
                            ->label(__('admin.fields.homepage_description_lines'))
                            ->helperText(__('admin.helpers.homepage_description_lines'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(4),
                        Toggle::make('homepage_show_group_badge')
                            ->label(__('admin.fields.homepage_show_group_badge'))
                            ->helperText(__('admin.helpers.homepage_show_group_badge')),
                        Toggle::make('homepage_show_authors')
                            ->label(__('admin.fields.homepage_show_authors')),
                        Toggle::make('homepage_show_categories')
                            ->label(__('admin.fields.homepage_show_categories')),
                        Toggle::make('homepage_show_tags')
                            ->label(__('admin.fields.homepage_show_tags')),
                        Toggle::make('homepage_show_duration')
                            ->label(__('admin.fields.homepage_show_duration')),
                        Toggle::make('homepage_show_effective_date')
                            ->label(__('admin.fields.homepage_show_effective_date')),
                        Toggle::make('homepage_show_description')
                            ->label(__('admin.fields.homepage_show_description')),
                    ])
                    ->columns(3),
            ]);
    }
}
