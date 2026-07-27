<?php

namespace App\Support\SettingsLifecycle;

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Spatie\LaravelSettings\Settings;

class SettingsLifecycleSchema
{
    /** @var array<string, array<string, mixed>> */
    private array $groupPayloads = [];

    /** @var array<string, array<int, SettingsLifecycleUnit>> */
    private array $units = [];

    /** @var array<string, array<string, SettingsLifecycleUnit>> */
    private array $unitsByPath = [];

    public function __construct(
        private readonly SettingsLifecycleGroups $groups,
    ) {}

    /**
     * @return array<int, string>
     */
    public function managedGroups(): array
    {
        return array_keys($this->groups->all());
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, SettingsLifecycleUnit>
     */
    public function units(?array $payload = null, ?string $group = null): array
    {
        $registration = $this->groups->get($group ?? $this->groups->defaultGroup()->name);
        $payload ??= $this->payloadFor($registration);
        $cacheKey = $this->cacheKey($registration, $payload);

        if (isset($this->units[$cacheKey])) {
            return $this->units[$cacheKey];
        }

        return $this->units[$cacheKey] = collect($this->deriveUnitPaths($payload, $registration))
            ->map(fn (string $path): SettingsLifecycleUnit => new SettingsLifecycleUnit(
                group: $registration->name,
                path: $path,
                label: $this->labelFor($path),
                labelKey: $this->labelKeyFor($path),
                section: $this->sectionFor($path, $payload),
                sectionLabel: $this->sectionLabelFor($this->sectionFor($path, $payload)),
                structuralType: $this->structuralType($this->value($payload, $path)),
                expectedScalarType: $this->expectedScalarType($registration, $path),
                semantics: $registration->overlay->semanticsForPath($path),
            ))
            ->values()
            ->all();
    }

    public function unitFor(string $path, ?array $payload = null, ?string $group = null): ?SettingsLifecycleUnit
    {
        return $this->unitsByPath($payload, $group)[$path] ?? null;
    }

    /**
     * @return array<string, SettingsLifecycleUnit>
     */
    public function unitsByPath(?array $payload = null, ?string $group = null): array
    {
        $registration = $this->groups->get($group ?? $this->groups->defaultGroup()->name);
        $payload ??= $this->payloadFor($registration);
        $cacheKey = $this->cacheKey($registration, $payload);

        return $this->unitsByPath[$cacheKey] ??= collect($this->units($payload, $registration->name))
            ->keyBy('path')
            ->all();
    }

    public function labelKeyFor(string $path): string
    {
        return "admin.settings_paths.{$path}";
    }

    public function labelFor(string $path): string
    {
        $key = $this->labelKeyFor($path);
        $label = __($key);

        if (is_string($label) && $label !== $key) {
            return $label;
        }

        if (str_starts_with($path, 'card_templates.')) {
            return $this->qualifiedLabel(
                'admin.fields.public_front_card_templates',
                'admin.card_template_families.'.str($path)->afterLast('.'),
            );
        }

        if (str_starts_with($path, 'default_images.')) {
            return $this->qualifiedLabel(
                'admin.sections.public_default_images',
                'admin.default_image_families.'.str($path)->afterLast('.'),
            );
        }

        if (str_starts_with($path, 'route_labels.')) {
            return $this->qualifiedLabel(
                'admin.fields.public_front_route_label',
                'admin.public_front_routes.'.str($path)->afterLast('.'),
            );
        }

        foreach ($this->labelCandidatesFor($path) as $candidate) {
            $label = __($candidate);

            if (is_string($label) && $label !== $candidate) {
                return $label;
            }
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function labelCandidatesFor(string $path): array
    {
        $aliases = [
            'about_page.settings' => 'admin.sections.about_page_team_defaults',
            'card_templates' => 'admin.fields.public_front_card_templates',
            'contributors_page.cards' => 'admin.sections.public_front_contributor_cards',
            'contributors_page.directory' => 'admin.sections.public_front_contributors_directory',
            'contributors_page.page' => 'admin.sections.public_front_contributor_page_items',
            'contributors_page.top_transcribers' => 'admin.sections.public_front_top_transcribers',
            'display_defaults.density' => 'admin.fields.public_front_card_density',
            'display_defaults.image_fit' => 'admin.fields.public_front_card_image_fit',
            'display_defaults.image_radius' => 'admin.fields.public_front_card_image_radius',
            'display_defaults.image_size' => 'admin.fields.public_front_card_image_size',
            'display_defaults.layout' => 'admin.fields.public_front_display_layout',
            'display_defaults.page_size' => 'admin.fields.public_front_page_size',
            'display_defaults.title_size' => 'admin.fields.public_front_card_title_size',
            'display_defaults.transcription_display' => 'admin.fields.public_front_transcription_display',
            'item_page.badges' => 'admin.sections.public_front_item_page_badges',
            'item_page.info_fields' => 'admin.sections.public_front_item_page_info_fields',
            'item_page.podcast_identity' => 'admin.sections.public_front_item_page_header',
            'menu_config.enabled' => 'admin.fields.public_front_menu_enabled',
            'menu_config.items' => 'admin.fields.public_menu_items',
            'menu_config.items_alignment' => 'admin.fields.public_menu_items_alignment',
            'menu_config.logo' => 'admin.sections.public_menu_logo',
            'menu_config.search' => 'admin.sections.public_menu_search',
            'menu_config.theme_selector' => 'admin.sections.public_menu_theme_selector',
            'podcasts_page.group_page' => 'admin.sections.public_front_podcasts_group_page',
            'public_forms.definitions' => 'admin.fields.public_forms',
            'route_labels' => 'admin.fields.public_front_route_labels',
            'transcription_policy.count_mode' => 'admin.fields.public_transcription_policy_count_mode',
            'transcription_policy.public_mode' => 'admin.fields.public_transcription_policy_public_mode',
            'transcription_policy.show_multiple_transcriptions_on_item_page' => 'admin.fields.public_transcription_policy_show_multiple_transcriptions_on_item_page',
        ];

        return array_values(array_filter([
            $aliases[$path] ?? null,
            "admin.fields.{$path}",
            'admin.fields.'.str_replace('.', '_', $path),
        ]));
    }

    private function qualifiedLabel(string $groupKey, string $unitKey): string
    {
        $group = __($groupKey);
        $unit = __($unitKey);

        if (
            ! is_string($group)
            || ! is_string($unit)
            || $group === $groupKey
            || $unit === $unitKey
        ) {
            return $unitKey;
        }

        return "{$group}: {$unit}";
    }

    public function structuralType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }

        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            return array_is_list($value) ? 'list' : 'map';
        }

        if ($value === null) {
            return 'null';
        }

        return 'mixed';
    }

    public function scalarTypeMatches(?string $expectedType, mixed $value): bool
    {
        if ($expectedType === null) {
            return true;
        }

        return match ($expectedType) {
            'array' => is_array($value),
            'bool' => is_bool($value),
            'float' => is_float($value) || is_int($value),
            'int' => is_int($value),
            'string' => is_string($value),
            default => true,
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function overlaySemantics(?string $group = null): array
    {
        return $this->groups->get($group ?? $this->groups->defaultGroup()->name)
            ->overlay
            ->semantics();
    }

    public function valueExists(array $payload, string $path): bool
    {
        if ($this->isRouteLabelPath($path)) {
            return $this->routeLabelForPath($payload, $path) !== null;
        }

        if ($this->isCardTemplateFamilyPath($path)) {
            return $this->cardTemplatesForPath($payload, $path) !== [];
        }

        return Arr::has($payload, $path);
    }

    public function value(array $payload, string $path): mixed
    {
        if ($this->isRouteLabelPath($path)) {
            return $this->routeLabelForPath($payload, $path);
        }

        if ($this->isCardTemplateFamilyPath($path)) {
            return $this->cardTemplatesForPath($payload, $path);
        }

        return data_get($payload, $path);
    }

    public function setValue(array &$payload, string $path, mixed $value): void
    {
        if ($this->isRouteLabelPath($path)) {
            $this->setRouteLabelForPath($payload, $path, $value);

            return;
        }

        if ($this->isCardTemplateFamilyPath($path)) {
            $this->setCardTemplatesForPath($payload, $path, $value);

            return;
        }

        data_set($payload, $path, $value);
    }

    public function forgetValue(array &$payload, string $path): void
    {
        if ($this->isRouteLabelPath($path)) {
            $this->forgetRouteLabelForPath($payload, $path);

            return;
        }

        if ($this->isCardTemplateFamilyPath($path)) {
            $this->forgetCardTemplatesForPath($payload, $path);

            return;
        }

        Arr::forget($payload, $path);
    }

    /**
     * @return array<int, string>
     */
    public function unitPaths(?array $payload = null, ?string $group = null): array
    {
        return collect($this->units($payload, $group))
            ->pluck('path')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function unitPathsForSemanticPath(string $semanticPath, ?array $payload = null, ?string $group = null): array
    {
        $payload ??= $this->payloadForGroup($group);
        $units = $this->units($payload, $group);
        $unitPaths = collect($units)->pluck('path');
        $exactPaths = $unitPaths
            ->filter(fn (string $path): bool => $path === $semanticPath)
            ->values()
            ->all();

        if ($exactPaths !== []) {
            return $exactPaths;
        }

        $segmentedPath = $this->segmentedUnitPathForSemanticPath($semanticPath, $payload);

        if ($segmentedPath !== null) {
            $segmentedPaths = $unitPaths
                ->filter(fn (string $path): bool => $path === $segmentedPath)
                ->values()
                ->all();

            if ($segmentedPaths !== []) {
                return $segmentedPaths;
            }
        }

        $topLevel = str($semanticPath)->before('.')->toString();
        $segmentationMode = $this->groups
            ->get($group ?? $this->groups->defaultGroup()->name)
            ->overlay
            ->segmentationMode($topLevel);

        if (in_array($segmentationMode, ['route_key', 'card_family'], true)) {
            return [];
        }

        return collect($units)
            ->filter(function (SettingsLifecycleUnit $unit) use ($semanticPath): bool {
                if ($unit->path === $semanticPath) {
                    return true;
                }

                return str_starts_with($semanticPath, "{$unit->path}.");
            })
            ->pluck('path')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function segmentedUnitPathForSemanticPath(string $semanticPath, array $payload): ?string
    {
        $segments = explode('.', $semanticPath);
        $topLevel = $segments[0] ?? null;
        $index = $segments[1] ?? null;

        if (! in_array($topLevel, ['route_labels', 'card_templates'], true) || ! ctype_digit((string) $index)) {
            return null;
        }

        $item = $payload[$topLevel][(int) $index] ?? null;

        if (! is_array($item)) {
            return null;
        }

        $identity = $topLevel === 'route_labels'
            ? ($item['route_key'] ?? null)
            : ($item['family'] ?? null);

        if (! is_string($identity) || $identity === '' || str_contains($identity, '.')) {
            return null;
        }

        return "{$topLevel}.{$identity}";
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForGroup(?string $group = null): array
    {
        return $this->payloadFor($this->groups->get($group ?? $this->groups->defaultGroup()->name));
    }

    private function payloadFor(SettingsLifecycleGroup $group): array
    {
        if (isset($this->groupPayloads[$group->name])) {
            return $this->groupPayloads[$group->name];
        }

        $currentPayload = $group->currentPayload();

        return $this->groupPayloads[$group->name] = array_replace_recursive(
            array_intersect_key(PublicFrontConfigRegistry::defaults(), $currentPayload),
            $currentPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cacheKey(SettingsLifecycleGroup $group, array $payload): string
    {
        return $group->name.':'.hash('sha256', PublicSettingsPackage::canonicalPayloadJson($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function deriveUnitPaths(array $payload, SettingsLifecycleGroup $group): array
    {
        $paths = [];

        foreach ($payload as $property => $value) {
            if ($group->overlay->excludesTopLevelPath($property)) {
                continue;
            }

            $this->assertSafeSegment((string) $property);

            if ($group->overlay->segmentationMode($property) === 'route_key') {
                foreach (PublicFrontConfigRegistry::routeKeys() as $routeKey) {
                    $this->assertSafeSegment($routeKey);
                    $paths[] = "{$property}.{$routeKey}";
                }

                continue;
            }

            if ($group->overlay->segmentationMode($property) === 'card_family') {
                foreach (PublicFrontConfigRegistry::cardFamilies() as $family) {
                    $this->assertSafeSegment($family);
                    $paths[] = "{$property}.{$family}";
                }

                continue;
            }

            if ($group->overlay->segmentationMode($property) === 'whole') {
                $paths[] = $property;

                continue;
            }

            if (! is_array($value)) {
                $paths[] = $property;

                continue;
            }

            if ($value === [] || array_is_list($value)) {
                $paths[] = $property;

                continue;
            }

            foreach (array_keys($value) as $key) {
                $this->assertSafeSegment((string) $key);
                $paths[] = "{$property}.{$key}";
            }
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sectionFor(string $path, array $payload): string
    {
        $topLevel = explode('.', $path, 2)[0];

        if (! is_array($payload[$topLevel] ?? null)) {
            return '_scalars';
        }

        return $topLevel;
    }

    private function sectionLabelFor(string $section): string
    {
        if ($section === '_scalars') {
            return __('admin.settings_import.groups.scalars');
        }

        return $this->labelFor($section);
    }

    private function expectedScalarType(SettingsLifecycleGroup $group, string $path): ?string
    {
        if (str_contains($path, '.')) {
            return null;
        }

        /** @var class-string<Settings> $settingsClass */
        $settingsClass = $group->settingsClass;
        $reflection = new ReflectionClass($settingsClass);

        if (! $reflection->hasProperty($path)) {
            return null;
        }

        $type = $reflection->getProperty($path)->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }

    private function assertSafeSegment(string $segment): void
    {
        if (str_contains($segment, '.')) {
            throw new RuntimeException("Settings lifecycle unit segment [{$segment}] must not contain a dot.");
        }
    }

    private function isRouteLabelPath(string $path): bool
    {
        return str_starts_with($path, 'route_labels.')
            && in_array(str($path)->after('route_labels.')->toString(), PublicFrontConfigRegistry::routeKeys(), true);
    }

    private function isCardTemplateFamilyPath(string $path): bool
    {
        return str_starts_with($path, 'card_templates.')
            && in_array(str($path)->after('card_templates.')->toString(), PublicFrontConfigRegistry::cardFamilies(), true);
    }

    private function routeKeyFromPath(string $path): string
    {
        return str($path)->after('route_labels.')->toString();
    }

    private function cardFamilyFromPath(string $path): string
    {
        return str($path)->after('card_templates.')->toString();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeLabelForPath(array $payload, string $path): ?array
    {
        $routeKey = $this->routeKeyFromPath($path);

        $label = collect($payload['route_labels'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->first(fn (array $item): bool => ($item['route_key'] ?? null) === $routeKey);

        return is_array($label) ? $label : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cardTemplatesForPath(array $payload, string $path): array
    {
        $family = $this->cardFamilyFromPath($path);
        $templates = [];

        foreach ($payload['card_templates'] ?? [] as $item) {
            if (! is_array($item) || ($item['family'] ?? null) !== $family || blank($item['key'] ?? null)) {
                continue;
            }

            $key = (string) $item['key'];

            $templates[$key] ??= $item;
        }

        return $templates;
    }

    private function setRouteLabelForPath(array &$payload, string $path, mixed $value): void
    {
        $routeKey = $this->routeKeyFromPath($path);
        $labels = collect($payload['route_labels'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['route_key'] ?? null) !== $routeKey)
            ->values();

        if (is_array($value) && filled($value['label'] ?? null)) {
            $labels->push([
                'route_key' => $routeKey,
                'label' => (string) $value['label'],
            ]);
        }

        $payload['route_labels'] = $labels->values()->all();
    }

    private function forgetRouteLabelForPath(array &$payload, string $path): void
    {
        $routeKey = $this->routeKeyFromPath($path);

        $payload['route_labels'] = collect($payload['route_labels'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['route_key'] ?? null) !== $routeKey)
            ->values()
            ->all();
    }

    private function setCardTemplatesForPath(array &$payload, string $path, mixed $value): void
    {
        $family = $this->cardFamilyFromPath($path);
        $otherTemplates = collect($payload['card_templates'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['family'] ?? null) !== $family);

        $familyTemplates = collect(is_array($value) ? $value : [])
            ->values()
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['key'] ?? null))
            ->map(function (array $item) use ($family): array {
                $item['family'] = $family;

                return $item;
            })
            ->values()
            ->all();

        $payload['card_templates'] = $otherTemplates
            ->concat($familyTemplates)
            ->values()
            ->all();
    }

    private function forgetCardTemplatesForPath(array &$payload, string $path): void
    {
        $family = $this->cardFamilyFromPath($path);

        $payload['card_templates'] = collect($payload['card_templates'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['family'] ?? null) !== $family)
            ->values()
            ->all();
    }
}
