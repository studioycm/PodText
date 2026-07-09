<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Settings\PublicContentSettings as PublicContentSettingsData;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\About\PublicAboutPageRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

class PublicContentSettings extends SettingsPage
{
    use UsesAdminNavigationOrder;

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
                Tabs::make(__('admin.sections.public_content_settings_tabs'))
                    ->key('public-content-settings-tabs')
                    ->persistTabInQueryString('public-content-tab')
                    ->vertical()
                    ->tabs([
                        Tab::make(__('admin.tabs.public_content_settings.homepage'))
                            ->id('homepage')
                            ->key('public-settings-tab-homepage')
                            ->schema([
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
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.display'))
                            ->id('display')
                            ->key('public-settings-tab-display')
                            ->schema([
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
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
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
                                        Select::make('homepage_card_image_fit')
                                            ->label(__('admin.fields.homepage_card_image_fit'))
                                            ->helperText(__('admin.helpers.homepage_card_image_fit'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::imageFitOptions())
                                            ->default('cover')
                                            ->native(false)
                                            ->required(),
                                        Select::make('homepage_card_image_radius')
                                            ->label(__('admin.fields.homepage_card_image_radius'))
                                            ->helperText(__('admin.helpers.homepage_card_image_radius'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::imageRadiusOptions())
                                            ->default('mid_rounded')
                                            ->native(false)
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
                                        Select::make('homepage_group_badge_mode')
                                            ->label(__('admin.fields.homepage_group_badge_mode'))
                                            ->helperText(__('admin.helpers.homepage_group_badge_mode'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::groupBadgeModeOptions())
                                            ->default('name_only')
                                            ->native(false)
                                            ->required(),
                                        TextInput::make('homepage_group_title_separator')
                                            ->label(__('admin.fields.homepage_group_title_separator'))
                                            ->helperText(__('admin.helpers.homepage_group_title_separator'))
                                            ->maxLength(12),
                                        Toggle::make('homepage_group_badge_duplicate_thumbnail')
                                            ->label(__('admin.fields.homepage_group_badge_duplicate_thumbnail'))
                                            ->helperText(__('admin.helpers.homepage_group_badge_duplicate_thumbnail')),
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
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_front_configuration'))
                                    ->description(__('admin.descriptions.public_front_configuration'))
                                    ->schema([
                                        Select::make('display_defaults.layout')
                                            ->label(__('admin.fields.public_front_display_layout'))
                                            ->helperText(__('admin.helpers.public_front_display_layout'))
                                            ->options([
                                                'cards' => __('admin.layouts.cards'),
                                                'rows' => __('admin.layouts.rows'),
                                            ])
                                            ->required(),
                                        Select::make('display_defaults.density')
                                            ->label(__('admin.fields.public_front_card_density'))
                                            ->helperText(__('admin.helpers.public_front_card_density'))
                                            ->options([
                                                'compact' => __('admin.card_density.compact'),
                                                'comfortable' => __('admin.card_density.comfortable'),
                                            ])
                                            ->required(),
                                        Select::make('display_defaults.image_size')
                                            ->label(__('admin.fields.public_front_card_image_size'))
                                            ->helperText(__('admin.helpers.public_front_card_image_size'))
                                            ->options([
                                                'hidden' => __('admin.card_image_size.hidden'),
                                                'small' => __('admin.card_image_size.small'),
                                                'medium' => __('admin.card_image_size.medium'),
                                                'large' => __('admin.card_image_size.large'),
                                            ])
                                            ->required(),
                                        Select::make('display_defaults.image_fit')
                                            ->label(__('admin.fields.public_front_card_image_fit'))
                                            ->helperText(__('admin.helpers.public_front_card_image_fit'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::imageFitOptions())
                                            ->default('cover')
                                            ->native(false)
                                            ->required(),
                                        Select::make('display_defaults.image_radius')
                                            ->label(__('admin.fields.public_front_card_image_radius'))
                                            ->helperText(__('admin.helpers.public_front_card_image_radius'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::imageRadiusOptions())
                                            ->default('mid_rounded')
                                            ->native(false)
                                            ->required(),
                                        Select::make('display_defaults.title_size')
                                            ->label(__('admin.fields.public_front_card_title_size'))
                                            ->helperText(__('admin.helpers.public_front_card_title_size'))
                                            ->options([
                                                'sm' => __('admin.card_title_size.sm'),
                                                'base' => __('admin.card_title_size.base'),
                                                'lg' => __('admin.card_title_size.lg'),
                                            ])
                                            ->required(),
                                        Select::make('display_defaults.transcription_display')
                                            ->label(__('admin.fields.public_front_transcription_display'))
                                            ->helperText(__('admin.helpers.public_front_transcription_display'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::transcriptionDisplayOptions())
                                            ->default('effective_only')
                                            ->native(false)
                                            ->required(),
                                        TextInput::make('display_defaults.page_size')
                                            ->label(__('admin.fields.public_front_page_size'))
                                            ->helperText(__('admin.helpers.public_front_page_size'))
                                            ->required()
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(48),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_default_images'))
                                    ->description(__('admin.descriptions.public_default_images'))
                                    ->schema($this->defaultImageFamilyFieldsets())
                                    ->columns(1)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_transcription_policy'))
                                    ->description(__('admin.descriptions.public_transcription_policy'))
                                    ->schema([
                                        Select::make('transcription_policy.public_mode')
                                            ->label(__('admin.fields.public_transcription_policy_public_mode'))
                                            ->helperText(__('admin.helpers.public_transcription_policy_public_mode'))
                                            ->options(fn (): array => PublicTranscriptionPolicy::modeOptions())
                                            ->default(PublicTranscriptionPolicy::MODE_FEATURED_ONLY)
                                            ->native(false)
                                            ->required(),
                                        Select::make('transcription_policy.count_mode')
                                            ->label(__('admin.fields.public_transcription_policy_count_mode'))
                                            ->helperText(__('admin.helpers.public_transcription_policy_count_mode'))
                                            ->options(fn (): array => PublicTranscriptionPolicy::modeOptions())
                                            ->default(PublicTranscriptionPolicy::MODE_FEATURED_ONLY)
                                            ->native(false)
                                            ->required(),
                                        Toggle::make('transcription_policy.show_multiple_transcriptions_on_item_page')
                                            ->label(__('admin.fields.public_transcription_policy_show_multiple_transcriptions_on_item_page'))
                                            ->helperText(__('admin.helpers.public_transcription_policy_show_multiple_transcriptions_on_item_page')),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.item_page'))
                            ->id('item-page')
                            ->key('public-settings-tab-item-page')
                            ->schema([
                                Section::make(__('admin.sections.public_front_item_page_layout'))
                                    ->description(__('admin.descriptions.public_front_item_page_layout'))
                                    ->schema([
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
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_front_item_page_header'))
                                    ->description(__('admin.descriptions.public_front_item_page_header'))
                                    ->schema([
                                        Toggle::make('item_page.show_breadcrumbs')
                                            ->label(__('admin.fields.item_page_show_breadcrumbs'))
                                            ->helperText(__('admin.helpers.item_page_show_breadcrumbs'))
                                            ->default(true),
                                        Select::make('item_page.podcast_identity.mode')
                                            ->label(__('admin.fields.item_page_podcast_identity_mode'))
                                            ->helperText(__('admin.helpers.item_page_podcast_identity_mode'))
                                            ->options(fn (): array => PublicItemPageRegistry::podcastIdentityModeOptions())
                                            ->default('badge')
                                            ->native(false)
                                            ->required(),
                                        Select::make('item_page.podcast_identity.color')
                                            ->label(__('admin.fields.item_page_podcast_identity_color'))
                                            ->helperText(__('admin.helpers.item_page_podcast_identity_color'))
                                            ->options(fn (): array => PublicItemPageRegistry::podcastIdentityColorOptions())
                                            ->default('primary')
                                            ->native(false)
                                            ->required(),
                                        Select::make('item_page.podcast_identity.size')
                                            ->label(__('admin.fields.item_page_podcast_identity_size'))
                                            ->helperText(__('admin.helpers.item_page_podcast_identity_size'))
                                            ->options(fn (): array => PublicItemPageRegistry::podcastIdentitySizeOptions())
                                            ->default('sm')
                                            ->native(false)
                                            ->required(),
                                        Select::make('item_page.podcast_identity.position')
                                            ->label(__('admin.fields.item_page_podcast_identity_position'))
                                            ->helperText(__('admin.helpers.item_page_podcast_identity_position'))
                                            ->options(fn (): array => PublicItemPageRegistry::podcastIdentityPositionOptions())
                                            ->default('above_title')
                                            ->native(false)
                                            ->required(),
                                        Select::make('item_page.podcast_identity.icon')
                                            ->label(__('admin.fields.item_page_podcast_identity_icon'))
                                            ->helperText(__('admin.helpers.item_page_podcast_identity_icon'))
                                            ->options(fn (): array => PublicFrontCardTemplateRegistry::iconOptions())
                                            ->default('podcast')
                                            ->native(false)
                                            ->required(),
                                        Select::make('item_page.podcast_identity.icon_position')
                                            ->label(__('admin.fields.item_page_podcast_identity_icon_position'))
                                            ->helperText(__('admin.helpers.item_page_podcast_identity_icon_position'))
                                            ->options(fn (): array => PublicFrontCardTemplateRegistry::iconPositionOptions())
                                            ->default('inline_before')
                                            ->native(false)
                                            ->required(),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_front_item_page_dates'))
                                    ->description(__('admin.descriptions.public_front_item_page_dates'))
                                    ->schema([
                                        Select::make('item_page.dates.display')
                                            ->label(__('admin.fields.item_page_dates_display'))
                                            ->helperText(__('admin.helpers.item_page_dates_display'))
                                            ->options(fn (): array => PublicItemPageRegistry::dateDisplayOptions())
                                            ->default('both')
                                            ->native(false)
                                            ->required(),
                                        $this->itemPageDateFieldset('site_published', 'item_page_site_published_date'),
                                        $this->itemPageDateFieldset('original_published', 'item_page_original_published_date'),
                                        $this->itemPageDateFieldset('transcription_date', 'item_page_transcription_date', withEnabled: true),
                                    ])
                                    ->columns(1)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_front_item_page_badges'))
                                    ->description(__('admin.descriptions.public_front_item_page_badges'))
                                    ->schema([
                                        Select::make('item_page.badges.info.size')
                                            ->label(__('admin.fields.item_page_info_badge_size'))
                                            ->helperText(__('admin.helpers.item_page_info_badge_size'))
                                            ->options(fn (): array => PublicItemPageRegistry::badgeSizeOptions())
                                            ->default('sm')
                                            ->native(false)
                                            ->required(),
                                        Select::make('item_page.badges.info.color')
                                            ->label(__('admin.fields.item_page_info_badge_color'))
                                            ->helperText(__('admin.helpers.item_page_info_badge_color'))
                                            ->options(fn (): array => PublicItemPageRegistry::badgeColorOptions())
                                            ->default('gray')
                                            ->native(false)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_front_item_page_transcript_controls'))
                                    ->description(__('admin.descriptions.public_front_item_page_transcript_controls'))
                                    ->schema([
                                        Toggle::make('item_page.show_transcript_actions_menu')
                                            ->label(__('admin.fields.item_page_show_transcript_actions_menu'))
                                            ->helperText(__('admin.helpers.item_page_show_transcript_actions_menu'))
                                            ->default(false),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                Section::make(__('admin.sections.public_front_item_page_info_fields'))
                                    ->description(__('admin.descriptions.public_front_item_page_info_fields'))
                                    ->schema([
                                        $this->itemPageInfoFieldRepeater(),
                                    ])
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.menu_header'))
                            ->id('menu-header')
                            ->key('public-settings-tab-menu-header')
                            ->schema([
                                Section::make(__('admin.sections.public_front_menu_header'))
                                    ->description(__('admin.descriptions.public_front_menu_header'))
                                    ->schema([
                                        Toggle::make('menu_config.enabled')
                                            ->label(__('admin.fields.public_front_menu_enabled'))
                                            ->helperText(__('admin.helpers.public_front_menu_enabled')),
                                        Select::make('menu_config.items_alignment')
                                            ->label(__('admin.fields.public_menu_items_alignment'))
                                            ->helperText(__('admin.helpers.public_menu_items_alignment'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::publicMenuAlignmentOptions())
                                            ->default('center')
                                            ->native(false)
                                            ->required(),
                                        Fieldset::make(__('admin.sections.public_menu_logo'))
                                            ->schema([
                                                FileUpload::make('menu_config.logo.light_path')
                                                    ->label(__('admin.fields.public_menu_logo_light_path'))
                                                    ->helperText(__('admin.helpers.public_menu_logo_light_path'))
                                                    ->disk('public')
                                                    ->directory('header')
                                                    ->visibility('public')
                                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                                    ->maxSize(2048),
                                                FileUpload::make('menu_config.logo.dark_path')
                                                    ->label(__('admin.fields.public_menu_logo_dark_path'))
                                                    ->helperText(__('admin.helpers.public_menu_logo_dark_path'))
                                                    ->disk('public')
                                                    ->directory('header')
                                                    ->visibility('public')
                                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                                    ->maxSize(2048),
                                                TextInput::make('menu_config.logo.alt_text')
                                                    ->label(__('admin.fields.public_menu_logo_alt_text'))
                                                    ->helperText(__('admin.helpers.public_menu_logo_alt_text'))
                                                    ->maxLength(120),
                                                Select::make('menu_config.logo.display_mode')
                                                    ->label(__('admin.fields.public_menu_logo_display_mode'))
                                                    ->helperText(__('admin.helpers.public_menu_logo_display_mode'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::publicMenuLogoDisplayModeOptions())
                                                    ->default('image')
                                                    ->native(false),
                                                Select::make('menu_config.logo.size')
                                                    ->label(__('admin.fields.public_menu_logo_size'))
                                                    ->helperText(__('admin.helpers.public_menu_logo_size'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::publicMenuLogoSizeOptions())
                                                    ->default('medium')
                                                    ->native(false),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.public_menu_search'))
                                            ->schema([
                                                Toggle::make('menu_config.search.enabled')
                                                    ->label(__('admin.fields.public_menu_search_enabled'))
                                                    ->helperText(__('admin.helpers.public_menu_search_enabled')),
                                                TextInput::make('menu_config.search.placeholder')
                                                    ->label(__('admin.fields.public_menu_search_placeholder'))
                                                    ->helperText(__('admin.helpers.public_menu_search_placeholder'))
                                                    ->maxLength(120),
                                                Select::make('menu_config.search.route_key')
                                                    ->label(__('admin.fields.public_menu_search_route_key'))
                                                    ->helperText(__('admin.helpers.public_menu_search_route_key'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::routeOptions())
                                                    ->default('search')
                                                    ->native(false)
                                                    ->required(),
                                                TextInput::make('menu_config.search.query_param')
                                                    ->label(__('admin.fields.public_menu_search_query_param'))
                                                    ->helperText(__('admin.helpers.public_menu_search_query_param'))
                                                    ->maxLength(40)
                                                    ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                        Repeater::make('menu_config.items')
                                            ->label(__('admin.fields.public_menu_items'))
                                            ->helperText(__('admin.helpers.public_menu_items'))
                                            ->schema([
                                                Fieldset::make(__('admin.sections.public_menu_item_identity'))
                                                    ->schema([
                                                        TextInput::make('key')
                                                            ->label(__('admin.fields.public_menu_item_key'))
                                                            ->helperText(__('admin.helpers.public_menu_item_key'))
                                                            ->required()
                                                            ->maxLength(80)
                                                            ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                                                        Select::make('type')
                                                            ->label(__('admin.fields.public_menu_item_type'))
                                                            ->helperText(__('admin.helpers.public_menu_item_type'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::publicMenuItemTypeOptions())
                                                            ->default('route')
                                                            ->native(false)
                                                            ->live()
                                                            ->required(),
                                                        TextInput::make('label')
                                                            ->label(__('admin.fields.public_menu_item_label'))
                                                            ->helperText(__('admin.helpers.public_menu_item_label'))
                                                            ->maxLength(80),
                                                        Toggle::make('visible')
                                                            ->label(__('admin.fields.public_menu_item_visible'))
                                                            ->helperText(__('admin.helpers.public_menu_item_visible'))
                                                            ->default(true),
                                                        TextInput::make('sort')
                                                            ->label(__('admin.fields.public_menu_item_sort'))
                                                            ->helperText(__('admin.helpers.public_menu_item_sort'))
                                                            ->numeric()
                                                            ->integer()
                                                            ->minValue(0)
                                                            ->maxValue(1000),
                                                    ])
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                                Fieldset::make(__('admin.sections.public_menu_item_target'))
                                                    ->schema([
                                                        Select::make('route_key')
                                                            ->label(__('admin.fields.public_menu_item_route_key'))
                                                            ->helperText(__('admin.helpers.public_menu_item_route_key'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::routeOptions())
                                                            ->searchable()
                                                            ->native(false)
                                                            ->required(fn (Get $get): bool => $get('type') === 'route')
                                                            ->visible(fn (Get $get): bool => $get('type') === 'route'),
                                                        TextInput::make('external_url')
                                                            ->label(__('admin.fields.public_menu_item_external_url'))
                                                            ->helperText(__('admin.helpers.public_menu_item_external_url'))
                                                            ->url()
                                                            ->maxLength(2048)
                                                            ->required(fn (Get $get): bool => $get('type') === 'external_url')
                                                            ->visible(fn (Get $get): bool => $get('type') === 'external_url'),
                                                        Toggle::make('open_in_new_tab')
                                                            ->label(__('admin.fields.public_menu_item_open_in_new_tab'))
                                                            ->helperText(__('admin.helpers.public_menu_item_open_in_new_tab'))
                                                            ->default(false)
                                                            ->visible(fn (Get $get): bool => $get('type') === 'external_url'),
                                                        Select::make('form_key')
                                                            ->label(__('admin.fields.public_menu_item_form_key'))
                                                            ->helperText(__('admin.helpers.public_menu_item_form_key'))
                                                            ->options(fn (): array => $this->publicFormOptions())
                                                            ->searchable()
                                                            ->native(false)
                                                            ->required(fn (Get $get): bool => $get('type') === 'public_form')
                                                            ->visible(fn (Get $get): bool => $get('type') === 'public_form'),
                                                        Select::make('display_mode')
                                                            ->label(__('admin.fields.public_menu_item_display_mode'))
                                                            ->helperText(__('admin.helpers.public_menu_item_display_mode'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::publicFormDisplayModeOptions())
                                                            ->default('modal')
                                                            ->native(false)
                                                            ->visible(fn (Get $get): bool => $get('type') === 'public_form'),
                                                    ])
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['key'] ?? __('admin.labels.untitled'))
                                            ->defaultItems(0)
                                            ->reorderable()
                                            ->cloneable()
                                            ->collapsed()
                                            ->columns(3)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.public_menu_theme_selector'))
                                            ->schema([
                                                Toggle::make('menu_config.theme_selector.enabled')
                                                    ->label(__('admin.fields.public_menu_theme_selector_enabled'))
                                                    ->helperText(__('admin.helpers.public_menu_theme_selector_enabled')),
                                                Select::make('menu_config.theme_selector.mode')
                                                    ->label(__('admin.fields.public_menu_theme_selector_mode'))
                                                    ->helperText(__('admin.helpers.public_menu_theme_selector_mode'))
                                                    ->options([
                                                        'light_dark_system' => __('admin.public_menu_theme_selector_modes.light_dark_system'),
                                                        'light_dark' => __('admin.public_menu_theme_selector_modes.light_dark'),
                                                    ])
                                                    ->default('light_dark_system')
                                                    ->native(false),
                                                Select::make('menu_config.theme_selector.display_mode')
                                                    ->label(__('admin.fields.public_menu_theme_selector_display_mode'))
                                                    ->helperText(__('admin.helpers.public_menu_theme_selector_display_mode'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::publicMenuThemeDisplayModeOptions())
                                                    ->default('text_icon')
                                                    ->native(false),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                        Repeater::make('route_labels')
                                            ->label(__('admin.fields.public_front_route_labels'))
                                            ->helperText(__('admin.helpers.public_front_route_labels'))
                                            ->schema([
                                                Select::make('route_key')
                                                    ->label(__('admin.fields.public_front_route_key'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::routeOptions())
                                                    ->native(false)
                                                    ->required(),
                                                TextInput::make('label')
                                                    ->label(__('admin.fields.public_front_route_label'))
                                                    ->maxLength(80)
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->reorderable()
                                            ->cloneable()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.podcasts'))
                            ->id('podcasts')
                            ->key('public-settings-tab-podcasts')
                            ->schema([
                                Section::make(__('admin.sections.public_front_podcasts_page'))
                                    ->description(__('admin.descriptions.public_front_podcasts_page'))
                                    ->schema([
                                        Toggle::make('podcasts_page.enabled')
                                            ->label(__('admin.fields.podcasts_page_enabled'))
                                            ->helperText(__('admin.helpers.podcasts_page_enabled')),
                                        TextInput::make('podcasts_page.title')
                                            ->label(__('admin.fields.podcasts_page_title'))
                                            ->helperText(__('admin.helpers.podcasts_page_title'))
                                            ->required()
                                            ->maxLength(160),
                                        Textarea::make('podcasts_page.description')
                                            ->label(__('admin.fields.podcasts_page_description'))
                                            ->helperText(__('admin.helpers.podcasts_page_description'))
                                            ->rows(3)
                                            ->maxLength(1000)
                                            ->columnSpanFull(),
                                        TextInput::make('podcasts_page.group_label_singular')
                                            ->label(__('admin.fields.podcasts_page_group_label_singular'))
                                            ->helperText(__('admin.helpers.podcasts_page_group_label_singular'))
                                            ->required()
                                            ->maxLength(80),
                                        TextInput::make('podcasts_page.group_label_plural')
                                            ->label(__('admin.fields.podcasts_page_group_label_plural'))
                                            ->helperText(__('admin.helpers.podcasts_page_group_label_plural'))
                                            ->required()
                                            ->maxLength(80),
                                        TextInput::make('podcasts_page.cards_per_page')
                                            ->label(__('admin.fields.podcasts_page_cards_per_page'))
                                            ->helperText(__('admin.helpers.podcasts_page_cards_per_page'))
                                            ->required()
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(48),
                                        Toggle::make('podcasts_page.category_filter_enabled')
                                            ->label(__('admin.fields.podcasts_page_category_filter_enabled'))
                                            ->helperText(__('admin.helpers.podcasts_page_category_filter_enabled')),
                                        Toggle::make('podcasts_page.search_enabled')
                                            ->label(__('admin.fields.podcasts_page_search_enabled'))
                                            ->helperText(__('admin.helpers.podcasts_page_search_enabled')),
                                        Select::make('podcasts_page.template_key')
                                            ->label(__('admin.fields.podcasts_page_template_key'))
                                            ->helperText(__('admin.helpers.podcasts_page_template_key'))
                                            ->options(fn (Get $get): array => $this->cardTemplateOptions('content_group', $get('card_templates')))
                                            ->placeholder(__('admin.labels.none'))
                                            ->native(false),
                                        Select::make('podcasts_page.item_template_key')
                                            ->label(__('admin.fields.podcasts_page_item_template_key'))
                                            ->helperText(__('admin.helpers.podcasts_page_item_template_key'))
                                            ->options(fn (Get $get): array => $this->cardTemplateOptions('content_item', $get('card_templates')))
                                            ->placeholder(__('admin.labels.none'))
                                            ->native(false),
                                        Select::make('podcasts_page.image_fit')
                                            ->label(__('admin.fields.podcasts_page_image_fit'))
                                            ->helperText(__('admin.helpers.podcasts_page_image_fit'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::imageFitOptions())
                                            ->default('cover')
                                            ->native(false)
                                            ->required(),
                                        Select::make('podcasts_page.image_radius')
                                            ->label(__('admin.fields.podcasts_page_image_radius'))
                                            ->helperText(__('admin.helpers.podcasts_page_image_radius'))
                                            ->options(fn (): array => PublicFrontConfigRegistry::imageRadiusOptions())
                                            ->default('mid_rounded')
                                            ->native(false)
                                            ->required(),
                                        Toggle::make('podcasts_page.show_description')
                                            ->label(__('admin.fields.podcasts_page_show_description'))
                                            ->helperText(__('admin.helpers.podcasts_page_show_description')),
                                        Toggle::make('podcasts_page.show_categories')
                                            ->label(__('admin.fields.podcasts_page_show_categories'))
                                            ->helperText(__('admin.helpers.podcasts_page_show_categories')),
                                        Toggle::make('podcasts_page.show_episode_count')
                                            ->label(__('admin.fields.podcasts_page_show_episode_count'))
                                            ->helperText(__('admin.helpers.podcasts_page_show_episode_count')),
                                        Fieldset::make(__('admin.sections.public_front_podcasts_group_page'))
                                            ->schema([
                                                Fieldset::make(__('admin.sections.public_front_podcasts_group_page_header'))
                                                    ->schema([
                                                        Toggle::make('podcasts_page.group_page.show_description')
                                                            ->label(__('admin.fields.podcasts_group_page_show_description'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_description')),
                                                        Toggle::make('podcasts_page.group_page.show_categories')
                                                            ->label(__('admin.fields.podcasts_group_page_show_categories'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_categories')),
                                                        Toggle::make('podcasts_page.group_page.show_episode_descriptions')
                                                            ->label(__('admin.fields.podcasts_group_page_show_episode_descriptions'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_episode_descriptions')),
                                                    ])
                                                    ->columns(2)
                                                    ->columnSpanFull(),
                                                Fieldset::make(__('admin.sections.public_front_podcasts_group_items_grid'))
                                                    ->schema([
                                                        Select::make('podcasts_page.group_page.items_layout')
                                                            ->label(__('admin.fields.podcasts_group_page_items_layout'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_items_layout'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::podcastGroupItemLayoutOptions())
                                                            ->default('cards')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.items_grid_columns')
                                                            ->label(__('admin.fields.podcasts_group_page_items_grid_columns'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_items_grid_columns'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::podcastGroupItemGridColumnOptions())
                                                            ->default(3)
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.items_grid_gap')
                                                            ->label(__('admin.fields.podcasts_group_page_items_grid_gap'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_items_grid_gap'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::podcastGroupItemGridGapOptions())
                                                            ->default('comfortable')
                                                            ->native(false)
                                                            ->required(),
                                                    ])
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                                Fieldset::make(__('admin.sections.public_front_podcasts_group_items_controls'))
                                                    ->schema([
                                                        Toggle::make('podcasts_page.group_page.search_enabled')
                                                            ->label(__('admin.fields.podcasts_group_page_search_enabled'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_search_enabled')),
                                                        Toggle::make('podcasts_page.group_page.category_filter_enabled')
                                                            ->label(__('admin.fields.podcasts_group_page_category_filter_enabled'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_category_filter_enabled')),
                                                        Toggle::make('podcasts_page.group_page.sort_enabled')
                                                            ->label(__('admin.fields.podcasts_group_page_sort_enabled'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_sort_enabled')),
                                                        Select::make('podcasts_page.group_page.default_sort')
                                                            ->label(__('admin.fields.podcasts_group_page_default_sort'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_default_sort'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::podcastGroupItemSortOptions())
                                                            ->default('latest_transcription')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.sort_options')
                                                            ->label(__('admin.fields.podcasts_group_page_sort_options'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_sort_options'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::podcastGroupItemSortOptions())
                                                            ->multiple()
                                                            ->native(false)
                                                            ->required(),
                                                        TextInput::make('podcasts_page.group_page.items_per_page')
                                                            ->label(__('admin.fields.podcasts_group_page_items_per_page'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_items_per_page'))
                                                            ->required()
                                                            ->numeric()
                                                            ->integer()
                                                            ->minValue(1)
                                                            ->maxValue(48),
                                                        Select::make('podcasts_page.group_page.page_size_options')
                                                            ->label(__('admin.fields.podcasts_group_page_page_size_options'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_page_size_options'))
                                                            ->options([
                                                                6 => '6',
                                                                9 => '9',
                                                                12 => '12',
                                                                15 => '15',
                                                                18 => '18',
                                                                24 => '24',
                                                                36 => '36',
                                                                48 => '48',
                                                            ])
                                                            ->multiple()
                                                            ->native(false)
                                                            ->required(),
                                                        Toggle::make('podcasts_page.group_page.per_page_selector_enabled')
                                                            ->label(__('admin.fields.podcasts_group_page_per_page_selector_enabled'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_per_page_selector_enabled')),
                                                    ])
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                                Fieldset::make(__('admin.sections.public_front_podcasts_group_item_cards'))
                                                    ->schema([
                                                        Select::make('podcasts_page.group_page.item_density')
                                                            ->label(__('admin.fields.podcasts_group_page_item_density'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_item_density'))
                                                            ->options([
                                                                'compact' => __('admin.card_density.compact'),
                                                                'comfortable' => __('admin.card_density.comfortable'),
                                                            ])
                                                            ->default('comfortable')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.item_image_size')
                                                            ->label(__('admin.fields.podcasts_group_page_item_image_size'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_item_image_size'))
                                                            ->options([
                                                                'hidden' => __('admin.card_image_size.hidden'),
                                                                'small' => __('admin.card_image_size.small'),
                                                                'medium' => __('admin.card_image_size.medium'),
                                                                'large' => __('admin.card_image_size.large'),
                                                            ])
                                                            ->default('medium')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.item_image_fit')
                                                            ->label(__('admin.fields.podcasts_group_page_item_image_fit'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_item_image_fit'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::imageFitOptions())
                                                            ->default('cover')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.item_image_radius')
                                                            ->label(__('admin.fields.podcasts_group_page_item_image_radius'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_item_image_radius'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::imageRadiusOptions())
                                                            ->default('mid_rounded')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.item_title_size')
                                                            ->label(__('admin.fields.podcasts_group_page_item_title_size'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_item_title_size'))
                                                            ->options([
                                                                'sm' => __('admin.card_title_size.sm'),
                                                                'base' => __('admin.card_title_size.base'),
                                                                'lg' => __('admin.card_title_size.lg'),
                                                            ])
                                                            ->default('base')
                                                            ->native(false)
                                                            ->required(),
                                                        Select::make('podcasts_page.group_page.transcription_display')
                                                            ->label(__('admin.fields.public_front_transcription_display'))
                                                            ->helperText(__('admin.helpers.public_front_transcription_display'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::transcriptionDisplayOptions())
                                                            ->default('effective_only')
                                                            ->native(false)
                                                            ->required(),
                                                        Toggle::make('podcasts_page.group_page.show_episode_authors')
                                                            ->label(__('admin.fields.podcasts_group_page_show_episode_authors'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_episode_authors')),
                                                        Toggle::make('podcasts_page.group_page.show_episode_tags')
                                                            ->label(__('admin.fields.podcasts_group_page_show_episode_tags'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_episode_tags')),
                                                        Toggle::make('podcasts_page.group_page.show_episode_duration')
                                                            ->label(__('admin.fields.podcasts_group_page_show_episode_duration'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_episode_duration')),
                                                        Toggle::make('podcasts_page.group_page.show_episode_effective_date')
                                                            ->label(__('admin.fields.podcasts_group_page_show_episode_effective_date'))
                                                            ->helperText(__('admin.helpers.podcasts_group_page_show_episode_effective_date')),
                                                    ])
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.contributors'))
                            ->id('contributors')
                            ->key('public-settings-tab-contributors')
                            ->schema([
                                Section::make(__('admin.sections.public_front_contributors_page'))
                                    ->description(__('admin.descriptions.public_front_contributors_page'))
                                    ->schema([
                                        Fieldset::make(__('admin.sections.public_front_contributors_identity'))
                                            ->schema([
                                                Toggle::make('contributors_page.enabled')
                                                    ->label(__('admin.fields.contributors_page_enabled'))
                                                    ->helperText(__('admin.helpers.contributors_page_enabled')),
                                                TextInput::make('contributors_page.title')
                                                    ->label(__('admin.fields.contributors_page_title'))
                                                    ->helperText(__('admin.helpers.contributors_page_title'))
                                                    ->required()
                                                    ->maxLength(160),
                                                Textarea::make('contributors_page.description')
                                                    ->label(__('admin.fields.contributors_page_description'))
                                                    ->helperText(__('admin.helpers.contributors_page_description'))
                                                    ->rows(3)
                                                    ->maxLength(1000)
                                                    ->columnSpanFull(),
                                                TextInput::make('contributors_page.label_singular')
                                                    ->label(__('admin.fields.contributors_page_label_singular'))
                                                    ->helperText(__('admin.helpers.contributors_page_label_singular'))
                                                    ->required()
                                                    ->maxLength(80),
                                                TextInput::make('contributors_page.label_plural')
                                                    ->label(__('admin.fields.contributors_page_label_plural'))
                                                    ->helperText(__('admin.helpers.contributors_page_label_plural'))
                                                    ->required()
                                                    ->maxLength(80),
                                                TextInput::make('contributors_page.item_label_singular')
                                                    ->label(__('admin.fields.contributors_page_item_label_singular'))
                                                    ->helperText(__('admin.helpers.contributors_page_item_label_singular'))
                                                    ->required()
                                                    ->maxLength(80),
                                                TextInput::make('contributors_page.item_label_plural')
                                                    ->label(__('admin.fields.contributors_page_item_label_plural'))
                                                    ->helperText(__('admin.helpers.contributors_page_item_label_plural'))
                                                    ->required()
                                                    ->maxLength(80),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.public_front_contributors_directory'))
                                            ->schema([
                                                Select::make('contributors_page.directory.default_sort')
                                                    ->label(__('admin.fields.contributors_directory_default_sort'))
                                                    ->helperText(__('admin.helpers.contributors_directory_default_sort'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorDirectorySortOptions())
                                                    ->default('count_desc')
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.directory.sort_options')
                                                    ->label(__('admin.fields.contributors_directory_sort_options'))
                                                    ->helperText(__('admin.helpers.contributors_directory_sort_options'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorDirectorySortOptions())
                                                    ->multiple()
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.directory.default_per_page')
                                                    ->label(__('admin.fields.contributors_directory_default_per_page'))
                                                    ->helperText(__('admin.helpers.contributors_directory_default_per_page'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorDirectoryPageSizeOptions())
                                                    ->default(10)
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.directory.per_page_options')
                                                    ->label(__('admin.fields.contributors_directory_per_page_options'))
                                                    ->helperText(__('admin.helpers.contributors_directory_per_page_options'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorDirectoryPageSizeOptions())
                                                    ->multiple()
                                                    ->native(false)
                                                    ->required(),
                                                TextInput::make('contributors_page.directory.preview_items_per_page')
                                                    ->label(__('admin.fields.contributors_directory_preview_items_per_page'))
                                                    ->helperText(__('admin.helpers.contributors_directory_preview_items_per_page'))
                                                    ->required()
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->maxValue(24),
                                                Select::make('contributors_page.directory.preview_grid_columns')
                                                    ->label(__('admin.fields.contributors_directory_preview_grid_columns'))
                                                    ->helperText(__('admin.helpers.contributors_directory_preview_grid_columns'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorGridColumnOptions())
                                                    ->default(3)
                                                    ->native(false)
                                                    ->required(),
                                                Toggle::make('contributors_page.directory.preview_search_enabled')
                                                    ->label(__('admin.fields.contributors_directory_preview_search_enabled'))
                                                    ->helperText(__('admin.helpers.contributors_directory_preview_search_enabled')),
                                                Select::make('contributors_page.directory.transcription_display')
                                                    ->label(__('admin.fields.public_front_transcription_display'))
                                                    ->helperText(__('admin.helpers.public_front_transcription_display'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::transcriptionDisplayOptions())
                                                    ->default('effective_only')
                                                    ->native(false)
                                                    ->required(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.public_front_top_transcribers'))
                                            ->schema([
                                                Toggle::make('contributors_page.top_transcribers.enabled')
                                                    ->label(__('admin.fields.top_transcribers_enabled'))
                                                    ->helperText(__('admin.helpers.top_transcribers_enabled')),
                                                TextInput::make('contributors_page.top_transcribers.limit')
                                                    ->label(__('admin.fields.top_transcribers_limit'))
                                                    ->helperText(__('admin.helpers.top_transcribers_limit'))
                                                    ->required()
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->maxValue(24),
                                                Select::make('contributors_page.top_transcribers.layout')
                                                    ->label(__('admin.fields.top_transcribers_layout'))
                                                    ->helperText(__('admin.helpers.top_transcribers_layout'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::topTranscriberLayoutOptions())
                                                    ->default('horizontal')
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.top_transcribers.preview_default_page_size')
                                                    ->label(__('admin.fields.top_transcribers_preview_default_page_size'))
                                                    ->helperText(__('admin.helpers.top_transcribers_preview_default_page_size'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::topTranscriberPreviewPageSizeOptions())
                                                    ->default(5)
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.top_transcribers.preview_page_size_options')
                                                    ->label(__('admin.fields.top_transcribers_preview_page_size_options'))
                                                    ->helperText(__('admin.helpers.top_transcribers_preview_page_size_options'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::topTranscriberPreviewPageSizeOptions())
                                                    ->multiple()
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.top_transcribers.preview_grid_columns')
                                                    ->label(__('admin.fields.top_transcribers_preview_grid_columns'))
                                                    ->helperText(__('admin.helpers.top_transcribers_preview_grid_columns'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorGridColumnOptions())
                                                    ->default(3)
                                                    ->native(false)
                                                    ->required(),
                                                Toggle::make('contributors_page.top_transcribers.show_full_page_link')
                                                    ->label(__('admin.fields.top_transcribers_show_full_page_link'))
                                                    ->helperText(__('admin.helpers.top_transcribers_show_full_page_link')),
                                                Toggle::make('contributors_page.top_transcribers.show_count_badge')
                                                    ->label(__('admin.fields.top_transcribers_show_count_badge'))
                                                    ->helperText(__('admin.helpers.top_transcribers_show_count_badge')),
                                                Select::make('contributors_page.top_transcribers.transcription_display')
                                                    ->label(__('admin.fields.public_front_transcription_display'))
                                                    ->helperText(__('admin.helpers.public_front_transcription_display'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::transcriptionDisplayOptions())
                                                    ->default('effective_only')
                                                    ->native(false)
                                                    ->required(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.public_front_contributor_cards'))
                                            ->schema([
                                                Toggle::make('contributors_page.cards.compact_show_count')
                                                    ->label(__('admin.fields.contributor_cards_compact_show_count'))
                                                    ->helperText(__('admin.helpers.contributor_cards_compact_show_count')),
                                                Select::make('contributors_page.cards.compact_count_icon')
                                                    ->label(__('admin.fields.contributor_cards_compact_count_icon'))
                                                    ->helperText(__('admin.helpers.contributor_cards_compact_count_icon'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorCardIconOptions())
                                                    ->default('document-text')
                                                    ->native(false)
                                                    ->required(),
                                                Toggle::make('contributors_page.cards.preview_show_bio')
                                                    ->label(__('admin.fields.contributor_cards_preview_show_bio'))
                                                    ->helperText(__('admin.helpers.contributor_cards_preview_show_bio')),
                                                Toggle::make('contributors_page.cards.preview_show_counts')
                                                    ->label(__('admin.fields.contributor_cards_preview_show_counts'))
                                                    ->helperText(__('admin.helpers.contributor_cards_preview_show_counts')),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.public_front_contributor_page_items'))
                                            ->schema([
                                                TextInput::make('contributors_page.page.items_per_page')
                                                    ->label(__('admin.fields.contributor_page_items_per_page'))
                                                    ->helperText(__('admin.helpers.contributor_page_items_per_page'))
                                                    ->required()
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->maxValue(48),
                                                Select::make('contributors_page.page.page_size_options')
                                                    ->label(__('admin.fields.contributor_page_page_size_options'))
                                                    ->helperText(__('admin.helpers.contributor_page_page_size_options'))
                                                    ->options([
                                                        6 => '6',
                                                        12 => '12',
                                                        18 => '18',
                                                        24 => '24',
                                                        36 => '36',
                                                        48 => '48',
                                                    ])
                                                    ->multiple()
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.page.default_sort')
                                                    ->label(__('admin.fields.contributor_page_default_sort'))
                                                    ->helperText(__('admin.helpers.contributor_page_default_sort'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorItemSortOptions())
                                                    ->default('latest_transcription')
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.page.sort_options')
                                                    ->label(__('admin.fields.contributor_page_sort_options'))
                                                    ->helperText(__('admin.helpers.contributor_page_sort_options'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorItemSortOptions())
                                                    ->multiple()
                                                    ->native(false)
                                                    ->required(),
                                                Toggle::make('contributors_page.page.search_enabled')
                                                    ->label(__('admin.fields.contributor_page_search_enabled'))
                                                    ->helperText(__('admin.helpers.contributor_page_search_enabled')),
                                                Select::make('contributors_page.page.grid_columns')
                                                    ->label(__('admin.fields.contributor_page_grid_columns'))
                                                    ->helperText(__('admin.helpers.contributor_page_grid_columns'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::contributorGridColumnOptions())
                                                    ->default(3)
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.page.grid_gap')
                                                    ->label(__('admin.fields.contributor_page_grid_gap'))
                                                    ->helperText(__('admin.helpers.contributor_page_grid_gap'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::podcastGroupItemGridGapOptions())
                                                    ->default('comfortable')
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('contributors_page.page.transcription_display')
                                                    ->label(__('admin.fields.public_front_transcription_display'))
                                                    ->helperText(__('admin.helpers.public_front_transcription_display'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::transcriptionDisplayOptions())
                                                    ->default('effective_only')
                                                    ->native(false)
                                                    ->required(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.about'))
                            ->id('about')
                            ->key('public-settings-tab-about')
                            ->schema([
                                Section::make(__('admin.sections.public_front_about_page'))
                                    ->description(__('admin.descriptions.public_front_about_page'))
                                    ->schema([
                                        Fieldset::make(__('admin.sections.about_page_identity'))
                                            ->schema([
                                                Toggle::make('about_page.enabled')
                                                    ->label(__('admin.fields.about_page_enabled'))
                                                    ->helperText(__('admin.helpers.about_page_enabled'))
                                                    ->default(false),
                                                TextInput::make('about_page.title')
                                                    ->label(__('admin.fields.about_page_title'))
                                                    ->helperText(__('admin.helpers.about_page_title'))
                                                    ->required()
                                                    ->maxLength(160),
                                                TextInput::make('about_page.kicker')
                                                    ->label(__('admin.fields.about_page_kicker'))
                                                    ->helperText(__('admin.helpers.about_page_kicker'))
                                                    ->maxLength(120),
                                                Textarea::make('about_page.description')
                                                    ->label(__('admin.fields.about_page_description'))
                                                    ->helperText(__('admin.helpers.about_page_description'))
                                                    ->rows(3)
                                                    ->maxLength(1000)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.about_page_team_defaults'))
                                            ->schema([
                                                TextInput::make('about_page.settings.team_heading')
                                                    ->label(__('admin.fields.about_page_team_heading'))
                                                    ->helperText(__('admin.helpers.about_page_team_heading'))
                                                    ->maxLength(160),
                                                Select::make('about_page.settings.team_layout')
                                                    ->label(__('admin.fields.about_page_team_layout'))
                                                    ->helperText(__('admin.helpers.about_page_team_layout'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::aboutTeamLayoutOptions())
                                                    ->default('grid')
                                                    ->native(false)
                                                    ->required(),
                                                Textarea::make('about_page.settings.team_description')
                                                    ->label(__('admin.fields.about_page_team_description'))
                                                    ->helperText(__('admin.helpers.about_page_team_description'))
                                                    ->rows(3)
                                                    ->maxLength(1000)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                        Fieldset::make(__('admin.sections.about_page_team_card'))
                                            ->schema([
                                                Toggle::make('about_page.settings.team_card.show_image')
                                                    ->label(__('admin.fields.about_team_card_show_image'))
                                                    ->helperText(__('admin.helpers.about_team_card_show_image')),
                                                Select::make('about_page.settings.team_card.image_size')
                                                    ->label(__('admin.fields.about_team_card_image_size'))
                                                    ->helperText(__('admin.helpers.about_team_card_image_size'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::aboutTeamCardImageSizeOptions())
                                                    ->default('medium')
                                                    ->native(false),
                                                Select::make('about_page.settings.team_card.image_fit')
                                                    ->label(__('admin.fields.about_team_card_image_fit'))
                                                    ->helperText(__('admin.helpers.about_team_card_image_fit'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::imageFitOptions())
                                                    ->default('cover')
                                                    ->native(false),
                                                Select::make('about_page.settings.team_card.image_radius')
                                                    ->label(__('admin.fields.about_team_card_image_radius'))
                                                    ->helperText(__('admin.helpers.about_team_card_image_radius'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::imageRadiusOptions())
                                                    ->default('circle')
                                                    ->native(false),
                                                Select::make('about_page.settings.team_card.layout')
                                                    ->label(__('admin.fields.about_team_card_layout'))
                                                    ->helperText(__('admin.helpers.about_team_card_layout'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::aboutTeamLayoutOptions())
                                                    ->default('grid')
                                                    ->native(false),
                                                Select::make('about_page.settings.team_card.density')
                                                    ->label(__('admin.fields.about_team_card_density'))
                                                    ->helperText(__('admin.helpers.about_team_card_density'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::aboutTeamCardDensityOptions())
                                                    ->default('comfortable')
                                                    ->native(false),
                                                Toggle::make('about_page.settings.team_card.show_title')
                                                    ->label(__('admin.fields.about_team_card_show_title'))
                                                    ->helperText(__('admin.helpers.about_team_card_show_title')),
                                                Toggle::make('about_page.settings.team_card.show_description')
                                                    ->label(__('admin.fields.about_team_card_show_description'))
                                                    ->helperText(__('admin.helpers.about_team_card_show_description')),
                                                TextInput::make('about_page.settings.team_card.description_lines')
                                                    ->label(__('admin.fields.about_team_card_description_lines'))
                                                    ->helperText(__('admin.helpers.about_team_card_description_lines'))
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(6),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                        Builder::make('about_page.blocks')
                                            ->label(__('admin.fields.about_page_blocks'))
                                            ->helperText(__('admin.helpers.about_page_blocks'))
                                            ->blocks($this->aboutPageBlockBlocks())
                                            ->blockPickerColumns(2)
                                            ->collapsible()
                                            ->collapsed()
                                            ->cloneable()
                                            ->default([])
                                            ->addActionLabel(__('admin.actions.add_about_page_block'))
                                            ->columnSpanFull(),
                                        Repeater::make('about_page.team_profiles')
                                            ->label(__('admin.fields.about_page_team_profiles'))
                                            ->helperText(__('admin.helpers.about_page_team_profiles'))
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label(__('admin.fields.about_team_profile_key'))
                                                    ->helperText(__('admin.helpers.about_team_profile_key'))
                                                    ->required()
                                                    ->maxLength(80)
                                                    ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                                                Toggle::make('visible')
                                                    ->label(__('admin.fields.about_team_profile_visible'))
                                                    ->helperText(__('admin.helpers.about_team_profile_visible'))
                                                    ->default(true),
                                                TextInput::make('sort')
                                                    ->label(__('admin.fields.about_team_profile_sort'))
                                                    ->helperText(__('admin.helpers.about_team_profile_sort'))
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(1000),
                                                FileUpload::make('image_path')
                                                    ->label(__('admin.fields.about_team_profile_image'))
                                                    ->helperText(__('admin.helpers.about_team_profile_image'))
                                                    ->disk('public')
                                                    ->directory('team')
                                                    ->visibility('public')
                                                    ->avatar()
                                                    ->acceptedFileTypes(PublicAboutPageRegistry::acceptedImageTypes())
                                                    ->maxSize(PublicAboutPageRegistry::maxImageSize()),
                                                TextInput::make('name')
                                                    ->label(__('admin.fields.about_team_profile_name'))
                                                    ->helperText(__('admin.helpers.about_team_profile_name'))
                                                    ->required()
                                                    ->maxLength(120),
                                                TextInput::make('title')
                                                    ->label(__('admin.fields.about_team_profile_title'))
                                                    ->helperText(__('admin.helpers.about_team_profile_title'))
                                                    ->maxLength(120),
                                                Textarea::make('description')
                                                    ->label(__('admin.fields.about_team_profile_description'))
                                                    ->helperText(__('admin.helpers.about_team_profile_description'))
                                                    ->rows(3)
                                                    ->maxLength(1000)
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['key'] ?? __('admin.labels.untitled'))
                                            ->defaultItems(0)
                                            ->reorderable()
                                            ->cloneable()
                                            ->collapsed()
                                            ->grid(['md' => 2])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.forms'))
                            ->id('forms')
                            ->key('public-settings-tab-forms')
                            ->schema([
                                Section::make(__('admin.sections.public_front_forms'))
                                    ->description(__('admin.descriptions.public_front_forms'))
                                    ->schema([
                                        Repeater::make('public_forms.definitions')
                                            ->label(__('admin.fields.public_forms'))
                                            ->helperText(__('admin.helpers.public_forms'))
                                            ->schema([
                                                Fieldset::make(__('admin.sections.public_form_identity'))
                                                    ->schema([
                                                        TextInput::make('key')
                                                            ->label(__('admin.fields.public_form_key'))
                                                            ->helperText(__('admin.helpers.public_form_key'))
                                                            ->required()
                                                            ->maxLength(80)
                                                            ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                                                        TextInput::make('name')
                                                            ->label(__('admin.fields.public_form_name'))
                                                            ->helperText(__('admin.helpers.public_form_name'))
                                                            ->required()
                                                            ->maxLength(120),
                                                        TextInput::make('heading')
                                                            ->label(__('admin.fields.public_form_heading'))
                                                            ->helperText(__('admin.helpers.public_form_heading'))
                                                            ->maxLength(160),
                                                        Select::make('display_mode_default')
                                                            ->label(__('admin.fields.public_form_display_mode'))
                                                            ->helperText(__('admin.helpers.public_form_display_mode'))
                                                            ->options(fn (): array => PublicFrontConfigRegistry::publicFormDisplayModeOptions())
                                                            ->default('modal')
                                                            ->native(false)
                                                            ->required(),
                                                        Toggle::make('enabled')
                                                            ->label(__('admin.fields.public_form_enabled'))
                                                            ->helperText(__('admin.helpers.public_form_enabled'))
                                                            ->default(false),
                                                    ])
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                                Fieldset::make(__('admin.sections.public_form_behavior'))
                                                    ->schema([
                                                        TextInput::make('submit_label')
                                                            ->label(__('admin.fields.public_form_submit_label'))
                                                            ->helperText(__('admin.helpers.public_form_submit_label'))
                                                            ->maxLength(80),
                                                        TextInput::make('success_message')
                                                            ->label(__('admin.fields.public_form_success_message'))
                                                            ->helperText(__('admin.helpers.public_form_success_message'))
                                                            ->maxLength(240)
                                                            ->columnSpanFull(),
                                                        Textarea::make('description')
                                                            ->label(__('admin.fields.public_form_description'))
                                                            ->helperText(__('admin.helpers.public_form_description'))
                                                            ->rows(3)
                                                            ->maxLength(1000)
                                                            ->columnSpanFull(),
                                                        TextInput::make('settings.rate_limit_attempts')
                                                            ->label(__('admin.fields.public_form_rate_limit_attempts'))
                                                            ->helperText(__('admin.helpers.public_form_rate_limit_attempts'))
                                                            ->numeric()
                                                            ->integer()
                                                            ->minValue(1)
                                                            ->maxValue(30)
                                                            ->default(5),
                                                        TextInput::make('settings.rate_limit_decay_seconds')
                                                            ->label(__('admin.fields.public_form_rate_limit_decay_seconds'))
                                                            ->helperText(__('admin.helpers.public_form_rate_limit_decay_seconds'))
                                                            ->numeric()
                                                            ->integer()
                                                            ->minValue(60)
                                                            ->maxValue(86400)
                                                            ->default(600),
                                                    ])
                                                    ->columns(2)
                                                    ->columnSpanFull(),
                                                Fieldset::make(__('admin.sections.public_form_fields_config'))
                                                    ->schema([
                                                        Builder::make('fields')
                                                            ->label(__('admin.fields.public_form_fields'))
                                                            ->helperText(__('admin.helpers.public_form_fields'))
                                                            ->blocks($this->publicFormFieldBlocks())
                                                            ->blockPickerColumns(2)
                                                            ->collapsible()
                                                            ->collapsed()
                                                            ->cloneable()
                                                            ->default([])
                                                            ->addActionLabel(__('admin.actions.add_public_form_field'))
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['key'] ?? __('admin.labels.untitled'))
                                            ->defaultItems(0)
                                            ->reorderable()
                                            ->cloneable()
                                            ->collapsed()
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('admin.tabs.public_content_settings.advanced'))
                            ->id('advanced')
                            ->key('public-settings-tab-advanced')
                            ->schema([
                                Section::make(__('admin.sections.public_front_card_templates'))
                                    ->description(__('admin.descriptions.public_front_card_templates'))
                                    ->schema([
                                        Repeater::make('card_templates')
                                            ->label(__('admin.fields.public_front_card_templates'))
                                            ->helperText(__('admin.helpers.public_front_card_templates'))
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label(__('admin.fields.card_template_key'))
                                                    ->helperText(__('admin.helpers.card_template_key'))
                                                    ->required()
                                                    ->maxLength(80)
                                                    ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                                                TextInput::make('label')
                                                    ->label(__('admin.fields.card_template_label'))
                                                    ->helperText(__('admin.helpers.card_template_label'))
                                                    ->required()
                                                    ->maxLength(120),
                                                Select::make('family')
                                                    ->label(__('admin.fields.card_template_family'))
                                                    ->helperText(__('admin.helpers.card_template_family'))
                                                    ->options(fn (): array => PublicFrontConfigRegistry::cardFamilyOptions())
                                                    ->native(false)
                                                    ->live()
                                                    ->required(),
                                                Select::make('layout')
                                                    ->label(__('admin.fields.card_template_layout'))
                                                    ->helperText(__('admin.helpers.card_template_layout'))
                                                    ->options([
                                                        'cards' => __('admin.layouts.cards'),
                                                        'rows' => __('admin.layouts.rows'),
                                                    ])
                                                    ->native(false)
                                                    ->default('cards')
                                                    ->required(),
                                                Select::make('density')
                                                    ->label(__('admin.fields.card_template_density'))
                                                    ->helperText(__('admin.helpers.card_template_density'))
                                                    ->options([
                                                        'compact' => __('admin.card_density.compact'),
                                                        'comfortable' => __('admin.card_density.comfortable'),
                                                    ])
                                                    ->native(false)
                                                    ->default('comfortable')
                                                    ->required(),
                                                Select::make('image_size')
                                                    ->label(__('admin.fields.card_template_image_size'))
                                                    ->helperText(__('admin.helpers.card_template_image_size'))
                                                    ->options([
                                                        'hidden' => __('admin.card_image_size.hidden'),
                                                        'small' => __('admin.card_image_size.small'),
                                                        'medium' => __('admin.card_image_size.medium'),
                                                        'large' => __('admin.card_image_size.large'),
                                                    ])
                                                    ->native(false)
                                                    ->default('medium')
                                                    ->required(),
                                                Select::make('title_size')
                                                    ->label(__('admin.fields.card_template_title_size'))
                                                    ->helperText(__('admin.helpers.card_template_title_size'))
                                                    ->options([
                                                        'sm' => __('admin.card_title_size.sm'),
                                                        'base' => __('admin.card_title_size.base'),
                                                        'lg' => __('admin.card_title_size.lg'),
                                                    ])
                                                    ->native(false)
                                                    ->default('base')
                                                    ->required(),
                                                Builder::make('parts')
                                                    ->label(__('admin.fields.card_template_parts'))
                                                    ->helperText(__('admin.helpers.card_template_parts'))
                                                    ->blocks($this->cardTemplatePartBlocks())
                                                    ->blockPickerColumns(2)
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->cloneable()
                                                    ->default([])
                                                    ->addActionLabel(__('admin.actions.add_card_template_part'))
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['key'] ?? __('admin.labels.untitled'))
                                            ->defaultItems(0)
                                            ->live()
                                            ->reorderable()
                                            ->cloneable()
                                            ->collapsed()
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function itemPageInfoFieldRepeater(): Repeater
    {
        return Repeater::make('item_page.info_fields')
            ->label(__('admin.fields.item_page_info_fields'))
            ->helperText(__('admin.helpers.item_page_info_fields'))
            ->schema([
                Select::make('field')
                    ->label(__('admin.fields.item_page_info_field_key'))
                    ->helperText(__('admin.helpers.item_page_info_field_key'))
                    ->options(fn (): array => PublicItemPageRegistry::infoFieldOptions())
                    ->native(false)
                    ->required(),
                Select::make('label_mode')
                    ->label(__('admin.fields.item_page_info_field_label_mode'))
                    ->helperText(__('admin.helpers.item_page_info_field_label_mode'))
                    ->options(fn (): array => PublicItemPageRegistry::labelModeOptions())
                    ->default('hidden')
                    ->native(false)
                    ->required(),
                TextInput::make('label_override')
                    ->label(__('admin.fields.item_page_info_field_label_override'))
                    ->helperText(__('admin.helpers.item_page_info_field_label_override'))
                    ->maxLength(80),
                Select::make('icon')
                    ->label(__('admin.fields.item_page_info_field_icon'))
                    ->helperText(__('admin.helpers.item_page_info_field_icon'))
                    ->options(fn (): array => PublicFrontCardTemplateRegistry::iconOptions())
                    ->default('document')
                    ->native(false)
                    ->required(),
                Select::make('icon_position')
                    ->label(__('admin.fields.item_page_info_field_icon_position'))
                    ->helperText(__('admin.helpers.item_page_info_field_icon_position'))
                    ->options(fn (): array => PublicFrontCardTemplateRegistry::iconPositionOptions())
                    ->default('inline_before')
                    ->native(false)
                    ->required(),
                Select::make('size')
                    ->label(__('admin.fields.item_page_info_field_size'))
                    ->helperText(__('admin.helpers.item_page_info_field_size'))
                    ->options(fn (): array => PublicItemPageRegistry::badgeSizeOptions())
                    ->default('sm')
                    ->native(false)
                    ->required(),
                Select::make('color')
                    ->label(__('admin.fields.item_page_info_field_color'))
                    ->helperText(__('admin.helpers.item_page_info_field_color'))
                    ->options(fn (): array => PublicItemPageRegistry::badgeColorOptions())
                    ->default('gray')
                    ->native(false)
                    ->required(),
            ])
            ->itemLabel(fn (array $state): ?string => filled($state['field'] ?? null)
                ? __('admin.item_page_info_fields.'.$state['field'])
                : __('admin.labels.untitled'))
            ->default(PublicItemPageRegistry::defaultInfoFields())
            ->defaultItems(0)
            ->reorderable()
            ->cloneable()
            ->collapsed()
            ->columns(3)
            ->columnSpanFull();
    }

    /**
     * @return array<int, Fieldset>
     */
    private function defaultImageFamilyFieldsets(): array
    {
        return collect(PublicFrontConfigRegistry::defaultImageFamilies())
            ->map(fn (string $family): Fieldset => Fieldset::make(__("admin.default_image_families.{$family}"))
                ->schema([
                    Select::make("default_images.{$family}.mode")
                        ->label(__('admin.fields.default_image_mode'))
                        ->helperText(__('admin.helpers.default_image_mode'))
                        ->options(fn (): array => PublicFrontConfigRegistry::defaultImageModeOptions())
                        ->default('inherit')
                        ->native(false)
                        ->live()
                        ->required(),
                    FileUpload::make("default_images.{$family}.path")
                        ->label(__('admin.fields.default_image_path'))
                        ->helperText(__('admin.helpers.default_image_path'))
                        ->disk('public')
                        ->directory(PublicFrontConfigRegistry::defaultImageDirectory())
                        ->visibility('public')
                        ->image()
                        ->imagePreviewHeight('160')
                        ->acceptedFileTypes(PublicFrontConfigRegistry::defaultImageAcceptedFileTypes())
                        ->maxSize(PublicFrontConfigRegistry::defaultImageMaxSize())
                        ->visible(fn (Get $get): bool => $get("default_images.{$family}.mode") === 'custom')
                        ->required(fn (Get $get): bool => $get("default_images.{$family}.mode") === 'custom')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull())
            ->values()
            ->all();
    }

    private function itemPageDateFieldset(string $dateKey, string $sectionKey, bool $withEnabled = false): Fieldset
    {
        $statePath = "item_page.dates.{$dateKey}";

        return Fieldset::make(__("admin.sections.{$sectionKey}"))
            ->schema([
                ...($withEnabled ? [
                    Toggle::make("{$statePath}.enabled")
                        ->label(__('admin.fields.item_page_transcription_date_enabled'))
                        ->helperText(__('admin.helpers.item_page_transcription_date_enabled'))
                        ->default(true),
                ] : []),
                Select::make("{$statePath}.label_mode")
                    ->label(__('admin.fields.item_page_date_label_mode'))
                    ->helperText(__('admin.helpers.item_page_date_label_mode'))
                    ->options(fn (): array => PublicItemPageRegistry::labelModeOptions())
                    ->default($dateKey === 'site_published' ? 'long' : 'short')
                    ->native(false)
                    ->required(),
                TextInput::make("{$statePath}.label_override")
                    ->label(__('admin.fields.item_page_date_label_override'))
                    ->helperText(__('admin.helpers.item_page_date_label_override'))
                    ->maxLength(80),
                Select::make("{$statePath}.icon")
                    ->label(__('admin.fields.item_page_date_icon'))
                    ->helperText(__('admin.helpers.item_page_date_icon'))
                    ->options(fn (): array => PublicFrontCardTemplateRegistry::iconOptions())
                    ->default($dateKey === 'transcription_date' ? 'document' : 'calendar')
                    ->native(false)
                    ->required(),
                Select::make("{$statePath}.icon_position")
                    ->label(__('admin.fields.item_page_date_icon_position'))
                    ->helperText(__('admin.helpers.item_page_date_icon_position'))
                    ->options(fn (): array => PublicFrontCardTemplateRegistry::iconPositionOptions())
                    ->default('inline_before')
                    ->native(false)
                    ->required(),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $publicFrontConfig = app(PublicFrontConfigReader::class)
            ->fromArray($data)
            ->config();

        $publicFrontConfig['about_page'] = $this->aboutPageForBuilder($publicFrontConfig['about_page'] ?? []);
        $publicFrontConfig['card_templates'] = $this->cardTemplatesForBuilder($publicFrontConfig['card_templates'] ?? []);
        $publicFrontConfig['public_forms'] = $this->publicFormsForBuilder($publicFrontConfig['public_forms'] ?? []);

        return [
            ...$data,
            ...$publicFrontConfig,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['about_page'] = $this->normalizeAboutPageUploadState($data['about_page'] ?? []);
        $data['menu_config'] = $this->normalizeMenuUploadState($data['menu_config'] ?? []);

        $publicFrontConfig = app(PublicFrontConfigValidator::class)
            ->validate($data)
            ->config();

        return [
            ...$data,
            ...$publicFrontConfig,
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $aboutPage
     * @return array<string, mixed>
     */
    private function normalizeAboutPageUploadState(mixed $aboutPage): array
    {
        if (! is_array($aboutPage)) {
            return [];
        }

        $aboutPage['blocks'] = collect($aboutPage['blocks'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(function (array $block): array {
                if (array_key_exists('data', $block) && is_array($block['data'])) {
                    $block['data']['image_path'] = $this->singleFileUploadPath($block['data']['image_path'] ?? null);

                    return $block;
                }

                $block['image_path'] = $this->singleFileUploadPath($block['image_path'] ?? null);

                return $block;
            })
            ->values()
            ->all();

        $aboutPage['team_profiles'] = collect($aboutPage['team_profiles'] ?? [])
            ->filter(fn (mixed $profile): bool => is_array($profile))
            ->map(function (array $profile): array {
                $profile['image_path'] = $this->singleFileUploadPath($profile['image_path'] ?? null);

                return $profile;
            })
            ->values()
            ->all();

        return $aboutPage;
    }

    /**
     * @param  array<string, mixed>|mixed  $menuConfig
     * @return array<string, mixed>
     */
    private function normalizeMenuUploadState(mixed $menuConfig): array
    {
        if (! is_array($menuConfig)) {
            return [];
        }

        if (is_array($menuConfig['logo'] ?? null)) {
            $menuConfig['logo']['light_path'] = $this->singleFileUploadPath($menuConfig['logo']['light_path'] ?? null);
            $menuConfig['logo']['dark_path'] = $this->singleFileUploadPath($menuConfig['logo']['dark_path'] ?? null);
        }

        return $menuConfig;
    }

    private function singleFileUploadPath(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $path) {
            if (is_string($path) && filled($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<Block>
     */
    private function cardTemplatePartBlocks(bool $allowGroups = true): array
    {
        return collect(PublicFrontConfigRegistry::cardPartTypes())
            ->when(! $allowGroups, fn ($types) => $types->reject(fn (string $type): bool => $type === 'part_group'))
            ->map(fn (string $type): Block => Block::make($type)
                ->label(__("admin.card_template_part_types.{$type}"))
                ->schema($this->cardTemplatePartSchema($type))
                ->columns(3))
            ->all();
    }

    /**
     * @return array<Block>
     */
    private function publicFormFieldBlocks(): array
    {
        return collect(PublicFrontConfigRegistry::publicFormFieldTypes())
            ->map(fn (string $type): Block => Block::make($type)
                ->label(__("admin.public_form_field_types.{$type}"))
                ->schema($this->publicFormFieldSchema($type))
                ->columns(3))
            ->all();
    }

    /**
     * @return array<Block>
     */
    private function aboutPageBlockBlocks(): array
    {
        return collect(PublicFrontConfigRegistry::aboutBlockTypes())
            ->map(fn (string $type): Block => Block::make($type)
                ->label(__("admin.about_block_types.{$type}"))
                ->schema($this->aboutPageBlockSchema($type))
                ->columns(3))
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function aboutPageBlockSchema(string $type): array
    {
        return [
            TextInput::make('key')
                ->label(__('admin.fields.about_block_key'))
                ->helperText(__('admin.helpers.about_block_key'))
                ->maxLength(80)
                ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
            Toggle::make('visible')
                ->label(__('admin.fields.about_block_visible'))
                ->helperText(__('admin.helpers.about_block_visible'))
                ->default(true),
            TextInput::make('sort')
                ->label(__('admin.fields.about_block_sort'))
                ->helperText(__('admin.helpers.about_block_sort'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(1000),
            Select::make('style')
                ->label(__('admin.fields.about_block_style'))
                ->helperText(__('admin.helpers.about_block_style'))
                ->options(fn (): array => PublicFrontConfigRegistry::aboutBlockStyleOptions())
                ->default('default')
                ->native(false),
            TextInput::make('heading')
                ->label(__('admin.fields.about_block_heading'))
                ->helperText(__('admin.helpers.about_block_heading'))
                ->required($type === 'heading')
                ->maxLength(160)
                ->visible(in_array($type, ['heading', 'markdown', 'rich_content', 'callout', 'form_cta', 'team_section'], true)),
            Textarea::make('body')
                ->label(__('admin.fields.about_block_body'))
                ->helperText(__('admin.helpers.about_block_body'))
                ->rows(3)
                ->maxLength(20000)
                ->visible(in_array($type, ['heading', 'image', 'form_cta', 'team_section'], true))
                ->columnSpanFull(),
            MarkdownEditor::make('content')
                ->label(__('admin.fields.about_block_content'))
                ->helperText(__('admin.helpers.about_block_content'))
                ->disableToolbarButtons(['attachFiles'])
                ->fileAttachments(false)
                ->required(in_array($type, ['markdown', 'callout'], true))
                ->maxLength(20000)
                ->visible(in_array($type, ['markdown', 'callout'], true))
                ->columnSpanFull(),
            RichEditor::make('rich_content')
                ->label(__('admin.fields.about_block_rich_content'))
                ->helperText(__('admin.helpers.about_block_rich_content'))
                ->json()
                ->fileAttachments(false)
                ->required($type === 'rich_content')
                ->visible($type === 'rich_content')
                ->columnSpanFull(),
            FileUpload::make('image_path')
                ->label(__('admin.fields.about_block_image'))
                ->helperText(__('admin.helpers.about_block_image'))
                ->disk('public')
                ->directory('about')
                ->visibility('public')
                ->image()
                ->acceptedFileTypes(PublicAboutPageRegistry::acceptedImageTypes())
                ->maxSize(PublicAboutPageRegistry::maxImageSize())
                ->required($type === 'image')
                ->visible($type === 'image'),
            TextInput::make('image_alt')
                ->label(__('admin.fields.about_block_image_alt'))
                ->helperText(__('admin.helpers.about_block_image_alt'))
                ->maxLength(160)
                ->visible($type === 'image'),
            Select::make('image_fit')
                ->label(__('admin.fields.about_block_image_fit'))
                ->helperText(__('admin.helpers.about_block_image_fit'))
                ->options(fn (): array => PublicFrontConfigRegistry::imageFitOptions())
                ->default('cover')
                ->native(false)
                ->visible($type === 'image'),
            Select::make('image_radius')
                ->label(__('admin.fields.about_block_image_radius'))
                ->helperText(__('admin.helpers.about_block_image_radius'))
                ->options(fn (): array => PublicFrontConfigRegistry::imageRadiusOptions())
                ->default('mid_rounded')
                ->native(false)
                ->visible($type === 'image'),
            Select::make('form_key')
                ->label(__('admin.fields.about_block_form_key'))
                ->helperText(__('admin.helpers.about_block_form_key'))
                ->options(fn (): array => $this->publicFormOptions())
                ->searchable()
                ->native(false)
                ->required($type === 'form_cta')
                ->visible($type === 'form_cta'),
            Select::make('display_mode')
                ->label(__('admin.fields.public_form_display_mode'))
                ->helperText(__('admin.helpers.public_form_display_mode'))
                ->options(fn (): array => PublicFrontConfigRegistry::publicFormDisplayModeOptions())
                ->default('modal')
                ->native(false)
                ->visible($type === 'form_cta'),
            TextInput::make('button_label')
                ->label(__('admin.fields.about_block_button_label'))
                ->helperText(__('admin.helpers.about_block_button_label'))
                ->maxLength(80)
                ->visible($type === 'form_cta'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function publicFormFieldSchema(string $type): array
    {
        $supportsTextLengths = in_array($type, ['text', 'email', 'phone', 'textarea', 'url'], true);
        $supportsOptions = in_array($type, ['select', 'checkbox'], true);

        return [
            TextInput::make('key')
                ->label(__('admin.fields.public_form_field_key'))
                ->helperText(__('admin.helpers.public_form_field_key'))
                ->required()
                ->maxLength(80)
                ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
            TextInput::make('label')
                ->label(__('admin.fields.public_form_field_label'))
                ->helperText(__('admin.helpers.public_form_field_label'))
                ->required()
                ->maxLength(120),
            Toggle::make('required')
                ->label(__('admin.fields.public_form_field_required'))
                ->helperText(__('admin.helpers.public_form_field_required'))
                ->default(false),
            TextInput::make('placeholder')
                ->label(__('admin.fields.public_form_field_placeholder'))
                ->helperText(__('admin.helpers.public_form_field_placeholder'))
                ->maxLength(160)
                ->visible($type !== 'checkbox'),
            TextInput::make('help_text')
                ->label(__('admin.fields.public_form_field_help_text'))
                ->helperText(__('admin.helpers.public_form_field_help_text'))
                ->maxLength(240),
            Select::make('validation_semantics')
                ->label(__('admin.fields.public_form_field_validation_semantics'))
                ->helperText(__('admin.helpers.public_form_field_validation_semantics'))
                ->options(fn (): array => PublicFrontConfigRegistry::publicFormValidationSemanticOptions())
                ->default('none')
                ->native(false),
            TextInput::make('min_length')
                ->label(__('admin.fields.public_form_field_min_length'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(5000)
                ->visible($supportsTextLengths),
            TextInput::make('max_length')
                ->label(__('admin.fields.public_form_field_max_length'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(5000)
                ->visible($supportsTextLengths),
            Repeater::make('options')
                ->label(__('admin.fields.public_form_field_options'))
                ->helperText(__('admin.helpers.public_form_field_options'))
                ->schema([
                    TextInput::make('value')
                        ->label(__('admin.fields.public_form_option_value'))
                        ->helperText(__('admin.helpers.public_form_option_value'))
                        ->required($type === 'select')
                        ->maxLength(80)
                        ->rules(['regex:/^[a-z][a-z0-9_-]*$/']),
                    TextInput::make('label')
                        ->label(__('admin.fields.public_form_option_label'))
                        ->required($type === 'select')
                        ->maxLength(120),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->cloneable()
                ->columns(2)
                ->visible($supportsOptions)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function cardTemplatePartSchema(string $type): array
    {
        $requiresSource = ! in_array($type, ['divider', 'spacer', 'part_group'], true);
        $isGroup = $type === 'part_group';

        return [
            Toggle::make('visible')
                ->label(__('admin.fields.card_template_part_visible'))
                ->helperText(__('admin.helpers.card_template_part_visible'))
                ->default(true),
            Select::make('source')
                ->label(__('admin.fields.card_template_part_source'))
                ->helperText(__('admin.helpers.card_template_part_source'))
                ->options(fn (): array => PublicFrontConfigRegistry::cardSourceOptions())
                ->default(PublicFrontCardTemplateRegistry::defaultSourceForPart($type))
                ->native(false)
                ->live()
                ->required($requiresSource)
                ->afterStateUpdated(fn (Set $set): mixed => $set('attribute', null))
                ->visible($requiresSource),
            Select::make('attribute')
                ->label(__('admin.fields.card_template_part_attribute'))
                ->helperText(__('admin.helpers.card_template_part_attribute'))
                ->options(fn (Get $get): array => PublicFrontConfigRegistry::cardAttributeOptions($get('source')))
                ->default(PublicFrontCardTemplateRegistry::defaultAttributeForPart($type))
                ->native(false)
                ->required($requiresSource)
                ->visible($requiresSource),
            TextInput::make('label')
                ->label(__('admin.fields.card_template_part_label'))
                ->helperText(__('admin.helpers.card_template_part_label'))
                ->maxLength(80),
            Select::make('label_position')
                ->label(__('admin.fields.card_template_part_label_position'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::labelPositionOptions())
                ->native(false),
            Select::make('label_alignment')
                ->label(__('admin.fields.card_template_part_label_alignment'))
                ->helperText(__('admin.helpers.card_template_part_label_alignment'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::labelAlignmentOptions())
                ->default('start')
                ->native(false),
            Select::make('icon')
                ->label(__('admin.fields.card_template_part_icon'))
                ->helperText(__('admin.helpers.card_template_part_icon'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::iconOptions())
                ->native(false),
            Select::make('icon_position')
                ->label(__('admin.fields.card_template_part_icon_position'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::iconPositionOptions())
                ->native(false),
            Select::make('layout')
                ->label(__('admin.fields.card_template_part_layout'))
                ->helperText(__('admin.helpers.card_template_part_layout'))
                ->options(fn (): array => $isGroup
                    ? PublicFrontCardTemplateRegistry::groupLayoutOptions()
                    : PublicFrontCardTemplateRegistry::partLayoutOptions())
                ->default('inline')
                ->native(false),
            Select::make('columns')
                ->label(__('admin.fields.card_template_part_group_columns'))
                ->helperText(__('admin.helpers.card_template_part_group_columns'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::groupColumnOptions())
                ->default('auto')
                ->native(false)
                ->visible($isGroup),
            Select::make('gap')
                ->label(__('admin.fields.card_template_part_group_gap'))
                ->helperText(__('admin.helpers.card_template_part_group_gap'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::groupGapOptions())
                ->default('compact')
                ->native(false)
                ->visible($isGroup),
            Select::make('alignment')
                ->label(__('admin.fields.card_template_part_group_alignment'))
                ->helperText(__('admin.helpers.card_template_part_group_alignment'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::groupAlignmentOptions())
                ->default('start')
                ->native(false)
                ->visible($isGroup),
            TextInput::make('order')
                ->label(__('admin.fields.card_template_part_order'))
                ->helperText(__('admin.helpers.card_template_part_order'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(1000),
            Select::make('line_clamp')
                ->label(__('admin.fields.card_template_part_line_clamp'))
                ->helperText(__('admin.helpers.card_template_part_line_clamp'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::lineClampOptions())
                ->native(false),
            Select::make('font_size')
                ->label(__('admin.fields.card_template_part_font_size'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::fontSizeOptions())
                ->native(false),
            Select::make('url_target')
                ->label(__('admin.fields.card_template_part_url_target'))
                ->options(fn (): array => PublicFrontCardTemplateRegistry::urlTargetOptions())
                ->native(false),
            TextInput::make('text')
                ->label(__('admin.fields.card_template_part_text'))
                ->helperText(__('admin.helpers.card_template_part_text'))
                ->maxLength(500)
                ->visible($type === 'custom_text'),
            ...($isGroup ? [
                Builder::make('children')
                    ->label(__('admin.fields.card_template_part_group_children'))
                    ->helperText(__('admin.helpers.card_template_part_group_children'))
                    ->blocks($this->cardTemplatePartBlocks(allowGroups: false))
                    ->blockPickerColumns(2)
                    ->collapsible()
                    ->collapsed()
                    ->cloneable()
                    ->default([])
                    ->addActionLabel(__('admin.actions.add_card_template_part'))
                    ->columnSpanFull(),
            ] : []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array<string, mixed>>
     */
    private function cardTemplatesForBuilder(array $templates): array
    {
        return collect($templates)
            ->map(function (array $template): array {
                $template['parts'] = collect($template['parts'] ?? [])
                    ->filter(fn (mixed $part): bool => is_array($part))
                    ->map(fn (array $part): array => $this->cardTemplatePartForBuilder($part))
                    ->values()
                    ->all();

                return $template;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array{type: string, data: array<string, mixed>}
     */
    private function cardTemplatePartForBuilder(array $part): array
    {
        $data = Arr::except($part, ['type']);

        if (isset($data['children']) && is_array($data['children'])) {
            $data['children'] = collect($data['children'])
                ->filter(fn (mixed $child): bool => is_array($child))
                ->map(fn (array $child): array => $this->cardTemplatePartForBuilder($child))
                ->values()
                ->all();
        }

        return [
            'type' => $part['type'] ?? 'custom_text',
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $aboutPage
     * @return array<string, mixed>
     */
    private function aboutPageForBuilder(array $aboutPage): array
    {
        $aboutPage['blocks'] = collect($aboutPage['blocks'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(function (array $block): array {
                if (in_array($block['type'] ?? null, ['markdown', 'callout'], true) && filled($block['body'] ?? null)) {
                    $block['content'] ??= $block['body'];
                }

                return [
                    'type' => $block['type'] ?? 'markdown',
                    'data' => Arr::except($block, ['type']),
                ];
            })
            ->values()
            ->all();

        return $aboutPage;
    }

    /**
     * @return array<string, string>
     */
    private function publicFormOptions(): array
    {
        $publicForms = app(PublicFrontConfigReader::class)
            ->read()
            ->group('public_forms');

        $configuredOptions = collect($publicForms['definitions'] ?? [])
            ->filter(fn (mixed $definition): bool => is_array($definition) && filled($definition['key'] ?? null))
            ->mapWithKeys(fn (array $definition): array => [
                $definition['key'] => $definition['name'] ?? $definition['key'],
            ])
            ->all();

        $defaultOptions = collect(PublicFrontConfigRegistry::defaultMenuItems())
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'public_form' && filled($item['form_key'] ?? null))
            ->mapWithKeys(fn (array $item): array => [
                $item['form_key'] => $item['label'] ?? $item['form_key'],
            ])
            ->all();

        return [...$defaultOptions, ...$configuredOptions];
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $templates
     * @return array<string, string>
     */
    private function cardTemplateOptions(string $family, mixed $templates = null): array
    {
        $resolver = app(PublicFrontCardTemplateResolver::class);

        if (is_array($templates)) {
            $normalizedTemplates = app(PublicFrontConfigValidator::class)
                ->validate(['card_templates' => array_values($templates)])
                ->group('card_templates');

            return $resolver->optionsFromTemplates($normalizedTemplates, $family);
        }

        return $resolver->optionsForFamily($family);
    }

    /**
     * @param  array<string, mixed>  $publicForms
     * @return array<string, mixed>
     */
    private function publicFormsForBuilder(array $publicForms): array
    {
        $publicForms['definitions'] = collect($publicForms['definitions'] ?? [])
            ->filter(fn (mixed $definition): bool => is_array($definition))
            ->map(function (array $definition): array {
                $definition['fields'] = collect($definition['fields'] ?? [])
                    ->filter(fn (mixed $field): bool => is_array($field))
                    ->map(fn (array $field): array => [
                        'type' => $field['type'] ?? 'text',
                        'data' => Arr::except($field, ['type']),
                    ])
                    ->values()
                    ->all();

                return $definition;
            })
            ->values()
            ->all();

        return $publicForms;
    }
}
