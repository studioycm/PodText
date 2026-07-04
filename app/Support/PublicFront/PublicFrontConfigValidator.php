<?php

namespace App\Support\PublicFront;

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
            $this->reportUnknownKeys($item, ['key', 'family', 'label'], $path, $invalidConfig);

            $key = $this->semanticKey($item['key'] ?? null, "{$path}.key", $invalidConfig);
            $family = $this->finiteString($item['family'] ?? null, ['content_item', 'content_group', 'contributor'], "{$path}.family", $invalidConfig);
            $label = $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig, nullable: true);

            if ($key === null || $family === null) {
                return null;
            }

            return array_filter([
                'key' => $key,
                'family' => $family,
                'label' => $label,
            ], fn (mixed $value): bool => $value !== null);
        });
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
     * @param  array<mixed>  $items
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, array<string, mixed>>
     */
    private function normalizePublicForms(array $items, array &$invalidConfig): array
    {
        return $this->normalizeList($items, 'public_forms', $invalidConfig, function (array $item, string $path, array &$invalidConfig): ?array {
            $this->reportUnknownKeys($item, ['key', 'label', 'enabled'], $path, $invalidConfig);

            $key = $this->semanticKey($item['key'] ?? null, "{$path}.key", $invalidConfig);
            $label = $this->plainString($item['label'] ?? null, "{$path}.label", $invalidConfig, nullable: true);
            $enabled = $this->boolean($item['enabled'] ?? null, "{$path}.enabled", false, $invalidConfig, nullable: true);

            if ($key === null) {
                return null;
            }

            return array_filter([
                'key' => $key,
                'label' => $label,
                'enabled' => $enabled,
            ], fn (mixed $value): bool => $value !== null);
        });
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
