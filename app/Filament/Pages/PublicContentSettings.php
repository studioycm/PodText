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
                                'latest' => __('admin.sort.latest'),
                                'oldest' => __('admin.sort.oldest'),
                                'title' => __('admin.sort.title'),
                                'pinned' => __('admin.sort.pinned'),
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
            ]);
    }
}
