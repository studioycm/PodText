<?php

namespace App\Support\SettingsLifecycle;

use App\Support\PublicFront\PublicFrontConfigRegistry;
use ReflectionClass;
use ReflectionNamedType;
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

        return collect($this->unitPaths($payload, $registration))
            ->map(fn (string $path): SettingsLifecycleUnit => new SettingsLifecycleUnit(
                group: $registration->name,
                path: $path,
                label: $this->labelFor($path),
                labelKey: $this->labelKeyFor($path),
                section: $this->sectionFor($path, $payload),
                sectionLabel: $this->sectionLabelFor($this->sectionFor($path, $payload)),
                structuralType: $this->structuralType(data_get($payload, $path)),
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
    private function unitPaths(array $payload, SettingsLifecycleGroup $group): array
    {
        $paths = [];

        foreach ($payload as $property => $value) {
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
}
