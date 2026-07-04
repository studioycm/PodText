<?php

namespace App\Filament\Resources\HomepageSections\Schemas;

use App\Enums\HomepageSectionType;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Models\ContentItem;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\Sections\PublicDisplaySectionRegistry;
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
                                HomepageSectionType::TopTranscribers->value => HomepageSectionType::TopTranscribers->getLabel(),
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
                Section::make(__('admin.sections.homepage_source_config'))
                    ->description(__('admin.descriptions.homepage_source_config'))
                    ->schema([
                        Select::make('source_config.source_type')
                            ->label(__('admin.fields.public_display_source_type'))
                            ->helperText(__('admin.helpers.public_display_source_type'))
                            ->options(PublicDisplaySectionRegistry::sourceTypeOptions())
                            ->live()
                            ->searchable()
                            ->placeholder(__('admin.placeholders.use_legacy_homepage_type')),
                        Select::make('source_config.sort')
                            ->label(__('admin.fields.public_display_sort'))
                            ->helperText(__('admin.helpers.public_display_sort'))
                            ->options(PublicDisplaySectionRegistry::sortOptions())
                            ->searchable()
                            ->placeholder(__('admin.placeholders.use_source_default')),
                        Select::make('source_config.direction')
                            ->label(__('admin.fields.public_display_direction'))
                            ->helperText(__('admin.helpers.public_display_direction'))
                            ->options([
                                'desc' => __('admin.options.descending'),
                                'asc' => __('admin.options.ascending'),
                            ])
                            ->placeholder(__('admin.placeholders.use_source_default')),
                        TextInput::make('source_config.total_limit')
                            ->label(__('admin.fields.public_display_total_limit'))
                            ->helperText(__('admin.helpers.public_display_total_limit'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100),
                        Toggle::make('source_config.include_descendants')
                            ->label(__('admin.fields.include_descendants'))
                            ->helperText(__('admin.helpers.include_descendants'))
                            ->default(true),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.homepage_selection_config'))
                    ->description(__('admin.descriptions.homepage_selection_config'))
                    ->schema([
                        Select::make('selection_config.include_ids')
                            ->label(__('admin.fields.public_display_include_items'))
                            ->helperText(__('admin.helpers.public_display_include_items'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => self::contentItemOptions()),
                        Select::make('selection_config.exclude_ids')
                            ->label(__('admin.fields.public_display_exclude_items'))
                            ->helperText(__('admin.helpers.public_display_exclude_items'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => self::contentItemOptions()),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.homepage_display_config'))
                    ->description(__('admin.descriptions.homepage_display_config'))
                    ->schema([
                        TextInput::make('display_config.heading')
                            ->label(__('admin.fields.public_display_heading'))
                            ->helperText(__('admin.helpers.public_display_heading'))
                            ->maxLength(160),
                        Toggle::make('display_config.show_heading')
                            ->label(__('admin.fields.public_display_show_heading'))
                            ->helperText(__('admin.helpers.public_display_show_heading'))
                            ->default(true),
                        Toggle::make('display_config.show_view_all_link')
                            ->label(__('admin.fields.public_display_show_view_all_link'))
                            ->helperText(__('admin.helpers.public_display_show_view_all_link'))
                            ->default(true),
                        Select::make('display_config.view_all_route_key')
                            ->label(__('admin.fields.public_display_view_all_route_key'))
                            ->helperText(__('admin.helpers.public_display_view_all_route_key'))
                            ->options(PublicFrontConfigRegistry::routeOptions())
                            ->searchable(),
                        Select::make('display_config.template_family')
                            ->label(__('admin.fields.card_template_family'))
                            ->helperText(__('admin.helpers.card_template_family'))
                            ->options(PublicFrontConfigRegistry::cardFamilyOptions())
                            ->live()
                            ->placeholder(fn (Get $get): ?string => self::defaultTemplateFamilyLabel($get)),
                        Select::make('display_config.template_key')
                            ->label(__('admin.fields.card_template_key'))
                            ->helperText(__('admin.helpers.public_display_template_key'))
                            ->options(fn (Get $get): array => self::cardTemplateOptions($get))
                            ->searchable(),
                        Select::make('display_config.template_overrides.layout')
                            ->label(__('admin.fields.card_template_layout'))
                            ->helperText(__('admin.helpers.card_template_layout'))
                            ->options([
                                'cards' => __('admin.layouts.cards'),
                                'rows' => __('admin.layouts.rows'),
                            ]),
                        Select::make('display_config.template_overrides.density')
                            ->label(__('admin.fields.card_template_density'))
                            ->helperText(__('admin.helpers.card_template_density'))
                            ->options([
                                'compact' => __('admin.card_density.compact'),
                                'comfortable' => __('admin.card_density.comfortable'),
                            ]),
                        Select::make('display_config.template_overrides.image_size')
                            ->label(__('admin.fields.card_template_image_size'))
                            ->helperText(__('admin.helpers.card_template_image_size'))
                            ->options([
                                'hidden' => __('admin.card_image_size.hidden'),
                                'small' => __('admin.card_image_size.small'),
                                'medium' => __('admin.card_image_size.medium'),
                                'large' => __('admin.card_image_size.large'),
                            ]),
                        Select::make('display_config.template_overrides.title_size')
                            ->label(__('admin.fields.card_template_title_size'))
                            ->helperText(__('admin.helpers.card_template_title_size'))
                            ->options([
                                'sm' => __('admin.card_title_size.sm'),
                                'base' => __('admin.card_title_size.base'),
                                'lg' => __('admin.card_title_size.lg'),
                            ]),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.homepage_pagination_config'))
                    ->description(__('admin.descriptions.homepage_pagination_config'))
                    ->schema([
                        Select::make('pagination_config.mode')
                            ->label(__('admin.fields.public_display_pagination_mode'))
                            ->helperText(__('admin.helpers.public_display_pagination_mode'))
                            ->options(PublicDisplaySectionRegistry::paginationModeOptions())
                            ->default('none'),
                        TextInput::make('pagination_config.per_page')
                            ->label(__('admin.fields.public_display_per_page'))
                            ->helperText(__('admin.helpers.public_display_per_page'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(48),
                        Select::make('pagination_config.page_size_options')
                            ->label(__('admin.fields.public_display_page_size_options'))
                            ->helperText(__('admin.helpers.public_display_page_size_options'))
                            ->multiple()
                            ->options([
                                6 => '6',
                                12 => '12',
                                18 => '18',
                                24 => '24',
                                48 => '48',
                            ]),
                        TextInput::make('pagination_config.total_limit')
                            ->label(__('admin.fields.public_display_total_limit'))
                            ->helperText(__('admin.helpers.public_display_total_limit'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100),
                    ])
                    ->columns(4),
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
            HomepageSectionType::TopTranscribers->value => __('admin.summaries.homepage_section_top_transcribers'),
            HomepageSectionType::Latest->value => __('admin.summaries.homepage_section_latest'),
            default => __('admin.summaries.homepage_section_choose_type'),
        };
    }

    /**
     * @return array<int, string>
     */
    private static function contentItemOptions(): array
    {
        return ContentItem::query()
            ->orderBy('title')
            ->limit(100)
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function cardTemplateOptions(Get $get): array
    {
        $family = self::selectedTemplateFamily($get);

        if ($family === null) {
            return [];
        }

        return collect(app(PublicFrontCardTemplateResolver::class)->all($family))
            ->mapWithKeys(fn (PublicFrontCardTemplate $template): array => [$template->key => $template->label ?: $template->key])
            ->all();
    }

    private static function selectedTemplateFamily(Get $get): ?string
    {
        $family = $get('display_config.template_family');

        if (is_string($family) && in_array($family, PublicFrontConfigRegistry::cardFamilies(), true)) {
            return $family;
        }

        $sourceType = $get('source_config.source_type');

        if (! is_string($sourceType) || $sourceType === '') {
            $sourceType = PublicDisplaySectionRegistry::defaultSourceTypeForLegacyType((string) $get('type'));
        }

        return PublicDisplaySectionRegistry::defaultTemplateFamilyForSourceType($sourceType);
    }

    private static function defaultTemplateFamilyLabel(Get $get): ?string
    {
        $family = self::selectedTemplateFamily($get);

        if ($family === null) {
            return null;
        }

        return PublicFrontConfigRegistry::cardFamilyOptions()[$family] ?? $family;
    }
}
