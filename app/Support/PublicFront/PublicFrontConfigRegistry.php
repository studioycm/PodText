<?php

namespace App\Support\PublicFront;

use App\Enums\PublicFrontConfigBlockType;
use App\Enums\PublicFrontLayoutVariant;
use App\Enums\PublicMenuItemType;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\About\PublicAboutPageRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Forms\PublicFormDefinitionRegistry;
use App\Support\PublicFront\Icons\PublicFrontIconRegistry;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
use App\Support\PublicFront\Menu\PublicRouteRegistry;

class PublicFrontConfigRegistry
{
    /**
     * @return array<string>
     */
    public static function settingsKeys(): array
    {
        return [
            'card_templates',
            'menu_config',
            'about_page',
            'public_forms',
            'route_labels',
            'display_defaults',
            'default_images',
            'transcription_policy',
            'item_page',
            'podcasts_page',
            'contributors_page',
            'settings_backups',
            'import_locks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'card_templates' => [],
            'menu_config' => [
                'enabled' => true,
                'items_alignment' => 'center',
                'items' => self::defaultMenuItems(),
                'logo' => [
                    'light_path' => null,
                    'dark_path' => null,
                    'alt_text' => __('app.name'),
                    'display_mode' => 'image',
                    'size' => 'medium',
                ],
                'search' => [
                    'enabled' => true,
                    'placeholder' => __('public.menu.search_placeholder'),
                    'route_key' => 'search',
                    'query_param' => 'q',
                ],
                'theme_selector' => [
                    'enabled' => true,
                    'mode' => 'light_dark_system',
                    'display_mode' => 'text_icon',
                ],
            ],
            'about_page' => [
                'enabled' => false,
                'title' => 'מי אנחנו',
                'kicker' => null,
                'description' => null,
                'blocks' => [],
                'team_profiles' => [],
                'settings' => [
                    'team_heading' => null,
                    'team_description' => null,
                    'team_layout' => 'grid',
                    'team_card' => [
                        'show_image' => true,
                        'image_size' => 'medium',
                        'image_fit' => 'cover',
                        'image_radius' => 'circle',
                        'layout' => 'grid',
                        'density' => 'comfortable',
                        'show_title' => true,
                        'show_description' => true,
                        'description_lines' => 3,
                    ],
                ],
            ],
            'public_forms' => [
                'definitions' => [],
            ],
            'route_labels' => [],
            'display_defaults' => [
                'layout' => PublicFrontLayoutVariant::Cards->value,
                'density' => PublicFrontLayoutVariant::Comfortable->value,
                'image_size' => PublicFrontLayoutVariant::Medium->value,
                'image_fit' => 'cover',
                'image_radius' => 'mid_rounded',
                'title_size' => PublicFrontLayoutVariant::Base->value,
                'page_size' => 12,
                'transcription_display' => 'effective_only',
            ],
            'default_images' => [
                'global' => [
                    'mode' => 'inherit',
                    'path' => null,
                ],
                'content_item' => [
                    'mode' => 'inherit',
                    'path' => null,
                ],
                'content_group' => [
                    'mode' => 'inherit',
                    'path' => null,
                ],
                'contributor' => [
                    'mode' => 'inherit',
                    'path' => null,
                ],
            ],
            'transcription_policy' => PublicTranscriptionPolicy::defaults(),
            'item_page' => [
                'show_breadcrumbs' => true,
                'show_transcript_actions_menu' => false,
                'podcast_identity' => [
                    'mode' => 'badge',
                    'color' => 'primary',
                    'custom_color' => null,
                    'icon' => PublicFrontIconRegistry::DEFAULT_PODCAST,
                    'icon_position' => 'inline_before',
                    'position' => 'above_title',
                    'size' => 'sm',
                ],
                'info_fields' => PublicItemPageRegistry::defaultInfoFields(),
                'dates' => [
                    'display' => 'both',
                    'site_published' => [
                        'label_mode' => 'long',
                        'label_override' => null,
                        'icon' => PublicFrontIconRegistry::DEFAULT_CALENDAR,
                        'icon_position' => 'inline_before',
                    ],
                    'original_published' => [
                        'label_mode' => 'short',
                        'label_override' => null,
                        'icon' => PublicFrontIconRegistry::DEFAULT_CALENDAR,
                        'icon_position' => 'inline_before',
                    ],
                    'transcription_date' => [
                        'enabled' => true,
                        'label_mode' => 'short',
                        'label_override' => null,
                        'icon' => PublicFrontIconRegistry::DEFAULT_CONTENT,
                        'icon_position' => 'inline_before',
                    ],
                ],
                'badges' => [
                    'info' => [
                        'size' => PublicItemPageRegistry::badgeSizes()[1],
                        'color' => PublicItemPageRegistry::badgeColors()[0],
                        'custom_color' => null,
                    ],
                ],
            ],
            'podcasts_page' => [
                'enabled' => true,
                'title' => __('public.pages.podcasts.title'),
                'description' => __('public.pages.podcasts.description'),
                'group_label_singular' => __('public.labels.podcast'),
                'group_label_plural' => __('public.labels.podcasts'),
                'cards_per_page' => 12,
                'category_filter_enabled' => true,
                'search_enabled' => true,
                'template_key' => null,
                'item_template_key' => null,
                'image_fit' => 'cover',
                'image_radius' => 'mid_rounded',
                'show_description' => true,
                'show_categories' => true,
                'show_episode_count' => true,
                'group_page' => [
                    'show_description' => true,
                    'show_categories' => true,
                    'show_episode_descriptions' => true,
                    'items_layout' => 'cards',
                    'items_grid_columns' => 3,
                    'items_grid_gap' => 'comfortable',
                    'items_per_page' => 12,
                    'page_size_options' => [6, 12, 24, 48],
                    'per_page_selector_enabled' => true,
                    'search_enabled' => true,
                    'sort_enabled' => true,
                    'category_filter_enabled' => true,
                    'default_sort' => 'latest_transcription',
                    'sort_options' => [
                        'latest_transcription',
                        'oldest_transcription',
                        'title_asc',
                        'title_desc',
                        'original_newest',
                        'original_oldest',
                        'duration_longest',
                        'duration_shortest',
                    ],
                    'item_density' => 'comfortable',
                    'item_image_size' => 'medium',
                    'item_image_fit' => 'cover',
                    'item_image_radius' => 'mid_rounded',
                    'item_title_size' => 'base',
                    'transcription_display' => 'effective_only',
                    'show_episode_authors' => true,
                    'show_episode_tags' => true,
                    'show_episode_duration' => true,
                    'show_episode_effective_date' => true,
                ],
            ],
            'contributors_page' => [
                'enabled' => true,
                'title' => __('public.pages.contributors.title'),
                'description' => __('public.pages.contributors.description'),
                'label_singular' => __('public.labels.contributor'),
                'label_plural' => __('public.labels.contributors'),
                'item_label_singular' => __('public.labels.item'),
                'item_label_plural' => __('public.labels.items'),
                'directory' => [
                    'per_page_options' => [10, 15, 20],
                    'default_per_page' => 10,
                    'default_sort' => 'count_desc',
                    'sort_options' => ['name_asc', 'name_desc', 'count_desc', 'count_asc'],
                    'preview_items_per_page' => 6,
                    'preview_grid_columns' => 3,
                    'preview_search_enabled' => true,
                    'transcription_display' => 'effective_only',
                ],
                'top_transcribers' => [
                    'enabled' => true,
                    'limit' => 8,
                    'layout' => 'horizontal',
                    'preview_default_page_size' => 5,
                    'preview_page_size_options' => [5, 10, 15],
                    'preview_grid_columns' => 3,
                    'show_full_page_link' => true,
                    'show_count_badge' => true,
                    'transcription_display' => 'effective_only',
                ],
                'cards' => [
                    'compact_show_count' => true,
                    'compact_count_icon' => PublicFrontIconRegistry::DEFAULT_CONTENT,
                    'preview_show_bio' => true,
                    'preview_show_counts' => true,
                ],
                'page' => [
                    'items_per_page' => 12,
                    'page_size_options' => [6, 12, 24],
                    'default_sort' => 'latest_transcription',
                    'sort_options' => ['latest_transcription', 'oldest_transcription', 'title_asc', 'title_desc'],
                    'search_enabled' => true,
                    'grid_columns' => 3,
                    'grid_gap' => 'comfortable',
                    'transcription_display' => 'effective_only',
                ],
            ],
            'settings_backups' => [
                'thumbnail_max_width' => 800,
                'snapshot_formats' => ['png'],
                'snapshot_themes' => ['light', 'dark'],
            ],
            'import_locks' => [
                'locked_paths' => [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function schema(): array
    {
        return [
            'card_templates' => 'cardTemplates',
            'menu_config' => 'menuConfig',
            'about_page' => 'aboutPage',
            'public_forms' => 'publicForms',
            'route_labels' => 'routeLabels',
            'display_defaults' => 'displayDefaults',
            'default_images' => 'defaultImages',
            'transcription_policy' => 'transcriptionPolicy',
            'item_page' => 'itemPage',
            'podcasts_page' => 'podcastsPage',
            'contributors_page' => 'contributorsPage',
            'settings_backups' => 'settingsBackups',
            'import_locks' => 'importLocks',
        ];
    }

    /**
     * @return array<string>
     */
    public static function blockTypes(): array
    {
        return array_map(
            fn (PublicFrontConfigBlockType $type): string => $type->value,
            PublicFrontConfigBlockType::cases(),
        );
    }

    /**
     * @return array<string>
     */
    public static function layouts(): array
    {
        return [
            PublicFrontLayoutVariant::Cards->value,
            PublicFrontLayoutVariant::Rows->value,
        ];
    }

    /**
     * @return array<string>
     */
    public static function densities(): array
    {
        return [
            PublicFrontLayoutVariant::Compact->value,
            PublicFrontLayoutVariant::Comfortable->value,
        ];
    }

    /**
     * @return array<string>
     */
    public static function imageSizes(): array
    {
        return [
            PublicFrontLayoutVariant::Hidden->value,
            PublicFrontLayoutVariant::Small->value,
            PublicFrontLayoutVariant::Medium->value,
            PublicFrontLayoutVariant::Large->value,
        ];
    }

    /**
     * @return array<string>
     */
    public static function imageFits(): array
    {
        return [
            'cover',
            'contain',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function imageFitOptions(): array
    {
        return collect(self::imageFits())
            ->mapWithKeys(fn (string $fit): array => [$fit => __("admin.image_fit.{$fit}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function imageRadii(): array
    {
        return [
            'sharp',
            'low_rounded',
            'mid_rounded',
            'high_rounded',
            'round',
            'circle',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function imageRadiusOptions(): array
    {
        return collect(self::imageRadii())
            ->mapWithKeys(fn (string $radius): array => [$radius => __("admin.image_radius.{$radius}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function titleSizes(): array
    {
        return [
            PublicFrontLayoutVariant::Small->value,
            PublicFrontLayoutVariant::Base->value,
            PublicFrontLayoutVariant::LargeTitle->value,
        ];
    }

    /**
     * @return array<string>
     */
    public static function transcriptionDisplayModes(): array
    {
        return [
            'effective_only',
            'effective_plus_count',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function transcriptionDisplayOptions(): array
    {
        return collect(self::transcriptionDisplayModes())
            ->mapWithKeys(fn (string $mode): array => [$mode => __("admin.transcription_display.{$mode}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function defaultImageFamilies(): array
    {
        return [
            'global',
            'content_item',
            'content_group',
            'contributor',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultImageFamilyOptions(): array
    {
        return collect(self::defaultImageFamilies())
            ->mapWithKeys(fn (string $family): array => [$family => __("admin.default_image_families.{$family}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function defaultImageModes(): array
    {
        return [
            'inherit',
            'custom',
            'none',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultImageModeOptions(): array
    {
        return collect(self::defaultImageModes())
            ->mapWithKeys(fn (string $mode): array => [$mode => __("admin.default_image_modes.{$mode}")])
            ->all();
    }

    public static function defaultImageDirectory(): string
    {
        return 'default-images';
    }

    /**
     * @return array<string>
     */
    public static function defaultImageAcceptedFileTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    public static function defaultImageMaxSize(): int
    {
        return 2048;
    }

    /**
     * @return array<int>
     */
    public static function settingsBackupThumbnailMaxWidths(): array
    {
        return [
            400,
            600,
            800,
        ];
    }

    /**
     * @return array<string>
     */
    public static function settingsBackupSnapshotFormats(): array
    {
        return [
            'png',
            'pdf',
            'html',
        ];
    }

    /**
     * @return array<string>
     */
    public static function settingsBackupSnapshotThemes(): array
    {
        return [
            'light',
            'dark',
        ];
    }

    /**
     * @return array<string>
     */
    public static function routeKeys(): array
    {
        return PublicRouteRegistry::keys();
    }

    /**
     * @return array<string, string>
     */
    public static function routeOptions(): array
    {
        return PublicRouteRegistry::options();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultMenuItems(): array
    {
        return [
            [
                'key' => 'home',
                'type' => 'route',
                'label' => __('public.menu.routes.home'),
                'route_key' => 'home',
                'visible' => true,
                'sort' => 10,
            ],
            [
                'key' => 'podcasts',
                'type' => 'route',
                'label' => __('public.menu.routes.podcasts'),
                'route_key' => 'podcasts',
                'visible' => true,
                'sort' => 20,
            ],
            [
                'key' => 'about',
                'type' => 'route',
                'label' => __('public.menu.routes.about'),
                'route_key' => 'about',
                'visible' => true,
                'sort' => 30,
            ],
            [
                'key' => 'request_transcription',
                'type' => 'public_form',
                'label' => __('public.menu.forms.request_transcription'),
                'form_key' => 'request_transcription',
                'display_mode' => 'slide_over',
                'visible' => true,
                'sort' => 40,
            ],
            [
                'key' => 'volunteer_transcriber',
                'type' => 'public_form',
                'label' => __('public.menu.forms.volunteer_transcriber'),
                'form_key' => 'volunteer_transcriber',
                'display_mode' => 'modal',
                'visible' => true,
                'sort' => 50,
            ],
            [
                'key' => 'theme_selector',
                'type' => 'theme_selector',
                'label' => __('public.menu.theme'),
                'visible' => true,
                'sort' => 60,
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public static function publicMenuItemTypes(): array
    {
        return PublicMenuItemType::values();
    }

    /**
     * @return array<string, string>
     */
    public static function publicMenuItemTypeOptions(): array
    {
        return PublicMenuItemType::options();
    }

    /**
     * @return array<string, string>
     */
    public static function publicMenuAlignmentOptions(): array
    {
        return [
            'start' => __('admin.public_menu_alignments.start'),
            'center' => __('admin.public_menu_alignments.center'),
            'end' => __('admin.public_menu_alignments.end'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function publicMenuLogoDisplayModeOptions(): array
    {
        return [
            'image' => __('admin.public_menu_logo_display_modes.image'),
            'image_text' => __('admin.public_menu_logo_display_modes.image_text'),
            'text' => __('admin.public_menu_logo_display_modes.text'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function publicMenuLogoSizeOptions(): array
    {
        return [
            'small' => __('admin.public_menu_logo_sizes.small'),
            'medium' => __('admin.public_menu_logo_sizes.medium'),
            'large' => __('admin.public_menu_logo_sizes.large'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function publicMenuThemeDisplayModeOptions(): array
    {
        return [
            'text' => __('admin.public_menu_theme_display_modes.text'),
            'text_icon' => __('admin.public_menu_theme_display_modes.text_icon'),
            'icon' => __('admin.public_menu_theme_display_modes.icon'),
            'trigger_icon_menu' => __('admin.public_menu_theme_display_modes.trigger_icon_menu'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastGroupItemLayoutOptions(): array
    {
        return [
            'cards' => __('admin.layouts.cards'),
            'rows' => __('admin.layouts.rows'),
        ];
    }

    /**
     * @return array<int>
     */
    public static function podcastGroupItemGridColumns(): array
    {
        return [1, 2, 3, 4];
    }

    /**
     * @return array<int, string>
     */
    public static function podcastGroupItemGridColumnOptions(): array
    {
        return collect(self::podcastGroupItemGridColumns())
            ->mapWithKeys(fn (int $columns): array => [$columns => (string) $columns])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function podcastGroupItemGridGaps(): array
    {
        return [
            'compact',
            'comfortable',
            'spacious',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastGroupItemGridGapOptions(): array
    {
        return collect(self::podcastGroupItemGridGaps())
            ->mapWithKeys(fn (string $gap): array => [$gap => __("admin.grid_gaps.{$gap}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function podcastGroupItemSorts(): array
    {
        return [
            'latest_transcription',
            'oldest_transcription',
            'title_asc',
            'title_desc',
            'original_newest',
            'original_oldest',
            'duration_longest',
            'duration_shortest',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function podcastGroupItemSortOptions(): array
    {
        return collect(self::podcastGroupItemSorts())
            ->mapWithKeys(fn (string $sort): array => [$sort => __("public.sort.{$sort}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function contributorDirectorySorts(): array
    {
        return [
            'name_asc',
            'name_desc',
            'count_desc',
            'count_asc',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function contributorDirectorySortOptions(): array
    {
        return collect(self::contributorDirectorySorts())
            ->mapWithKeys(fn (string $sort): array => [$sort => __("public.sort.contributors_{$sort}")])
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function contributorDirectoryPageSizes(): array
    {
        return [10, 15, 20];
    }

    /**
     * @return array<int, string>
     */
    public static function contributorDirectoryPageSizeOptions(): array
    {
        return collect(self::contributorDirectoryPageSizes())
            ->mapWithKeys(fn (int $size): array => [$size => (string) $size])
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function topTranscriberPreviewPageSizes(): array
    {
        return [5, 10, 15];
    }

    /**
     * @return array<int, string>
     */
    public static function topTranscriberPreviewPageSizeOptions(): array
    {
        return collect(self::topTranscriberPreviewPageSizes())
            ->mapWithKeys(fn (int $size): array => [$size => (string) $size])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function topTranscriberLayouts(): array
    {
        return [
            'horizontal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function topTranscriberLayoutOptions(): array
    {
        return [
            'horizontal' => __('admin.layouts.horizontal'),
        ];
    }

    /**
     * @return array<string>
     */
    public static function contributorCardIcons(): array
    {
        return PublicFrontIconRegistry::tokens();
    }

    /**
     * @return array<string, string>
     */
    public static function contributorCardIconOptions(): array
    {
        return PublicFrontIconRegistry::searchResults('');
    }

    /**
     * @return array<string>
     */
    public static function contributorItemSorts(): array
    {
        return [
            'latest_transcription',
            'oldest_transcription',
            'title_asc',
            'title_desc',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function contributorItemSortOptions(): array
    {
        return collect(self::contributorItemSorts())
            ->mapWithKeys(fn (string $sort): array => [$sort => __("public.sort.{$sort}")])
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function contributorGridColumns(): array
    {
        return [1, 2, 3, 4];
    }

    /**
     * @return array<int, string>
     */
    public static function contributorGridColumnOptions(): array
    {
        return collect(self::contributorGridColumns())
            ->mapWithKeys(fn (int $columns): array => [$columns => (string) $columns])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function groupBadgeModeOptions(): array
    {
        return [
            'name_only' => __('admin.group_badge_modes.name_only'),
            'thumbnail_name' => __('admin.group_badge_modes.thumbnail_name'),
            'combined_title' => __('admin.group_badge_modes.combined_title'),
        ];
    }

    /**
     * @return array<string>
     */
    public static function cardFamilies(): array
    {
        return PublicFrontCardTemplateRegistry::families();
    }

    /**
     * @return array<string, string>
     */
    public static function cardFamilyOptions(): array
    {
        return PublicFrontCardTemplateRegistry::familyOptions();
    }

    /**
     * @return array<string>
     */
    public static function cardPartTypes(): array
    {
        return PublicFrontCardTemplateRegistry::partTypes();
    }

    /**
     * @return array<string, string>
     */
    public static function cardPartTypeOptions(): array
    {
        return PublicFrontCardTemplateRegistry::partTypeOptions();
    }

    /**
     * @return array<string>
     */
    public static function cardSources(): array
    {
        return PublicFrontCardTemplateRegistry::sources();
    }

    /**
     * @return array<string, string>
     */
    public static function cardSourceOptions(): array
    {
        return PublicFrontCardTemplateRegistry::sourceOptions();
    }

    /**
     * @return array<string, array<string>>
     */
    public static function cardAttributes(): array
    {
        return PublicFrontCardTemplateRegistry::attributes();
    }

    /**
     * @return array<string, string>
     */
    public static function cardAttributeOptions(?string $source): array
    {
        return PublicFrontCardTemplateRegistry::attributeOptions($source);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultCardTemplates(): array
    {
        return PublicFrontCardTemplateRegistry::defaultTemplates();
    }

    /**
     * @return array<string>
     */
    public static function aboutBlockTypes(): array
    {
        return PublicAboutPageRegistry::blockTypes();
    }

    /**
     * @return array<string, string>
     */
    public static function aboutBlockTypeOptions(): array
    {
        return PublicAboutPageRegistry::blockTypeOptions();
    }

    /**
     * @return array<string>
     */
    public static function aboutBlockStyles(): array
    {
        return PublicAboutPageRegistry::styles();
    }

    /**
     * @return array<string, string>
     */
    public static function aboutBlockStyleOptions(): array
    {
        return PublicAboutPageRegistry::styleOptions();
    }

    /**
     * @return array<string>
     */
    public static function aboutTeamLayouts(): array
    {
        return PublicAboutPageRegistry::teamLayouts();
    }

    /**
     * @return array<string, string>
     */
    public static function aboutTeamLayoutOptions(): array
    {
        return PublicAboutPageRegistry::teamLayoutOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function aboutTeamCardImageSizeOptions(): array
    {
        return PublicAboutPageRegistry::teamCardImageSizeOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function aboutTeamCardDensityOptions(): array
    {
        return PublicAboutPageRegistry::teamCardDensityOptions();
    }

    /**
     * @return array<string>
     */
    public static function publicFormFieldTypes(): array
    {
        return PublicFormDefinitionRegistry::fieldTypes();
    }

    /**
     * @return array<string, string>
     */
    public static function publicFormFieldTypeOptions(): array
    {
        return PublicFormDefinitionRegistry::fieldTypeOptions();
    }

    /**
     * @return array<string>
     */
    public static function publicFormDisplayModes(): array
    {
        return PublicFormDefinitionRegistry::displayModes();
    }

    /**
     * @return array<string, string>
     */
    public static function publicFormDisplayModeOptions(): array
    {
        return PublicFormDefinitionRegistry::displayModeOptions();
    }

    /**
     * @return array<string>
     */
    public static function publicFormValidationSemantics(): array
    {
        return PublicFormDefinitionRegistry::validationSemantics();
    }

    /**
     * @return array<string, string>
     */
    public static function publicFormValidationSemanticOptions(): array
    {
        return PublicFormDefinitionRegistry::validationSemanticOptions();
    }
}
