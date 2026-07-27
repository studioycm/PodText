<?php

namespace App\Support\SettingsLifecycle;

class SettingsSubjectBaseline
{
    public function __construct(
        private readonly SettingsLifecycleSchema $schema,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{exists: bool, hash: string}>
     */
    public function capture(array $payload): array
    {
        $baseline = [];

        foreach ($this->schema->unitPaths($payload) as $path) {
            $baseline[$path] = $this->state($payload, $path);
        }

        return $baseline;
    }

    /**
     * @param  array<string, array{exists: bool, hash: string}>  $baseline
     * @param  array<string, mixed>  $payload
     */
    public function matches(array $baseline, array $payload, string $path): bool
    {
        return ($baseline[$path] ?? $this->missingState()) === $this->state($payload, $path);
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    public function valuesMatch(array $first, array $second, string $path): bool
    {
        return $this->state($first, $path) === $this->state($second, $path);
    }

    /**
     * @param  array<string, array{exists: bool, hash: string}>  ...$baselines
     * @return array<int, string>
     */
    public function paths(array ...$baselines): array
    {
        $paths = [];

        foreach ($baselines as $baseline) {
            $paths = [...$paths, ...array_keys($baseline)];
        }

        sort($paths);

        $paths = array_values(array_unique($paths));

        return array_values(array_filter(
            $paths,
            fn (string $path): bool => ! collect($paths)->contains(
                fn (string $candidate): bool => $candidate !== $path
                    && str_starts_with($candidate, "{$path}."),
            ),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{exists: bool, hash: string}
     */
    private function state(array $payload, string $path): array
    {
        $exists = $this->schema->valueExists($payload, $path);

        return [
            'exists' => $exists,
            'hash' => hash(
                'sha256',
                PublicSettingsPackage::canonicalPayloadJson([
                    'exists' => $exists,
                    'value' => $exists ? $this->schema->value($payload, $path) : null,
                ]),
            ),
        ];
    }

    /**
     * @return array{exists: bool, hash: string}
     */
    private function missingState(): array
    {
        return [
            'exists' => false,
            'hash' => hash(
                'sha256',
                PublicSettingsPackage::canonicalPayloadJson([
                    'exists' => false,
                    'value' => null,
                ]),
            ),
        ];
    }
}
