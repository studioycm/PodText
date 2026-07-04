<?php

namespace App\Support\PublicFront;

use App\Enums\PublicFrontConfigBlockType;
use App\Enums\PublicFrontLayoutVariant;

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
                'enabled' => false,
                'items' => [],
            ],
            'about_page' => [
                'enabled' => false,
                'blocks' => [],
                'team_profiles' => [],
            ],
            'public_forms' => [],
            'route_labels' => [],
            'display_defaults' => [
                'layout' => PublicFrontLayoutVariant::Cards->value,
                'density' => PublicFrontLayoutVariant::Comfortable->value,
                'image_size' => PublicFrontLayoutVariant::Medium->value,
                'title_size' => PublicFrontLayoutVariant::Base->value,
                'page_size' => 12,
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
        return [
            'home',
            'search',
            'podcasts',
            'contributors',
            'about',
            'request_transcription',
            'volunteer_transcriber',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function routeOptions(): array
    {
        return collect(self::routeKeys())
            ->mapWithKeys(fn (string $key): array => [$key => __("admin.public_front_routes.{$key}")])
            ->all();
    }
}
