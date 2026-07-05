<?php

namespace App\Support\PublicFront;

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
        $this->reportUnknownKeys($menuConfig, ['enabled', 'items'], 'menu_config', $invalidConfig);

        return [
            'enabled' => $this->boolean($menuConfig['enabled'] ?? null, 'menu_config.enabled', $defaults['enabled'], $invalidConfig),
            'items' => $this->normalizeListValue($menuConfig['items'] ?? [], 'menu_config.items', $invalidConfig, function (array $item, string $path, array &$invalidConfig): ?array {
                $this->reportUnknownKeys($item, ['label', 'route_key', 'external_url', 'form_key', 'theme_selector'], $path, $invalidConfig);

                $label = $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig, nullable: true);
                $routeKey = $this->finiteString($item['route_key'] ?? null, PublicFrontConfigRegistry::routeKeys(), "{$path}.route_key", $invalidConfig, nullable: true);
                $externalUrl = $this->httpsUrl($item['external_url'] ?? null, "{$path}.external_url", $invalidConfig, nullable: true);
                $formKey = $this->semanticKey($item['form_key'] ?? null, "{$path}.form_key", $invalidConfig, nullable: true);
                $themeSelector = $this->boolean($item['theme_selector'] ?? null, "{$path}.theme_selector", false, $invalidConfig, nullable: true);

                if ($routeKey === null && $externalUrl === null && $formKey === null && $themeSelector !== true) {
                    return null;
                }

                return array_filter([
                    'label' => $label,
                    'route_key' => $routeKey,
                    'external_url' => $externalUrl,
                    'form_key' => $formKey,
                    'theme_selector' => $themeSelector === true ? true : null,
                ], fn (mixed $value): bool => $value !== null);
            }),
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
        $this->reportUnknownKeys($aboutPage, ['enabled', 'blocks', 'team_profiles'], 'about_page', $invalidConfig);

        return [
            'enabled' => $this->boolean($aboutPage['enabled'] ?? null, 'about_page.enabled', $defaults['enabled'], $invalidConfig),
            'blocks' => $this->normalizeSimpleConfigList($aboutPage['blocks'] ?? [], 'about_page.blocks', $invalidConfig),
            'team_profiles' => $this->normalizeSimpleConfigList($aboutPage['team_profiles'] ?? [], 'about_page.team_profiles', $invalidConfig),
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
        $this->reportUnknownKeys($displayDefaults, ['layout', 'density', 'image_size', 'title_size', 'page_size'], 'display_defaults', $invalidConfig);

        return [
            'layout' => $this->finiteString($displayDefaults['layout'] ?? null, PublicFrontConfigRegistry::layouts(), 'display_defaults.layout', $invalidConfig, $defaults['layout']),
            'density' => $this->finiteString($displayDefaults['density'] ?? null, PublicFrontConfigRegistry::densities(), 'display_defaults.density', $invalidConfig, $defaults['density']),
            'image_size' => $this->finiteString($displayDefaults['image_size'] ?? null, PublicFrontConfigRegistry::imageSizes(), 'display_defaults.image_size', $invalidConfig, $defaults['image_size']),
            'title_size' => $this->finiteString($displayDefaults['title_size'] ?? null, PublicFrontConfigRegistry::titleSizes(), 'display_defaults.title_size', $invalidConfig, $defaults['title_size']),
            'page_size' => $this->integerRange($displayDefaults['page_size'] ?? null, 'display_defaults.page_size', 1, 48, $defaults['page_size'], $invalidConfig),
        ];
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
