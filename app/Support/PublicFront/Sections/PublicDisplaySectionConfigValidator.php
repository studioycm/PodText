<?php

namespace App\Support\PublicFront\Sections;

use App\Models\HomepageSection;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontInvalidConfig;

class PublicDisplaySectionConfigValidator
{
    public function validate(HomepageSection $section): PublicDisplaySectionConfigResult
    {
        $invalidConfig = [];
        $legacyType = $section->type?->value;

        $sourceConfig = $this->normalizeSourceConfig($section, $legacyType, $invalidConfig);
        $selectionConfig = $this->normalizeSelectionConfig($section->selectionConfig(), $invalidConfig);
        $displayConfig = $this->normalizeDisplayConfig($section, $sourceConfig, $invalidConfig);
        $paginationConfig = $this->normalizePaginationConfig($section, $sourceConfig, $invalidConfig);

        return new PublicDisplaySectionConfigResult(
            sourceConfig: $sourceConfig,
            selectionConfig: $selectionConfig,
            displayConfig: $displayConfig,
            paginationConfig: $paginationConfig,
            invalidConfig: $invalidConfig,
        );
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeSourceConfig(HomepageSection $section, ?string $legacyType, array &$invalidConfig): array
    {
        $config = $section->sourceConfig();
        $this->reportUnknownKeys($config, [
            'source_type',
            'sort',
            'direction',
            'total_limit',
            'category_id',
            'include_descendants',
            'tag_id',
            'content_group_id',
        ], 'source_config', $invalidConfig);

        $defaultSourceType = PublicDisplaySectionRegistry::defaultSourceTypeForLegacyType($legacyType);
        $sourceType = array_key_exists('source_type', $config)
            ? $this->finiteString($config['source_type'], PublicDisplaySectionRegistry::sourceTypes(), 'source_config.source_type', $invalidConfig, nullable: true)
            : $defaultSourceType;

        if ($sourceType === null && $legacyType !== null) {
            $invalidConfig[] = PublicFrontInvalidConfig::make('source_config.source_type', 'deferred_or_unknown_source_type', $legacyType);
        }

        $sort = $this->finiteString(
            $config['sort'] ?? $this->defaultSortForSourceType($sourceType),
            PublicDisplaySectionRegistry::sortTypes(),
            'source_config.sort',
            $invalidConfig,
            $this->defaultSortForSourceType($sourceType),
        );

        $isLatest = $sourceType === PublicDisplaySectionRegistry::LATEST_CONTENT_ITEMS;
        $totalLimit = array_key_exists('total_limit', $config)
            ? $this->integerRange($config['total_limit'], 'source_config.total_limit', $isLatest ? 50 : 1, 100, max($isLatest ? 50 : 1, (int) $section->limit), $invalidConfig)
            : null;

        return array_filter([
            'source_type' => $sourceType,
            'sort' => $sort,
            'direction' => $this->finiteString($config['direction'] ?? 'desc', PublicDisplaySectionRegistry::directions(), 'source_config.direction', $invalidConfig, 'desc'),
            'total_limit' => $totalLimit,
            'category_id' => $this->positiveInteger($config['category_id'] ?? $section->category_id, 'source_config.category_id', $invalidConfig, nullable: true),
            'include_descendants' => $this->boolean($config['include_descendants'] ?? null, 'source_config.include_descendants', true, $invalidConfig),
            'tag_id' => $this->positiveInteger($config['tag_id'] ?? $section->tag_id, 'source_config.tag_id', $invalidConfig, nullable: true),
            'content_group_id' => $this->positiveInteger($config['content_group_id'] ?? $section->content_group_id, 'source_config.content_group_id', $invalidConfig, nullable: true),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeSelectionConfig(array $config, array &$invalidConfig): array
    {
        $this->reportUnknownKeys($config, ['include_ids', 'exclude_ids'], 'selection_config', $invalidConfig);

        return [
            'include_ids' => $this->integerList($config['include_ids'] ?? [], 'selection_config.include_ids', $invalidConfig),
            'exclude_ids' => $this->integerList($config['exclude_ids'] ?? [], 'selection_config.exclude_ids', $invalidConfig),
        ];
    }

    /**
     * @param  array<string, mixed>  $sourceConfig
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizeDisplayConfig(HomepageSection $section, array $sourceConfig, array &$invalidConfig): array
    {
        $config = $section->displayConfig();
        $this->reportUnknownKeys($config, [
            'template_key',
            'template_family',
            'template_overrides',
            'heading',
            'body',
            'content_style',
            'button_label',
            'button_route_key',
            'button_form_key',
            'button_display_mode',
            'show_heading',
            'show_view_all_link',
            'view_all_route_key',
        ], 'display_config', $invalidConfig);

        $sourceType = $sourceConfig['source_type'] ?? null;
        $defaultFamily = PublicDisplaySectionRegistry::defaultTemplateFamilyForSourceType(is_string($sourceType) ? $sourceType : null);
        $family = array_key_exists('template_family', $config)
            ? $this->finiteString($config['template_family'], PublicFrontConfigRegistry::cardFamilies(), 'display_config.template_family', $invalidConfig, $defaultFamily, nullable: true)
            : $defaultFamily;

        return array_filter([
            'template_key' => $this->semanticKey($config['template_key'] ?? null, 'display_config.template_key', $invalidConfig, nullable: true),
            'template_family' => $family,
            'template_overrides' => $this->normalizeTemplateOverrides($config['template_overrides'] ?? [], $invalidConfig),
            'heading' => $this->plainString($config['heading'] ?? $section->name, 'display_config.heading', $invalidConfig, maxLength: 160, nullable: true),
            'body' => $this->plainString($config['body'] ?? null, 'display_config.body', $invalidConfig, maxLength: 20000, nullable: true),
            'content_style' => $this->finiteString($config['content_style'] ?? null, PublicDisplaySectionRegistry::contentBlockStyles(), 'display_config.content_style', $invalidConfig, 'plain'),
            'button_label' => $this->plainString($config['button_label'] ?? null, 'display_config.button_label', $invalidConfig, maxLength: 80, nullable: true),
            'button_route_key' => $this->finiteString($config['button_route_key'] ?? null, PublicFrontConfigRegistry::routeKeys(), 'display_config.button_route_key', $invalidConfig, nullable: true),
            'button_form_key' => $this->semanticKey($config['button_form_key'] ?? null, 'display_config.button_form_key', $invalidConfig, nullable: true),
            'button_display_mode' => $this->finiteString($config['button_display_mode'] ?? null, PublicFrontConfigRegistry::publicFormDisplayModes(), 'display_config.button_display_mode', $invalidConfig, 'modal'),
            'show_heading' => $this->boolean($config['show_heading'] ?? null, 'display_config.show_heading', true, $invalidConfig),
            'show_view_all_link' => $this->boolean($config['show_view_all_link'] ?? null, 'display_config.show_view_all_link', true, $invalidConfig),
            'view_all_route_key' => $this->finiteString($config['view_all_route_key'] ?? null, PublicFrontConfigRegistry::routeKeys(), 'display_config.view_all_route_key', $invalidConfig, nullable: true),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $sourceConfig
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, mixed>
     */
    private function normalizePaginationConfig(HomepageSection $section, array $sourceConfig, array &$invalidConfig): array
    {
        $config = $section->paginationConfig();
        $this->reportUnknownKeys($config, [
            'mode',
            'per_page',
            'page_size_options',
            'total_limit',
        ], 'pagination_config', $invalidConfig);

        $isLatest = ($sourceConfig['source_type'] ?? null) === PublicDisplaySectionRegistry::LATEST_CONTENT_ITEMS;
        $perPageMin = $isLatest ? 4 : 1;
        $perPageMax = $isLatest ? 25 : 48;
        $perPageDefault = max($perPageMin, min($perPageMax, max(1, (int) $section->limit)));
        $perPage = $this->integerRange($config['per_page'] ?? $section->limit, 'pagination_config.per_page', $perPageMin, $perPageMax, $perPageDefault, $invalidConfig);
        $totalLimitDefault = $sourceConfig['total_limit'] ?? max($perPage, (int) $section->limit, $isLatest ? 50 : 1);
        $totalLimitMin = $isLatest ? 50 : 1;

        return [
            'mode' => $this->finiteString($config['mode'] ?? 'none', PublicDisplaySectionRegistry::paginationModes(), 'pagination_config.mode', $invalidConfig, 'none'),
            'per_page' => $perPage,
            'page_size_options' => $this->integerList($config['page_size_options'] ?? [], 'pagination_config.page_size_options', $invalidConfig, min: $perPageMin, max: $perPageMax),
            'total_limit' => $this->integerRange($config['total_limit'] ?? $totalLimitDefault, 'pagination_config.total_limit', $totalLimitMin, 100, (int) $totalLimitDefault, $invalidConfig),
        ];
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<string, string>
     */
    private function normalizeTemplateOverrides(mixed $overrides, array &$invalidConfig): array
    {
        if ($overrides === null || $overrides === []) {
            return [];
        }

        if (! is_array($overrides)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make('display_config.template_overrides', 'expected_array', $overrides);

            return [];
        }

        $this->reportUnknownKeys($overrides, ['layout', 'density', 'image_size', 'title_size'], 'display_config.template_overrides', $invalidConfig);

        return array_filter([
            'layout' => array_key_exists('layout', $overrides)
                ? $this->finiteString($overrides['layout'], PublicFrontConfigRegistry::layouts(), 'display_config.template_overrides.layout', $invalidConfig, nullable: true)
                : null,
            'density' => array_key_exists('density', $overrides)
                ? $this->finiteString($overrides['density'], PublicFrontConfigRegistry::densities(), 'display_config.template_overrides.density', $invalidConfig, nullable: true)
                : null,
            'image_size' => array_key_exists('image_size', $overrides)
                ? $this->finiteString($overrides['image_size'], PublicFrontConfigRegistry::imageSizes(), 'display_config.template_overrides.image_size', $invalidConfig, nullable: true)
                : null,
            'title_size' => array_key_exists('title_size', $overrides)
                ? $this->finiteString($overrides['title_size'], PublicFrontConfigRegistry::titleSizes(), 'display_config.template_overrides.title_size', $invalidConfig, nullable: true)
                : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function defaultSortForSourceType(?string $sourceType): string
    {
        return match ($sourceType) {
            PublicDisplaySectionRegistry::CONTENT_GROUPS => 'homepage_order',
            PublicDisplaySectionRegistry::CATEGORIES => 'name_asc',
            PublicDisplaySectionRegistry::CONTRIBUTORS,
            PublicDisplaySectionRegistry::TOP_TRANSCRIBERS => 'top_transcriptions',
            default => 'latest_transcription',
        };
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
    private function boolean(mixed $value, string $path, bool $default, array &$invalidConfig): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) $value;
        }

        $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_boolean', $value);

        return $default;
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
    private function positiveInteger(mixed $value, string $path, array &$invalidConfig, bool $nullable = false): ?int
    {
        if ($value === null || $value === '') {
            return $nullable ? null : 0;
        }

        if (! is_numeric($value) || (int) $value < 1) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_positive_integer', $value);

            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     * @return array<int, int>
     */
    private function integerList(mixed $value, string $path, array &$invalidConfig, int $min = 1, int $max = PHP_INT_MAX): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            $invalidConfig[] = PublicFrontInvalidConfig::make($path, 'expected_list', $value);

            return [];
        }

        $ids = [];

        foreach ($value as $index => $item) {
            if (! is_numeric($item) || (int) $item < $min || (int) $item > $max) {
                $invalidConfig[] = PublicFrontInvalidConfig::make("{$path}.{$index}", 'integer_out_of_range', $item);

                continue;
            }

            $ids[] = (int) $item;
        }

        return collect($ids)->unique()->values()->all();
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
