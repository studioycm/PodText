<?php

namespace App\Support\PublicFront;

use App\Enums\PublicFrontConfigBlockType;
use App\Enums\PublicFrontLayoutVariant;
use App\Enums\PublicMenuItemType;
use App\Support\PublicFront\About\PublicAboutPageRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Forms\PublicFormDefinitionRegistry;
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
            'podcasts_page',
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
                'items' => self::defaultMenuItems(),
                'theme_selector' => [
                    'enabled' => true,
                    'mode' => 'light_dark_system',
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
                'title_size' => PublicFrontLayoutVariant::Base->value,
                'page_size' => 12,
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
                'show_description' => true,
                'show_categories' => true,
                'show_episode_count' => true,
                'group_page' => [
                    'show_description' => true,
                    'show_categories' => true,
                    'show_episode_descriptions' => true,
                    'items_per_page' => 12,
                ],
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
            'podcasts_page' => 'podcastsPage',
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
