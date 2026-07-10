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

        return collect($this->deriveUnitPaths($payload, $registration))
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
        return collect($this->units($payload, $group))
            ->first(fn (SettingsLifecycleUnit $unit): bool => $unit->path === $path);
    }

    /**
     * @return array<string, SettingsLifecycleUnit>
     */
    public function unitsByPath(?array $payload = null, ?string $group = null): array
    {
        return collect($this->units($payload, $group))
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

        return $label === $key ? $path : $label;
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
        $units = $this->units($payload, $group);

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
     * @return array<string, mixed>
     */
    public function payloadForGroup(?string $group = null): array
    {
        return $this->payloadFor($this->groups->get($group ?? $this->groups->defaultGroup()->name));
    }

    private function payloadFor(SettingsLifecycleGroup $group): array
    {
        $currentPayload = $group->currentPayload();

        return array_replace_recursive(
            array_intersect_key(PublicFrontConfigRegistry::defaults(), $currentPayload),
            $currentPayload,
        );
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

        return collect($payload['card_templates'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['family'] ?? null) === $family && filled($item['key'] ?? null))
            ->mapWithKeys(fn (array $item): array => [(string) $item['key'] => $item])
            ->all();
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
            });

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
