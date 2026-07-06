<?php

namespace App\Support\PublicFront;

use App\Enums\PublicMenuItemType;
use App\Support\PublicFront\About\PublicAboutPageRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Forms\PublicFormDefinitionRegistry;

class PublicFrontConfigValidator
{
    public function validate(array $config): PublicFrontConfigResult
    {
        $defaults = PublicFrontConfigRegistry::defaults();
        $normalized = $defaults;
        $invalidConfig = [];

        foreach ($config as $key => $value) {
            if (! in_array($key, PublicFrontConfigRegistry::settingsKeys(), true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($key, 'unknown_top_level_key', $value);

                continue;
            }

            if (! is_array($value)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($key, 'expected_array', $value);

                continue;
            }

            $normalized[$key] = match ($key) {
                'card_templates' => $this->normalizeCardTemplates($value, $invalidConfig),
                'menu_config' => $this->normalizeMenuConfig($value, $defaults['menu_config'], $invalidConfig),
                'about_page' => $this->normalizeAboutPage($value, $defaults['about_page'], $invalidConfig),
                'public_forms' => $this->normalizePublicForms($value, $invalidConfig),
                'route_labels' => $this->normalizeRouteLabels($value, $invalidConfig),
                'display_defaults' => $this->normalizeDisplayDefaults($value, $defaults['display_defaults'], $invalidConfig),
                'podcasts_page' => $this->normalizePodcastsPage($value, $defaults['podcasts_page'], $invalidConfig),
            };
        }

        return new PublicFrontConfigResult($normalized, $invalidConfig);
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCardTemplates(array $items, array &$invalidConfig): array
    {
        return $this->normalizeList($items, 'card_templates', $invalidConfig, function (array $item, string $path, array &$invalidConfig): ?array {
            $this->reportUnknownKeys($item, ['key', 'slug', 'family', 'label', 'layout', 'layout_variant', 'density', 'image_size', 'title_size', 'parts'], $path, $invalidConfig);

            $key = $this->semanticKey($item['key'] ?? $item['slug'] ?? null, "{$path}.key", $invalidConfig);
            $family = $this->finiteString($item['family'] ?? null, PublicFrontCardTemplateRegistry::families(), "{$path}.family", $invalidConfig);
            $label = $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig, nullable: true);
            $layout = $this->finiteString($item['layout'] ?? $item['layout_variant'] ?? null, PublicFrontConfigRegistry::layouts(), "{$path}.layout", $invalidConfig, 'cards');
            $density = $this->finiteString($item['density'] ?? null, PublicFrontConfigRegistry::densities(), "{$path}.density", $invalidConfig, 'comfortable');
            $imageSize = $this->finiteString($item['image_size'] ?? null, PublicFrontConfigRegistry::imageSizes(), "{$path}.image_size", $invalidConfig, 'medium');
            $titleSize = $this->finiteString($item['title_size'] ?? null, PublicFrontConfigRegistry::titleSizes(), "{$path}.title_size", $invalidConfig, 'base');
            $parts = $this->normalizeCardTemplateParts($item['parts'] ?? [], "{$path}.parts", $invalidConfig);

            if ($key === null || $family === null) {
                return null;
            }

            return [
                'key' => $key,
                'family' => $family,
                'label' => $label ?? $key,
                'layout' => $layout,
                'density' => $density,
                'image_size' => $imageSize,
                'title_size' => $titleSize,
                'parts' => $parts,
            ];
        });
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCardTemplateParts(mixed $items, string $path, array &$invalidConfig): array
    {
        if (! is_array($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        if (! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.{$index}", 'expected_array', $item);

                continue;
            }

            $part = $this->normalizeCardTemplatePart($item, "{$path}.{$index}", $invalidConfig, $index);

            if ($part !== null) {
                $normalized[] = $part;
            }
        }

        return collect($normalized)
            ->sortBy('order')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>|null
     */
    private function normalizeCardTemplatePart(array $item, string $path, array &$invalidConfig, int $index): ?array
    {
        [$part, $fieldPath] = $this->unwrapBuilderPart($item, $path, $invalidConfig);

        $allowedFields = [
            'type',
            'source',
            'attribute',
            'label',
            'label_position',
            'icon',
            'icon_position',
            'layout',
            'visible',
            'order',
            'line_clamp',
            'font_size',
            'url_target',
            'text',
        ];

        $this->reportUnknownKeys($part, $allowedFields, $fieldPath, $invalidConfig);

        $type = $this->finiteString($part['type'] ?? null, PublicFrontCardTemplateRegistry::partTypes(), "{$fieldPath}.type", $invalidConfig);

        if ($type === null) {
            return null;
        }

        $source = $this->normalizePartSource($part, $type, $fieldPath, $invalidConfig);

        if (($part['source'] ?? null) !== null && $source === null) {
            return null;
        }

        $attribute = $this->normalizePartAttribute($part, $type, $source, $fieldPath, $invalidConfig);

        if (($part['attribute'] ?? null) !== null && $attribute === null) {
            return null;
        }

        if (! PublicFrontCardTemplateRegistry::isValidAttributeForSource($source, $attribute)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$fieldPath}.attribute", 'invalid_source_attribute', $attribute);

            return null;
        }

        $text = array_key_exists('text', $part)
            ? $this->plainString($part['text'], "{$fieldPath}.text", $invalidConfig, maxLength: 500, nullable: true)
            : null;

        if ($type === 'custom_text' && $text === null) {
            return null;
        }

        return array_filter([
            'type' => $type,
            'source' => $source,
            'attribute' => $attribute,
            'label' => $this->plainString($part['label'] ?? null, "{$fieldPath}.label", $invalidConfig, maxLength: 80, nullable: true),
            'label_position' => array_key_exists('label_position', $part)
                ? $this->finiteString($part['label_position'], PublicFrontCardTemplateRegistry::labelPositions(), "{$fieldPath}.label_position", $invalidConfig, nullable: true)
                : null,
            'icon' => array_key_exists('icon', $part)
                ? $this->finiteString($part['icon'], PublicFrontCardTemplateRegistry::icons(), "{$fieldPath}.icon", $invalidConfig, nullable: true)
                : null,
            'icon_position' => array_key_exists('icon_position', $part)
                ? $this->finiteString($part['icon_position'], PublicFrontCardTemplateRegistry::iconPositions(), "{$fieldPath}.icon_position", $invalidConfig, nullable: true)
                : null,
            'layout' => $this->finiteString($part['layout'] ?? null, PublicFrontCardTemplateRegistry::partLayouts(), "{$fieldPath}.layout", $invalidConfig, 'inline'),
            'visible' => $this->boolean($part['visible'] ?? null, "{$fieldPath}.visible", true, $invalidConfig),
            'order' => array_key_exists('order', $part)
                ? $this->integerRange($part['order'], "{$fieldPath}.order", 0, 1000, ($index + 1) * 10, $invalidConfig)
                : ($index + 1) * 10,
            'line_clamp' => array_key_exists('line_clamp', $part)
                ? $this->integerRange($part['line_clamp'], "{$fieldPath}.line_clamp", 0, 4, 3, $invalidConfig)
                : null,
            'font_size' => array_key_exists('font_size', $part)
                ? $this->finiteString($part['font_size'], PublicFrontCardTemplateRegistry::fontSizes(), "{$fieldPath}.font_size", $invalidConfig, nullable: true)
                : null,
            'url_target' => array_key_exists('url_target', $part)
                ? $this->finiteString($part['url_target'], PublicFrontCardTemplateRegistry::urlTargets(), "{$fieldPath}.url_target", $invalidConfig, nullable: true)
                : null,
            'text' => $text,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function unwrapBuilderPart(array $item, string $path, array &$invalidConfig): array
    {
        if (! array_key_exists('data', $item)) {
            return [$item, $path];
        }

        $this->reportUnknownKeys($item, ['type', 'data'], $path, $invalidConfig);

        if (! is_array($item['data'])) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.data", 'expected_array', $item['data']);

            return [['type' => $item['type'] ?? null], $path];
        }

        return [
            [
                'type' => $item['type'] ?? null,
                ...$item['data'],
            ],
            "{$path}.data",
        ];
    }

    /**
     * @param  array<string, mixed>  $part
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function normalizePartSource(array $part, string $type, string $path, array &$invalidConfig): ?string
    {
        if (! array_key_exists('source', $part)) {
            return PublicFrontCardTemplateRegistry::defaultSourceForPart($type);
        }

        return $this->finiteString($part['source'], PublicFrontCardTemplateRegistry::sources(), "{$path}.source", $invalidConfig, nullable: true);
    }

    /**
     * @param  array<string, mixed>  $part
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function normalizePartAttribute(array $part, string $type, ?string $source, string $path, array &$invalidConfig): ?string
    {
        if (! array_key_exists('attribute', $part)) {
            return PublicFrontCardTemplateRegistry::defaultAttributeForPart($type);
        }

        if ($source === null) {
            return $this->semanticKey($part['attribute'], "{$path}.attribute", $invalidConfig, nullable: true);
        }

        return $this->finiteString($part['attribute'], PublicFrontCardTemplateRegistry::attributesForSource($source), "{$path}.attribute", $invalidConfig, nullable: true);
    }

    /**
     * @param  array<string, mixed>  $menuConfig
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeMenuConfig(array $menuConfig, array $defaults, array &$invalidConfig): array
    {
        $this->reportUnknownKeys($menuConfig, [
            'enabled',
            'items_alignment',
            'items',
            'logo',
            'search',
            'theme_selector',
        ], 'menu_config', $invalidConfig);

        return [
            'enabled' => $this->boolean($menuConfig['enabled'] ?? null, 'menu_config.enabled', $defaults['enabled'], $invalidConfig),
            'items_alignment' => $this->finiteString($menuConfig['items_alignment'] ?? null, ['start', 'center', 'end'], 'menu_config.items_alignment', $invalidConfig, $defaults['items_alignment'] ?? 'center'),
            'items' => $this->normalizeMenuItems($menuConfig['items'] ?? $defaults['items'], $invalidConfig),
            'logo' => $this->normalizeMenuLogo($menuConfig['logo'] ?? $defaults['logo'] ?? [], $defaults['logo'] ?? [], $invalidConfig),
            'search' => $this->normalizeMenuSearch($menuConfig['search'] ?? $defaults['search'] ?? [], $defaults['search'] ?? [], $invalidConfig),
            'theme_selector' => $this->normalizeThemeSelector($menuConfig['theme_selector'] ?? $defaults['theme_selector'] ?? [], $defaults['theme_selector'] ?? [], $invalidConfig),
        ];
    }

    /**
     * @param  array<mixed>|mixed  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMenuItems(mixed $items, array &$invalidConfig): array
    {
        $normalized = $this->normalizeListValue($items, 'menu_config.items', $invalidConfig, function (array $item, string $path, array &$invalidConfig): ?array {
            $this->reportUnknownKeys($item, [
                'key',
                'type',
                'label',
                'route_key',
                'external_url',
                'form_key',
                'display_mode',
                'visible',
                'sort',
                'open_in_new_tab',
                'theme_selector',
            ], $path, $invalidConfig);

            $inferredType = $this->inferMenuItemType($item);
            $type = $this->finiteString($item['type'] ?? $inferredType, PublicMenuItemType::values(), "{$path}.type", $invalidConfig, nullable: true);

            if ($type === null) {
                return null;
            }

            $normalized = [
                'key' => $this->semanticKey($item['key'] ?? $this->defaultMenuItemKey($item, $type), "{$path}.key", $invalidConfig, nullable: true)
                    ?? "{$type}_".str_replace('menu_config.items.', '', $path),
                'type' => $type,
                'label' => $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig, maxLength: 80, nullable: true),
                'visible' => $this->boolean($item['visible'] ?? null, "{$path}.visible", true, $invalidConfig),
                'sort' => $this->integerRange($item['sort'] ?? 0, "{$path}.sort", 0, 1000, 0, $invalidConfig),
            ];

            if ($type === PublicMenuItemType::Route->value) {
                $routeKey = $this->finiteString($item['route_key'] ?? null, PublicFrontConfigRegistry::routeKeys(), "{$path}.route_key", $invalidConfig);

                return $routeKey === null ? null : $normalized + [
                    'route_key' => $routeKey,
                ];
            }

            if ($type === PublicMenuItemType::ExternalUrl->value) {
                $externalUrl = $this->httpsUrl($item['external_url'] ?? null, "{$path}.external_url", $invalidConfig);

                return $externalUrl === null ? null : $normalized + [
                    'external_url' => $externalUrl,
                    'open_in_new_tab' => $this->boolean($item['open_in_new_tab'] ?? null, "{$path}.open_in_new_tab", false, $invalidConfig),
                ];
            }

            if ($type === PublicMenuItemType::PublicForm->value) {
                $formKey = $this->semanticKey($item['form_key'] ?? null, "{$path}.form_key", $invalidConfig);

                return $formKey === null ? null : $normalized + [
                    'form_key' => $formKey,
                    'display_mode' => $this->finiteString($item['display_mode'] ?? null, PublicFormDefinitionRegistry::displayModes(), "{$path}.display_mode", $invalidConfig, 'modal'),
                ];
            }

            return $normalized;
        });

        return collect($normalized)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function inferMenuItemType(array $item): ?string
    {
        if (($item['theme_selector'] ?? false) === true) {
            return PublicMenuItemType::ThemeSelector->value;
        }

        if (filled($item['route_key'] ?? null)) {
            return PublicMenuItemType::Route->value;
        }

        if (filled($item['external_url'] ?? null)) {
            return PublicMenuItemType::ExternalUrl->value;
        }

        if (filled($item['form_key'] ?? null)) {
            return PublicMenuItemType::PublicForm->value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function defaultMenuItemKey(array $item, string $type): ?string
    {
        return match ($type) {
            PublicMenuItemType::Route->value => is_string($item['route_key'] ?? null) ? $item['route_key'] : null,
            PublicMenuItemType::PublicForm->value => is_string($item['form_key'] ?? null) ? $item['form_key'] : null,
            PublicMenuItemType::ThemeSelector->value => 'theme_selector',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|mixed  $themeSelector
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeThemeSelector(mixed $themeSelector, array $defaults, array &$invalidConfig): array
    {
        if (! is_array($themeSelector)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make('menu_config.theme_selector', 'expected_array', $themeSelector);

            return $defaults;
        }

        $this->reportUnknownKeys($themeSelector, ['enabled', 'mode', 'display_mode'], 'menu_config.theme_selector', $invalidConfig);

        return [
            'enabled' => $this->boolean($themeSelector['enabled'] ?? null, 'menu_config.theme_selector.enabled', (bool) ($defaults['enabled'] ?? true), $invalidConfig),
            'mode' => $this->finiteString($themeSelector['mode'] ?? null, ['light_dark_system', 'light_dark'], 'menu_config.theme_selector.mode', $invalidConfig, (string) ($defaults['mode'] ?? 'light_dark_system')),
            'display_mode' => $this->finiteString($themeSelector['display_mode'] ?? null, ['text', 'text_icon', 'icon', 'trigger_icon_menu'], 'menu_config.theme_selector.display_mode', $invalidConfig, (string) ($defaults['display_mode'] ?? 'text_icon')),
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $logo
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeMenuLogo(mixed $logo, array $defaults, array &$invalidConfig): array
    {
        if (! is_array($logo)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make('menu_config.logo', 'expected_array', $logo);

            return $defaults;
        }

        $this->reportUnknownKeys($logo, [
            'light_path',
            'dark_path',
            'alt_text',
            'display_mode',
            'size',
        ], 'menu_config.logo', $invalidConfig);

        return [
            'light_path' => array_key_exists('light_path', $logo)
                ? $this->publicLogoPath($logo['light_path'], 'menu_config.logo.light_path', $invalidConfig)
                : ($defaults['light_path'] ?? null),
            'dark_path' => array_key_exists('dark_path', $logo)
                ? $this->publicLogoPath($logo['dark_path'], 'menu_config.logo.dark_path', $invalidConfig)
                : ($defaults['dark_path'] ?? null),
            'alt_text' => $this->plainString($logo['alt_text'] ?? null, 'menu_config.logo.alt_text', $invalidConfig, maxLength: 120, nullable: true)
                ?? (string) ($defaults['alt_text'] ?? __('app.name')),
            'display_mode' => $this->finiteString($logo['display_mode'] ?? null, ['image', 'image_text', 'text'], 'menu_config.logo.display_mode', $invalidConfig, (string) ($defaults['display_mode'] ?? 'image')),
            'size' => $this->finiteString($logo['size'] ?? null, ['small', 'medium', 'large'], 'menu_config.logo.size', $invalidConfig, (string) ($defaults['size'] ?? 'medium')),
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $search
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeMenuSearch(mixed $search, array $defaults, array &$invalidConfig): array
    {
        if (! is_array($search)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make('menu_config.search', 'expected_array', $search);

            return $defaults;
        }

        $this->reportUnknownKeys($search, [
            'enabled',
            'placeholder',
            'route_key',
            'query_param',
        ], 'menu_config.search', $invalidConfig);

        return [
            'enabled' => $this->boolean($search['enabled'] ?? null, 'menu_config.search.enabled', (bool) ($defaults['enabled'] ?? true), $invalidConfig),
            'placeholder' => $this->plainString($search['placeholder'] ?? null, 'menu_config.search.placeholder', $invalidConfig, maxLength: 120, nullable: true)
                ?? (string) ($defaults['placeholder'] ?? __('public.menu.search_placeholder')),
            'route_key' => $this->finiteString($search['route_key'] ?? null, PublicFrontConfigRegistry::routeKeys(), 'menu_config.search.route_key', $invalidConfig, (string) ($defaults['route_key'] ?? 'search')),
            'query_param' => $this->semanticKey($search['query_param'] ?? null, 'menu_config.search.query_param', $invalidConfig, nullable: true)
                ?? (string) ($defaults['query_param'] ?? 'q'),
        ];
    }

    /**
     * @param  array<string, mixed>  $aboutPage
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeAboutPage(array $aboutPage, array $defaults, array &$invalidConfig): array
    {
        $this->reportUnknownKeys($aboutPage, [
            'enabled',
            'title',
            'kicker',
            'description',
            'blocks',
            'team_profiles',
            'settings',
        ], 'about_page', $invalidConfig);

        return [
            'enabled' => $this->boolean($aboutPage['enabled'] ?? null, 'about_page.enabled', $defaults['enabled'], $invalidConfig),
            'title' => $this->plainString($aboutPage['title'] ?? null, 'about_page.title', $invalidConfig, maxLength: 160, nullable: true)
                ?? $defaults['title'],
            'kicker' => $this->plainString($aboutPage['kicker'] ?? null, 'about_page.kicker', $invalidConfig, maxLength: 120, nullable: true)
                ?? $defaults['kicker'],
            'description' => $this->plainString($aboutPage['description'] ?? null, 'about_page.description', $invalidConfig, maxLength: 1000, nullable: true)
                ?? $defaults['description'],
            'blocks' => $this->normalizeAboutBlocks($aboutPage['blocks'] ?? [], 'about_page.blocks', $invalidConfig),
            'team_profiles' => $this->normalizeTeamProfiles($aboutPage['team_profiles'] ?? [], 'about_page.team_profiles', $invalidConfig),
            'settings' => $this->normalizeAboutPageSettings($aboutPage['settings'] ?? [], $defaults['settings'], 'about_page.settings', $invalidConfig),
        ];
    }

    /**
     * @param  array<mixed>|mixed  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAboutBlocks(mixed $items, string $path, array &$invalidConfig): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            $blockPath = "{$path}.{$index}";

            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($blockPath, 'expected_array', $item);

                continue;
            }

            $block = $this->normalizeAboutBlock($item, $blockPath, $invalidConfig, $index);

            if ($block !== null) {
                $normalized[] = $block;
            }
        }

        return collect($normalized)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>|null
     */
    private function normalizeAboutBlock(array $item, string $path, array &$invalidConfig, int $index): ?array
    {
        [$block, $blockPath] = $this->unwrapBuilderBlock($item, $path, $invalidConfig);

        $this->reportUnknownKeys($block, [
            'key',
            'type',
            'visible',
            'sort',
            'heading',
            'body',
            'content',
            'rich_content',
            'image_path',
            'image_alt',
            'image_fit',
            'image_radius',
            'style',
            'form_key',
            'display_mode',
            'button_label',
        ], $blockPath, $invalidConfig);

        $type = $this->finiteString($block['type'] ?? null, PublicAboutPageRegistry::blockTypes(), "{$blockPath}.type", $invalidConfig);

        if ($type === null) {
            return null;
        }

        $normalized = [
            'key' => $this->semanticKey($block['key'] ?? null, "{$blockPath}.key", $invalidConfig, nullable: true)
                ?? "{$type}_".($index + 1),
            'type' => $type,
            'visible' => $this->boolean($block['visible'] ?? null, "{$blockPath}.visible", true, $invalidConfig),
            'sort' => array_key_exists('sort', $block)
                ? $this->integerRange($block['sort'], "{$blockPath}.sort", 0, 1000, ($index + 1) * 10, $invalidConfig)
                : ($index + 1) * 10,
            'style' => $this->finiteString($block['style'] ?? null, PublicAboutPageRegistry::styles(), "{$blockPath}.style", $invalidConfig, 'default'),
        ];

        $heading = $this->plainString($block['heading'] ?? null, "{$blockPath}.heading", $invalidConfig, maxLength: 160, nullable: true);
        $bodySource = $type === 'rich_content'
            ? ($block['body'] ?? null)
            : ($block['body'] ?? $block['content'] ?? null);
        $body = $this->markdownString($bodySource, "{$blockPath}.body", $invalidConfig, maxLength: 20000, nullable: true);

        if ($heading !== null) {
            $normalized['heading'] = $heading;
        }

        if ($body !== null) {
            $normalized['body'] = $body;
        }

        if ($type === 'heading') {
            return $heading === null ? null : $normalized;
        }

        if ($type === 'markdown') {
            return $body === null ? null : $normalized + ['content' => $body];
        }

        if ($type === 'rich_content') {
            $richContent = $this->normalizeRichContent($block['rich_content'] ?? $block['content'] ?? null, "{$blockPath}.rich_content", $invalidConfig);

            return $richContent === null ? null : $normalized + ['rich_content' => $richContent];
        }

        if ($type === 'image') {
            $imagePath = $this->publicImagePath($block['image_path'] ?? null, "{$blockPath}.image_path", $invalidConfig);

            if ($imagePath === null) {
                return null;
            }

            return $normalized + [
                'image_path' => $imagePath,
                'image_alt' => $this->plainString($block['image_alt'] ?? null, "{$blockPath}.image_alt", $invalidConfig, maxLength: 160, nullable: true),
                'image_fit' => $this->finiteString($block['image_fit'] ?? null, PublicFrontConfigRegistry::imageFits(), "{$blockPath}.image_fit", $invalidConfig, 'cover'),
                'image_radius' => $this->finiteString($block['image_radius'] ?? null, PublicFrontConfigRegistry::imageRadii(), "{$blockPath}.image_radius", $invalidConfig, 'mid_rounded'),
            ];
        }

        if ($type === 'callout') {
            return ($heading === null && $body === null) ? null : $normalized;
        }

        if ($type === 'form_cta') {
            $formKey = $this->semanticKey($block['form_key'] ?? null, "{$blockPath}.form_key", $invalidConfig);

            if ($formKey === null) {
                return null;
            }

            return $normalized + [
                'form_key' => $formKey,
                'display_mode' => $this->finiteString($block['display_mode'] ?? null, PublicFormDefinitionRegistry::displayModes(), "{$blockPath}.display_mode", $invalidConfig, 'modal'),
                'button_label' => $this->plainString($block['button_label'] ?? null, "{$blockPath}.button_label", $invalidConfig, maxLength: 80, nullable: true)
                    ?? __('public.forms.submit'),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function unwrapBuilderBlock(array $item, string $path, array &$invalidConfig): array
    {
        if (! array_key_exists('data', $item)) {
            return [$item, $path];
        }

        $this->reportUnknownKeys($item, ['type', 'data'], $path, $invalidConfig);

        if (! is_array($item['data'])) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.data", 'expected_array', $item['data']);

            return [['type' => $item['type'] ?? null], $path];
        }

        return [
            [
                'type' => $item['type'] ?? null,
                ...$item['data'],
            ],
            "{$path}.data",
        ];
    }

    /**
     * @param  array<mixed>|mixed  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTeamProfiles(mixed $items, string $path, array &$invalidConfig): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];
        $seenKeys = [];

        foreach ($items as $index => $item) {
            $profilePath = "{$path}.{$index}";

            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($profilePath, 'expected_array', $item);

                continue;
            }

            $profile = $this->normalizeTeamProfile($item, $profilePath, $invalidConfig, $index);

            if ($profile === null) {
                continue;
            }

            if (in_array($profile['key'], $seenKeys, true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$profilePath}.key", 'duplicate_key', $profile['key']);

                continue;
            }

            $seenKeys[] = $profile['key'];
            $normalized[] = $profile;
        }

        return collect($normalized)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>|null
     */
    private function normalizeTeamProfile(array $item, string $path, array &$invalidConfig, int $index): ?array
    {
        $this->reportUnknownKeys($item, [
            'key',
            'visible',
            'sort',
            'image_path',
            'title',
            'name',
            'description',
        ], $path, $invalidConfig);

        $key = $this->semanticKey($item['key'] ?? null, "{$path}.key", $invalidConfig);
        $name = $this->plainString($item['name'] ?? null, "{$path}.name", $invalidConfig, maxLength: 120);

        if ($name === '') {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.name", 'required_string', $item['name'] ?? null);

            return null;
        }

        if ($key === null || $name === null) {
            return null;
        }

        return array_filter([
            'key' => $key,
            'visible' => $this->boolean($item['visible'] ?? null, "{$path}.visible", true, $invalidConfig),
            'sort' => array_key_exists('sort', $item)
                ? $this->integerRange($item['sort'], "{$path}.sort", 0, 1000, ($index + 1) * 10, $invalidConfig)
                : ($index + 1) * 10,
            'image_path' => array_key_exists('image_path', $item)
                ? $this->publicImagePath($item['image_path'], "{$path}.image_path", $invalidConfig, ['team'])
                : null,
            'title' => $this->plainString($item['title'] ?? null, "{$path}.title", $invalidConfig, maxLength: 120, nullable: true),
            'name' => $name,
            'description' => $this->plainString($item['description'] ?? null, "{$path}.description", $invalidConfig, maxLength: 1000, nullable: true),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>|mixed  $settings
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeAboutPageSettings(mixed $settings, array $defaults, string $path, array &$invalidConfig): array
    {
        if ($settings === null || $settings === []) {
            return $defaults;
        }

        if (! is_array($settings)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_array', $settings);

            return $defaults;
        }

        $this->reportUnknownKeys($settings, ['team_heading', 'team_description', 'team_layout', 'team_card'], $path, $invalidConfig);

        return [
            'team_heading' => $this->plainString($settings['team_heading'] ?? null, "{$path}.team_heading", $invalidConfig, maxLength: 160, nullable: true)
                ?? $defaults['team_heading'],
            'team_description' => $this->plainString($settings['team_description'] ?? null, "{$path}.team_description", $invalidConfig, maxLength: 1000, nullable: true)
                ?? $defaults['team_description'],
            'team_layout' => $this->finiteString($settings['team_layout'] ?? null, PublicAboutPageRegistry::teamLayouts(), "{$path}.team_layout", $invalidConfig, $defaults['team_layout']),
            'team_card' => $this->normalizeTeamCardSettings($settings['team_card'] ?? [], $defaults['team_card'] ?? [], "{$path}.team_card", $invalidConfig),
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $settings
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeTeamCardSettings(mixed $settings, array $defaults, string $path, array &$invalidConfig): array
    {
        if ($settings === null || $settings === []) {
            return $defaults;
        }

        if (! is_array($settings)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_array', $settings);

            return $defaults;
        }

        $this->reportUnknownKeys($settings, [
            'show_image',
            'image_size',
            'image_fit',
            'image_radius',
            'layout',
            'density',
            'show_title',
            'show_description',
            'description_lines',
        ], $path, $invalidConfig);

        return [
            'show_image' => $this->boolean($settings['show_image'] ?? null, "{$path}.show_image", (bool) ($defaults['show_image'] ?? true), $invalidConfig),
            'image_size' => $this->finiteString($settings['image_size'] ?? null, PublicAboutPageRegistry::teamCardImageSizes(), "{$path}.image_size", $invalidConfig, (string) ($defaults['image_size'] ?? 'medium')),
            'image_fit' => $this->finiteString($settings['image_fit'] ?? null, PublicFrontConfigRegistry::imageFits(), "{$path}.image_fit", $invalidConfig, (string) ($defaults['image_fit'] ?? 'cover')),
            'image_radius' => $this->finiteString($settings['image_radius'] ?? null, PublicFrontConfigRegistry::imageRadii(), "{$path}.image_radius", $invalidConfig, (string) ($defaults['image_radius'] ?? 'circle')),
            'layout' => $this->finiteString($settings['layout'] ?? null, PublicAboutPageRegistry::teamLayouts(), "{$path}.layout", $invalidConfig, (string) ($defaults['layout'] ?? 'grid')),
            'density' => $this->finiteString($settings['density'] ?? null, PublicAboutPageRegistry::teamCardDensities(), "{$path}.density", $invalidConfig, (string) ($defaults['density'] ?? 'comfortable')),
            'show_title' => $this->boolean($settings['show_title'] ?? null, "{$path}.show_title", (bool) ($defaults['show_title'] ?? true), $invalidConfig),
            'show_description' => $this->boolean($settings['show_description'] ?? null, "{$path}.show_description", (bool) ($defaults['show_description'] ?? true), $invalidConfig),
            'description_lines' => array_key_exists('description_lines', $settings)
                ? $this->integerRange($settings['description_lines'], "{$path}.description_lines", 0, 6, (int) ($defaults['description_lines'] ?? 3), $invalidConfig)
                : (int) ($defaults['description_lines'] ?? 3),
        ];
    }

    /**
     * @param  array<mixed>  $config
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array{definitions: array<int, array<string, mixed>>}
     */
    private function normalizePublicForms(array $config, array &$invalidConfig): array
    {
        if (array_is_list($config)) {
            return [
                'definitions' => $this->normalizePublicFormDefinitions($config, 'public_forms.definitions', $invalidConfig),
            ];
        }

        $this->reportUnknownKeys($config, ['definitions'], 'public_forms', $invalidConfig);

        return [
            'definitions' => $this->normalizePublicFormDefinitions($config['definitions'] ?? [], 'public_forms.definitions', $invalidConfig),
        ];
    }

    /**
     * @param  array<mixed>|mixed  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizePublicFormDefinitions(mixed $items, string $path, array &$invalidConfig): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];
        $seenKeys = [];

        foreach ($items as $index => $item) {
            $definitionPath = "{$path}.{$index}";

            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($definitionPath, 'expected_array', $item);

                continue;
            }

            $definition = $this->normalizePublicFormDefinition($item, $definitionPath, $invalidConfig);

            if ($definition === null) {
                continue;
            }

            if (in_array($definition['key'], $seenKeys, true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$definitionPath}.key", 'duplicate_key', $definition['key']);

                continue;
            }

            $seenKeys[] = $definition['key'];
            $normalized[] = $definition;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>|null
     */
    private function normalizePublicFormDefinition(array $item, string $path, array &$invalidConfig): ?array
    {
        $this->reportUnknownKeys($item, [
            'key',
            'label',
            'name',
            'heading',
            'description',
            'submit_label',
            'success_message',
            'enabled',
            'display_mode_default',
            'fields',
            'settings',
        ], $path, $invalidConfig);

        $key = $this->semanticKey($item['key'] ?? null, "{$path}.key", $invalidConfig);
        $name = $this->plainString($item['name'] ?? $item['label'] ?? null, "{$path}.name", $invalidConfig, maxLength: 120);

        if ($key === null || $name === null) {
            return null;
        }

        $fields = $this->normalizePublicFormFields($item['fields'] ?? [], "{$path}.fields", $invalidConfig);
        $enabled = $this->boolean($item['enabled'] ?? null, "{$path}.enabled", false, $invalidConfig);

        if ($enabled && $fields === []) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.fields", 'enabled_form_requires_fields', $fields);
            $enabled = false;
        }

        return [
            'key' => $key,
            'name' => $name,
            'heading' => $this->plainString($item['heading'] ?? null, "{$path}.heading", $invalidConfig, maxLength: 160, nullable: true) ?? $name,
            'description' => $this->plainString($item['description'] ?? null, "{$path}.description", $invalidConfig, maxLength: 1000, nullable: true),
            'submit_label' => $this->plainString($item['submit_label'] ?? null, "{$path}.submit_label", $invalidConfig, maxLength: 80, nullable: true)
                ?? PublicFormDefinitionRegistry::defaultSubmitLabel(),
            'success_message' => $this->plainString($item['success_message'] ?? null, "{$path}.success_message", $invalidConfig, maxLength: 240, nullable: true)
                ?? PublicFormDefinitionRegistry::defaultSuccessMessage(),
            'enabled' => $enabled,
            'display_mode_default' => $this->finiteString($item['display_mode_default'] ?? null, PublicFormDefinitionRegistry::displayModes(), "{$path}.display_mode_default", $invalidConfig, 'modal'),
            'fields' => $fields,
            'settings' => $this->normalizePublicFormSettings($item['settings'] ?? [], "{$path}.settings", $invalidConfig),
        ];
    }

    /**
     * @param  array<mixed>|mixed  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizePublicFormFields(mixed $items, string $path, array &$invalidConfig): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];
        $seenKeys = [];

        foreach ($items as $index => $item) {
            $fieldPath = "{$path}.{$index}";

            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($fieldPath, 'expected_array', $item);

                continue;
            }

            $field = $this->normalizePublicFormField($item, $fieldPath, $invalidConfig);

            if ($field === null) {
                continue;
            }

            if (in_array($field['key'], $seenKeys, true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$fieldPath}.key", 'duplicate_key', $field['key']);

                continue;
            }

            $seenKeys[] = $field['key'];
            $normalized[] = $field;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>|null
     */
    private function normalizePublicFormField(array $item, string $path, array &$invalidConfig): ?array
    {
        [$fieldItem, $fieldPath] = $this->unwrapPublicFormField($item, $path, $invalidConfig);

        $this->reportUnknownKeys($fieldItem, [
            'key',
            'type',
            'label',
            'placeholder',
            'help_text',
            'required',
            'options',
            'min_length',
            'max_length',
            'validation_semantics',
        ], $fieldPath, $invalidConfig);

        $key = $this->semanticKey($fieldItem['key'] ?? null, "{$fieldPath}.key", $invalidConfig);
        $type = $this->finiteString($fieldItem['type'] ?? null, PublicFormDefinitionRegistry::fieldTypes(), "{$fieldPath}.type", $invalidConfig);
        $label = $this->plainString($fieldItem['label'] ?? null, "{$fieldPath}.label", $invalidConfig, maxLength: 120);

        if ($key === null || $type === null || $label === null) {
            return null;
        }

        $options = $this->normalizePublicFormFieldOptions($fieldItem['options'] ?? [], "{$fieldPath}.options", $invalidConfig);

        if ($type === 'select' && $options === []) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$fieldPath}.options", 'options_required', $fieldItem['options'] ?? []);

            return null;
        }

        $field = [
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'placeholder' => $this->plainString($fieldItem['placeholder'] ?? null, "{$fieldPath}.placeholder", $invalidConfig, maxLength: 160, nullable: true),
            'help_text' => $this->plainString($fieldItem['help_text'] ?? null, "{$fieldPath}.help_text", $invalidConfig, maxLength: 240, nullable: true),
            'required' => $this->boolean($fieldItem['required'] ?? null, "{$fieldPath}.required", false, $invalidConfig),
            'options' => in_array($type, ['select', 'checkbox'], true) ? $options : [],
            'validation_semantics' => $this->finiteString($fieldItem['validation_semantics'] ?? null, PublicFormDefinitionRegistry::validationSemantics(), "{$fieldPath}.validation_semantics", $invalidConfig, 'none'),
        ];

        if (array_key_exists('min_length', $fieldItem)) {
            $field['min_length'] = $this->integerRange($fieldItem['min_length'], "{$fieldPath}.min_length", 0, 5000, 0, $invalidConfig);
        }

        if (array_key_exists('max_length', $fieldItem)) {
            $field['max_length'] = $this->integerRange($fieldItem['max_length'], "{$fieldPath}.max_length", 1, 5000, $type === 'textarea' ? 5000 : 255, $invalidConfig);
        }

        if (($field['min_length'] ?? 0) > ($field['max_length'] ?? 5000)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$fieldPath}.max_length", 'max_length_before_min_length', $field['max_length']);
            unset($field['min_length'], $field['max_length']);
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function unwrapPublicFormField(array $item, string $path, array &$invalidConfig): array
    {
        if (! array_key_exists('data', $item)) {
            return [$item, $path];
        }

        $this->reportUnknownKeys($item, ['type', 'data'], $path, $invalidConfig);

        if (! is_array($item['data'])) {
            $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.data", 'expected_array', $item['data']);

            return [['type' => $item['type'] ?? null], $path];
        }

        return [
            [
                'type' => $item['type'] ?? null,
                ...$item['data'],
            ],
            "{$path}.data",
        ];
    }

    /**
     * @param  array<mixed>|mixed  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array{value: string, label: string}>
     */
    private function normalizePublicFormFieldOptions(mixed $items, string $path, array &$invalidConfig): array
    {
        if ($items === null || $items === []) {
            return [];
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];
        $seenValues = [];

        foreach ($items as $index => $item) {
            $optionPath = "{$path}.{$index}";

            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($optionPath, 'expected_array', $item);

                continue;
            }

            $this->reportUnknownKeys($item, ['value', 'label'], $optionPath, $invalidConfig);

            $value = $this->semanticKey($item['value'] ?? null, "{$optionPath}.value", $invalidConfig);
            $label = $this->plainString($item['label'] ?? null, "{$optionPath}.label", $invalidConfig, maxLength: 120);

            if ($value === null || $label === null) {
                continue;
            }

            if (in_array($value, $seenValues, true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$optionPath}.value", 'duplicate_key', $value);

                continue;
            }

            $seenValues[] = $value;
            $normalized[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|mixed  $settings
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array{rate_limit_attempts: int, rate_limit_decay_seconds: int}
     */
    private function normalizePublicFormSettings(mixed $settings, string $path, array &$invalidConfig): array
    {
        $defaults = PublicFormDefinitionRegistry::rateLimitDefaults();

        if ($settings === null || $settings === []) {
            return $defaults;
        }

        if (! is_array($settings)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_array', $settings);

            return $defaults;
        }

        $this->reportUnknownKeys($settings, ['rate_limit_attempts', 'rate_limit_decay_seconds'], $path, $invalidConfig);

        return [
            'rate_limit_attempts' => array_key_exists('rate_limit_attempts', $settings)
                ? $this->integerRange($settings['rate_limit_attempts'], "{$path}.rate_limit_attempts", 1, 30, $defaults['rate_limit_attempts'], $invalidConfig)
                : $defaults['rate_limit_attempts'],
            'rate_limit_decay_seconds' => array_key_exists('rate_limit_decay_seconds', $settings)
                ? $this->integerRange($settings['rate_limit_decay_seconds'], "{$path}.rate_limit_decay_seconds", 60, 86400, $defaults['rate_limit_decay_seconds'], $invalidConfig)
                : $defaults['rate_limit_decay_seconds'],
        ];
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, string>>
     */
    private function normalizeRouteLabels(array $items, array &$invalidConfig): array
    {
        return $this->normalizeList($items, 'route_labels', $invalidConfig, function (array $item, string $path, array &$invalidConfig): ?array {
            $this->reportUnknownKeys($item, ['route_key', 'label'], $path, $invalidConfig);

            $routeKey = $this->finiteString($item['route_key'] ?? null, PublicFrontConfigRegistry::routeKeys(), "{$path}.route_key", $invalidConfig);
            $label = $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig);

            if ($routeKey === null || $label === null) {
                return null;
            }

            return [
                'route_key' => $routeKey,
                'label' => $label,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $displayDefaults
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeDisplayDefaults(array $displayDefaults, array $defaults, array &$invalidConfig): array
    {
        $this->reportUnknownKeys($displayDefaults, ['layout', 'density', 'image_size', 'image_fit', 'image_radius', 'title_size', 'page_size'], 'display_defaults', $invalidConfig);

        return [
            'layout' => $this->finiteString($displayDefaults['layout'] ?? null, PublicFrontConfigRegistry::layouts(), 'display_defaults.layout', $invalidConfig, $defaults['layout']),
            'density' => $this->finiteString($displayDefaults['density'] ?? null, PublicFrontConfigRegistry::densities(), 'display_defaults.density', $invalidConfig, $defaults['density']),
            'image_size' => $this->finiteString($displayDefaults['image_size'] ?? null, PublicFrontConfigRegistry::imageSizes(), 'display_defaults.image_size', $invalidConfig, $defaults['image_size']),
            'image_fit' => $this->finiteString($displayDefaults['image_fit'] ?? null, PublicFrontConfigRegistry::imageFits(), 'display_defaults.image_fit', $invalidConfig, $defaults['image_fit'] ?? 'cover'),
            'image_radius' => $this->finiteString($displayDefaults['image_radius'] ?? null, PublicFrontConfigRegistry::imageRadii(), 'display_defaults.image_radius', $invalidConfig, $defaults['image_radius'] ?? 'mid_rounded'),
            'title_size' => $this->finiteString($displayDefaults['title_size'] ?? null, PublicFrontConfigRegistry::titleSizes(), 'display_defaults.title_size', $invalidConfig, $defaults['title_size']),
            'page_size' => $this->integerRange($displayDefaults['page_size'] ?? null, 'display_defaults.page_size', 1, 48, $defaults['page_size'], $invalidConfig),
        ];
    }

    /**
     * @param  array<string, mixed>  $podcastsPage
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizePodcastsPage(array $podcastsPage, array $defaults, array &$invalidConfig): array
    {
        $this->reportUnknownKeys($podcastsPage, [
            'enabled',
            'title',
            'description',
            'group_label_singular',
            'group_label_plural',
            'cards_per_page',
            'category_filter_enabled',
            'search_enabled',
            'template_key',
            'item_template_key',
            'image_fit',
            'image_radius',
            'show_description',
            'show_categories',
            'show_episode_count',
            'group_page',
        ], 'podcasts_page', $invalidConfig);

        return [
            'enabled' => $this->boolean($podcastsPage['enabled'] ?? null, 'podcasts_page.enabled', $defaults['enabled'], $invalidConfig),
            'title' => $this->plainString($podcastsPage['title'] ?? null, 'podcasts_page.title', $invalidConfig, maxLength: 160, nullable: true)
                ?? $defaults['title'],
            'description' => $this->plainString($podcastsPage['description'] ?? null, 'podcasts_page.description', $invalidConfig, maxLength: 1000, nullable: true)
                ?? $defaults['description'],
            'group_label_singular' => $this->plainString($podcastsPage['group_label_singular'] ?? null, 'podcasts_page.group_label_singular', $invalidConfig, maxLength: 80, nullable: true)
                ?? $defaults['group_label_singular'],
            'group_label_plural' => $this->plainString($podcastsPage['group_label_plural'] ?? null, 'podcasts_page.group_label_plural', $invalidConfig, maxLength: 80, nullable: true)
                ?? $defaults['group_label_plural'],
            'cards_per_page' => $this->integerRange($podcastsPage['cards_per_page'] ?? null, 'podcasts_page.cards_per_page', 1, 48, $defaults['cards_per_page'], $invalidConfig),
            'category_filter_enabled' => $this->boolean($podcastsPage['category_filter_enabled'] ?? null, 'podcasts_page.category_filter_enabled', $defaults['category_filter_enabled'], $invalidConfig),
            'search_enabled' => $this->boolean($podcastsPage['search_enabled'] ?? null, 'podcasts_page.search_enabled', $defaults['search_enabled'], $invalidConfig),
            'template_key' => $this->semanticKey($podcastsPage['template_key'] ?? null, 'podcasts_page.template_key', $invalidConfig, nullable: true),
            'item_template_key' => $this->semanticKey($podcastsPage['item_template_key'] ?? null, 'podcasts_page.item_template_key', $invalidConfig, nullable: true),
            'image_fit' => $this->finiteString($podcastsPage['image_fit'] ?? null, PublicFrontConfigRegistry::imageFits(), 'podcasts_page.image_fit', $invalidConfig, $defaults['image_fit'] ?? 'cover'),
            'image_radius' => $this->finiteString($podcastsPage['image_radius'] ?? null, PublicFrontConfigRegistry::imageRadii(), 'podcasts_page.image_radius', $invalidConfig, $defaults['image_radius'] ?? 'mid_rounded'),
            'show_description' => $this->boolean($podcastsPage['show_description'] ?? null, 'podcasts_page.show_description', $defaults['show_description'], $invalidConfig),
            'show_categories' => $this->boolean($podcastsPage['show_categories'] ?? null, 'podcasts_page.show_categories', $defaults['show_categories'], $invalidConfig),
            'show_episode_count' => $this->boolean($podcastsPage['show_episode_count'] ?? null, 'podcasts_page.show_episode_count', $defaults['show_episode_count'], $invalidConfig),
            'group_page' => $this->normalizePodcastGroupPage($podcastsPage['group_page'] ?? [], $defaults['group_page'], $invalidConfig),
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $groupPage
     * @param  array<string, mixed>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizePodcastGroupPage(mixed $groupPage, array $defaults, array &$invalidConfig): array
    {
        if ($groupPage === null || $groupPage === []) {
            return $defaults;
        }

        if (! is_array($groupPage)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make('podcasts_page.group_page', 'expected_array', $groupPage);

            return $defaults;
        }

        $this->reportUnknownKeys($groupPage, [
            'show_description',
            'show_categories',
            'show_episode_descriptions',
            'items_layout',
            'items_grid_columns',
            'items_grid_gap',
            'items_per_page',
            'page_size_options',
            'per_page_selector_enabled',
            'search_enabled',
            'sort_enabled',
            'category_filter_enabled',
            'default_sort',
            'sort_options',
            'item_density',
            'item_image_size',
            'item_image_fit',
            'item_image_radius',
            'item_title_size',
            'show_episode_authors',
            'show_episode_tags',
            'show_episode_duration',
            'show_episode_effective_date',
        ], 'podcasts_page.group_page', $invalidConfig);

        $itemsPerPage = $this->integerRange($groupPage['items_per_page'] ?? null, 'podcasts_page.group_page.items_per_page', 1, 48, $defaults['items_per_page'], $invalidConfig);
        $pageSizeOptions = $this->integerOptionsList(
            $groupPage['page_size_options'] ?? null,
            'podcasts_page.group_page.page_size_options',
            1,
            48,
            $defaults['page_size_options'] ?? [6, 12, 24, 48],
            $invalidConfig,
        );
        $pageSizeOptions = collect([...$pageSizeOptions, $itemsPerPage])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $sortOptions = $this->finiteStringList(
            $groupPage['sort_options'] ?? null,
            PublicFrontConfigRegistry::podcastGroupItemSorts(),
            'podcasts_page.group_page.sort_options',
            $defaults['sort_options'] ?? ['latest_transcription', 'title_asc'],
            $invalidConfig,
        );
        $defaultSort = $this->finiteString(
            $groupPage['default_sort'] ?? null,
            $sortOptions,
            'podcasts_page.group_page.default_sort',
            $invalidConfig,
            $defaults['default_sort'] ?? 'latest_transcription',
        );

        if (! in_array($defaultSort, $sortOptions, true)) {
            $defaultSort = $sortOptions[0] ?? 'latest_transcription';
        }

        return [
            'show_description' => $this->boolean($groupPage['show_description'] ?? null, 'podcasts_page.group_page.show_description', $defaults['show_description'], $invalidConfig),
            'show_categories' => $this->boolean($groupPage['show_categories'] ?? null, 'podcasts_page.group_page.show_categories', $defaults['show_categories'], $invalidConfig),
            'show_episode_descriptions' => $this->boolean($groupPage['show_episode_descriptions'] ?? null, 'podcasts_page.group_page.show_episode_descriptions', $defaults['show_episode_descriptions'], $invalidConfig),
            'items_layout' => $this->finiteString($groupPage['items_layout'] ?? null, PublicFrontConfigRegistry::layouts(), 'podcasts_page.group_page.items_layout', $invalidConfig, $defaults['items_layout'] ?? 'cards'),
            'items_grid_columns' => $this->integerRange($groupPage['items_grid_columns'] ?? null, 'podcasts_page.group_page.items_grid_columns', 1, 4, $defaults['items_grid_columns'] ?? 3, $invalidConfig),
            'items_grid_gap' => $this->finiteString($groupPage['items_grid_gap'] ?? null, PublicFrontConfigRegistry::podcastGroupItemGridGaps(), 'podcasts_page.group_page.items_grid_gap', $invalidConfig, $defaults['items_grid_gap'] ?? 'comfortable'),
            'items_per_page' => $itemsPerPage,
            'page_size_options' => $pageSizeOptions,
            'per_page_selector_enabled' => $this->boolean($groupPage['per_page_selector_enabled'] ?? null, 'podcasts_page.group_page.per_page_selector_enabled', $defaults['per_page_selector_enabled'] ?? true, $invalidConfig),
            'search_enabled' => $this->boolean($groupPage['search_enabled'] ?? null, 'podcasts_page.group_page.search_enabled', $defaults['search_enabled'] ?? true, $invalidConfig),
            'sort_enabled' => $this->boolean($groupPage['sort_enabled'] ?? null, 'podcasts_page.group_page.sort_enabled', $defaults['sort_enabled'] ?? true, $invalidConfig),
            'category_filter_enabled' => $this->boolean($groupPage['category_filter_enabled'] ?? null, 'podcasts_page.group_page.category_filter_enabled', $defaults['category_filter_enabled'] ?? true, $invalidConfig),
            'default_sort' => $defaultSort,
            'sort_options' => $sortOptions,
            'item_density' => $this->finiteString($groupPage['item_density'] ?? null, PublicFrontConfigRegistry::densities(), 'podcasts_page.group_page.item_density', $invalidConfig, $defaults['item_density'] ?? 'comfortable'),
            'item_image_size' => $this->finiteString($groupPage['item_image_size'] ?? null, PublicFrontConfigRegistry::imageSizes(), 'podcasts_page.group_page.item_image_size', $invalidConfig, $defaults['item_image_size'] ?? 'medium'),
            'item_image_fit' => $this->finiteString($groupPage['item_image_fit'] ?? null, PublicFrontConfigRegistry::imageFits(), 'podcasts_page.group_page.item_image_fit', $invalidConfig, $defaults['item_image_fit'] ?? 'cover'),
            'item_image_radius' => $this->finiteString($groupPage['item_image_radius'] ?? null, PublicFrontConfigRegistry::imageRadii(), 'podcasts_page.group_page.item_image_radius', $invalidConfig, $defaults['item_image_radius'] ?? 'mid_rounded'),
            'item_title_size' => $this->finiteString($groupPage['item_title_size'] ?? null, PublicFrontConfigRegistry::titleSizes(), 'podcasts_page.group_page.item_title_size', $invalidConfig, $defaults['item_title_size'] ?? 'base'),
            'show_episode_authors' => $this->boolean($groupPage['show_episode_authors'] ?? null, 'podcasts_page.group_page.show_episode_authors', $defaults['show_episode_authors'] ?? true, $invalidConfig),
            'show_episode_tags' => $this->boolean($groupPage['show_episode_tags'] ?? null, 'podcasts_page.group_page.show_episode_tags', $defaults['show_episode_tags'] ?? true, $invalidConfig),
            'show_episode_duration' => $this->boolean($groupPage['show_episode_duration'] ?? null, 'podcasts_page.group_page.show_episode_duration', $defaults['show_episode_duration'] ?? true, $invalidConfig),
            'show_episode_effective_date' => $this->boolean($groupPage['show_episode_effective_date'] ?? null, 'podcasts_page.group_page.show_episode_effective_date', $defaults['show_episode_effective_date'] ?? true, $invalidConfig),
        ];
    }

    /**
     * @param  array<string>  $allowed
     * @param  array<string>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, string>
     */
    private function finiteStringList(mixed $items, array $allowed, string $path, array $defaults, array &$invalidConfig): array
    {
        if ($items === null) {
            return $defaults;
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return $defaults;
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            $value = $this->finiteString($item, $allowed, "{$path}.{$index}", $invalidConfig, nullable: true);

            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        $normalized = collect($normalized)
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? $defaults : $normalized;
    }

    /**
     * @param  array<int>  $defaults
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int>
     */
    private function integerOptionsList(mixed $items, string $path, int $min, int $max, array $defaults, array &$invalidConfig): array
    {
        if ($items === null) {
            return $defaults;
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return $defaults;
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            $normalized[] = $this->integerRange($item, "{$path}.{$index}", $min, $max, 0, $invalidConfig);
        }

        $normalized = collect($normalized)
            ->filter(fn (int $value): bool => $value >= $min && $value <= $max)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized === [] ? $defaults : $normalized;
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSimpleConfigList(mixed $items, string $path, array &$invalidConfig): array
    {
        return $this->normalizeListValue($items, $path, $invalidConfig, function (array $item, string $path, array &$invalidConfig): ?array {
            $this->reportUnknownKeys($item, ['key', 'type', 'label', 'enabled'], $path, $invalidConfig);

            $key = $this->semanticKey($item['key'] ?? null, "{$path}.key", $invalidConfig, nullable: true);
            $type = $this->semanticKey($item['type'] ?? null, "{$path}.type", $invalidConfig, nullable: true);
            $label = $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig, nullable: true);
            $enabled = $this->boolean($item['enabled'] ?? null, "{$path}.enabled", true, $invalidConfig, nullable: true);

            return array_filter([
                'key' => $key,
                'type' => $type,
                'label' => $label,
                'enabled' => $enabled,
            ], fn (mixed $value): bool => $value !== null);
        });
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @param  callable(array<string, mixed>, string, array<PublicFrontInvalidConfig>): ?array<string, mixed>  $normalizer
     * @return array<int, array<string, mixed>>
     */
    private function normalizeListValue(mixed $items, string $path, array &$invalidConfig, callable $normalizer): array
    {
        if (! is_array($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        return $this->normalizeList($items, $path, $invalidConfig, $normalizer);
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @param  callable(array<string, mixed>, string, array<PublicFrontInvalidConfig>): ?array<string, mixed>  $normalizer
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(array $items, string $path, array &$invalidConfig, callable $normalizer): array
    {
        if (! array_is_list($items)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $items);

            return [];
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.{$index}", 'expected_array', $item);

                continue;
            }

            $normalizedItem = $normalizer($item, "{$path}.{$index}", $invalidConfig);

            if ($normalizedItem !== null && $normalizedItem !== []) {
                $normalized[] = $normalizedItem;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string>  $allowedKeys
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function reportUnknownKeys(array $value, array $allowedKeys, string $path, array &$invalidConfig): void
    {
        foreach (array_keys($value) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.{$key}", 'unknown_nested_key', $value[$key]);
            }
        }
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function boolean(mixed $value, string $path, bool $default, array &$invalidConfig, bool $nullable = false): ?bool
    {
        if ($value === null) {
            return $nullable ? null : $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) $value;
        }

        $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_boolean', $value);

        return $nullable ? null : $default;
    }

    /**
     * @param  array<string>  $allowed
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function finiteString(mixed $value, array $allowed, string $path, array &$invalidConfig, ?string $default = null, bool $nullable = false): ?string
    {
        $value = $this->plainString($value, $path, $invalidConfig, nullable: $nullable || $default !== null);

        if ($value === null) {
            return $default;
        }

        if (! in_array($value, $allowed, true)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'unknown_semantic_value', $value);

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function semanticKey(mixed $value, string $path, array &$invalidConfig, bool $nullable = false): ?string
    {
        $value = $this->plainString($value, $path, $invalidConfig, nullable: $nullable);

        if ($value === null) {
            return null;
        }

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'invalid_semantic_key', $value);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function httpsUrl(mixed $value, string $path, array &$invalidConfig, bool $nullable = false): ?string
    {
        $value = $this->plainString($value, $path, $invalidConfig, maxLength: 2048, nullable: $nullable);

        if ($value === null) {
            return null;
        }

        if (! str_starts_with(strtolower($value), 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_https_url', $value);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string>  $directories
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function publicImagePath(mixed $value, string $path, array &$invalidConfig, array $directories = []): ?string
    {
        if (is_array($value)) {
            $value = collect($value)
                ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
                ->first();
        }

        $value = $this->plainString($value, $path, $invalidConfig, maxLength: 255, nullable: true);

        if ($value === null) {
            return null;
        }

        $directories = $directories === [] ? PublicAboutPageRegistry::imageDirectories() : $directories;
        $directoryPattern = implode('|', array_map(fn (string $directory): string => preg_quote($directory, '/'), $directories));

        if (! preg_match("/^(?:{$directoryPattern})\/[A-Za-z0-9][A-Za-z0-9._\/-]*\.(?:jpe?g|png|webp)$/i", $value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'invalid_public_image_path', $value);

            return null;
        }

        if (str_contains($value, '../') || str_contains($value, '//') || str_starts_with($value, '/')) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'invalid_public_image_path', $value);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function publicLogoPath(mixed $value, string $path, array &$invalidConfig): ?string
    {
        if (is_array($value)) {
            $value = collect($value)
                ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
                ->first();
        }

        $value = $this->plainString($value, $path, $invalidConfig, maxLength: 255, nullable: true);

        if ($value === null) {
            return null;
        }

        if (! preg_match('/^header\/[A-Za-z0-9][A-Za-z0-9._\/-]*\.(?:jpe?g|png|webp|svg)$/i', $value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'invalid_public_logo_path', $value);

            return null;
        }

        if (str_contains($value, '../') || str_contains($value, '//') || str_starts_with($value, '/')) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'invalid_public_logo_path', $value);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function integerRange(mixed $value, string $path, int $min, int $max, int $default, array &$invalidConfig): int
    {
        if (! is_numeric($value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_integer', $value);

            return $default;
        }

        $value = (int) $value;

        if ($value < $min || $value > $max) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'integer_out_of_range', $value);

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function markdownString(mixed $value, string $path, array &$invalidConfig, int $maxLength = 20000, bool $nullable = false): ?string
    {
        if ($value === null || $value === '') {
            return $nullable ? null : '';
        }

        if (! is_string($value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_string', $value);

            return null;
        }

        $value = trim($value);

        if (mb_strlen($value) > $maxLength) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'string_too_long', $value);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>|null
     */
    private function normalizeRichContent(mixed $value, string $path, array &$invalidConfig): ?array
    {
        if (! is_array($value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_array', $value);

            return null;
        }

        if ($this->containsUnsafeRichContent($value, $path, $invalidConfig)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function containsUnsafeRichContent(array $value, string $path, array &$invalidConfig): bool
    {
        $unsafe = false;

        foreach ($value as $key => $item) {
            $itemPath = "{$path}.{$key}";

            if (is_array($item)) {
                $unsafe = $this->containsUnsafeRichContent($item, $itemPath, $invalidConfig) || $unsafe;

                continue;
            }

            if (! is_string($item)) {
                continue;
            }

            if ($key === 'type' && ! in_array($item, $this->allowedRichContentTypes(), true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($itemPath, 'unknown_semantic_value', $item);
                $unsafe = true;

                continue;
            }

            if (in_array($key, ['class', 'style', 'html'], true)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($itemPath, 'unsafe_string_value', $item);
                $unsafe = true;

                continue;
            }

            if (in_array($key, ['href', 'src'], true) && $this->richContentUrlIsUnsafe($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($itemPath, 'unsafe_string_value', $item);
                $unsafe = true;

                continue;
            }

            if ($key !== 'text' && $this->containsUnsafeString($item)) {
                $invalidConfig[] = PublicFrontInvalidConfig::make($itemPath, 'unsafe_string_value', $item);
                $unsafe = true;
            }
        }

        return $unsafe;
    }

    /**
     * @return array<string>
     */
    private function allowedRichContentTypes(): array
    {
        return [
            'blockquote',
            'bold',
            'bulletList',
            'code',
            'doc',
            'hardBreak',
            'heading',
            'horizontalRule',
            'italic',
            'link',
            'listItem',
            'orderedList',
            'paragraph',
            'strike',
            'text',
            'underline',
        ];
    }

    private function richContentUrlIsUnsafe(string $value): bool
    {
        $lowerValue = strtolower($value);

        if (str_starts_with($lowerValue, 'javascript:')) {
            return true;
        }

        if (str_starts_with($lowerValue, 'mailto:')) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    private function plainString(mixed $value, string $path, array &$invalidConfig, int $maxLength = 255, bool $nullable = false): ?string
    {
        if ($value === null || $value === '') {
            return $nullable ? null : '';
        }

        if (! is_string($value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_string', $value);

            return null;
        }

        $value = trim($value);

        if ($this->containsUnsafeString($value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'unsafe_string_value', $value);

            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'string_too_long', $value);

            return null;
        }

        return $value;
    }

    private function containsUnsafeString(string $value): bool
    {
        $lowerValue = strtolower($value);

        if (str_starts_with($lowerValue, 'javascript:')) {
            return true;
        }

        if (str_contains($lowerValue, '<iframe') || str_contains($lowerValue, '<script')) {
            return true;
        }

        if (str_contains($value, '<') && str_contains($value, '>')) {
            return true;
        }

        if (str_contains($value, '.blade.php') || str_contains($value, 'resources/views') || str_contains($value, '../')) {
            return true;
        }

        if (str_contains($value, '::class') || preg_match('/(?:^|\\\\)(?:App|Filament|Livewire|Illuminate)\\\\[A-Za-z0-9_\\\\]+/', $value)) {
            return true;
        }

        if (preg_match('/\b(select|insert|update|delete|drop|alter|union)\b.+\b(from|where|table|into|set)\b/i', $value)) {
            return true;
        }

        if (preg_match('/\b[a-z-]+\s*:\s*[^;]+;/', $value)) {
            return true;
        }

        return (bool) preg_match('/\b(?:bg|text|p|m|mt|mb|ml|mr|mx|my|px|py|gap|grid|flex|rounded|shadow|border|w|h|min-w|max-w|line-clamp)-[A-Za-z0-9\/\[\]#:.%-]+/', $value);
    }
}
