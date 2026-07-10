<?php

namespace App\Support\SettingsLifecycle;

use App\Support\PublicFront\PublicFrontConfigRegistry;

class SettingsLifecycleOverlay
{
    /**
     * @param  array<string, array<int, string>>  $semantics
     * @param  array<string, array<string, mixed>>  $segmentationOverrides
     * @param  array<int, string>  $excludedTopLevelPaths
     */
    public function __construct(
        private readonly array $semantics = [],
        private readonly array $segmentationOverrides = [],
        private readonly array $excludedTopLevelPaths = [],
    ) {}

    public static function publicContent(): self
    {
        $routeLabelPaths = array_map(
            fn (string $routeKey): string => "route_labels.{$routeKey}",
            PublicFrontConfigRegistry::routeKeys(),
        );
        $cardTemplatePaths = array_map(
            fn (string $family): string => "card_templates.{$family}",
            PublicFrontConfigRegistry::cardFamilies(),
        );

        return new self(
            semantics: [
                'front_text' => [
                    'menu_config.logo.alt_text',
                    'menu_config.search.placeholder',
                    'menu_config.items',
                    ...$routeLabelPaths,
                    'about_page.title',
                    'about_page.kicker',
                    'about_page.description',
                    'about_page.blocks',
                    'about_page.team_profiles',
                    'about_page.settings.team_heading',
                    'about_page.settings.team_description',
                    'public_forms.definitions',
                    'podcasts_page.title',
                    'podcasts_page.description',
                    'podcasts_page.group_label_singular',
                    'podcasts_page.group_label_plural',
                    'contributors_page.title',
                    'contributors_page.description',
                    'contributors_page.label_singular',
                    'contributors_page.label_plural',
                    'contributors_page.item_label_singular',
                    'contributors_page.item_label_plural',
                    'item_page.dates.site_published.label_override',
                    'item_page.dates.original_published.label_override',
                    'item_page.dates.transcription_date.label_override',
                    'item_page.info_fields',
                    ...$cardTemplatePaths,
                ],
                'asset_path' => [
                    'menu_config.logo.light_path',
                    'menu_config.logo.dark_path',
                    'default_images.global.path',
                    'default_images.content_item.path',
                    'default_images.content_group.path',
                    'default_images.contributor.path',
                ],
            ],
            segmentationOverrides: [
                'card_templates' => ['mode' => 'card_family'],
                'route_labels' => ['mode' => 'route_key'],
            ],
            excludedTopLevelPaths: [
                'import_locks',
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    public function semanticPaths(string $semantic): array
    {
        return $this->semantics[$semantic] ?? [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function semantics(): array
    {
        return $this->semantics;
    }

    public function segmentationMode(string $path): ?string
    {
        $override = $this->segmentationOverrides[$path] ?? null;

        return is_array($override) ? ($override['mode'] ?? null) : null;
    }

    public function excludesTopLevelPath(string $path): bool
    {
        return in_array($path, $this->excludedTopLevelPaths, true);
    }

    /**
     * @return array<int, string>
     */
    public function semanticsForPath(string $path): array
    {
        return collect($this->semantics)
            ->filter(fn (array $paths): bool => in_array($path, $paths, true))
            ->keys()
            ->values()
            ->all();
    }
}
