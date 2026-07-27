<?php

namespace App\Support\SettingsLifecycle;

use App\Support\PublicFront\PublicFrontInvalidConfig;

class SettingsSubjectThreeWayMerger
{
    public function __construct(
        private readonly SettingsLifecycleSchema $schema,
        private readonly SettingsSubjectBaseline $baseline,
    ) {}

    /**
     * @param  array<string, array{exists: bool, hash: string}>  $openedRawBaseline
     * @param  array<string, array{exists: bool, hash: string}>  $openedEditableBaseline
     * @param  array<string, mixed>  $localCandidate
     * @param  array<string, mixed>  $freshRaw
     * @param  array<string, mixed>|null  $normalizedCandidate
     * @param  array<int, PublicFrontInvalidConfig>  $invalidConfig
     * @param  array<int, PublicFrontInvalidConfig>  $freshInvalidConfig
     * @param  array<string, mixed>|null  $semanticPayload
     * @param  array<string, mixed>|null  $localComparisonCandidate
     * @return array{
     *     payload: array<string, mixed>,
     *     conflict_path: ?string,
     *     invalid_path: ?string,
     *     invalid_unit_path: ?string
     * }
     */
    public function merge(
        array $openedRawBaseline,
        array $openedEditableBaseline,
        array $localCandidate,
        array $freshRaw,
        ?array $normalizedCandidate = null,
        array $invalidConfig = [],
        array $freshInvalidConfig = [],
        ?array $semanticPayload = null,
        ?array $localComparisonCandidate = null,
    ): array {
        $normalizedCandidate ??= $localCandidate;
        $semanticPayload ??= $localCandidate;
        $localComparisonCandidate ??= $normalizedCandidate;
        $localBaseline = $this->baseline->capture($localComparisonCandidate);
        $normalizedBaseline = $this->baseline->capture($normalizedCandidate);
        $freshBaseline = $this->baseline->capture($freshRaw);
        $paths = $this->baseline->paths(
            $openedRawBaseline,
            $openedEditableBaseline,
            $localBaseline,
            $normalizedBaseline,
            $freshBaseline,
        );
        $merged = $freshRaw;
        $localChanges = [];

        foreach ($paths as $path) {
            $localChanges[$path] = ! $this->baseline->matches(
                $openedEditableBaseline,
                $localComparisonCandidate,
                $path,
            );
        }

        foreach ($freshInvalidConfig as $freshInvalid) {
            if (! $freshInvalid instanceof PublicFrontInvalidConfig) {
                continue;
            }

            $unitPaths = $this->schema->unitPathsForSemanticPath(
                $freshInvalid->path,
                $freshRaw,
            );
            $changedUnitPath = collect($unitPaths)
                ->first(fn (string $path): bool => $localChanges[$path] ?? false);
            $topLevel = str($freshInvalid->path)->before('.')->toString();
            $changedUnknownSegmentedUnit = $unitPaths === []
                && collect($localChanges)->contains(
                    fn (bool $changed, string $path): bool => $changed
                        && ($path === $topLevel || str_starts_with($path, "{$topLevel}.")),
                );

            if (! is_string($changedUnitPath) && ! $changedUnknownSegmentedUnit) {
                continue;
            }

            return [
                'payload' => $freshRaw,
                'conflict_path' => null,
                'invalid_path' => $freshInvalid->path,
                'invalid_unit_path' => is_string($changedUnitPath)
                    ? $changedUnitPath
                    : $topLevel,
            ];
        }

        foreach ($invalidConfig as $invalid) {
            if (! $invalid instanceof PublicFrontInvalidConfig) {
                continue;
            }

            $unitPaths = $this->schema->unitPathsForSemanticPath(
                $invalid->path,
                $semanticPayload,
            );
            $changedUnitPath = collect($unitPaths)
                ->first(fn (string $path): bool => $localChanges[$path] ?? false);

            if (
                is_string($changedUnitPath)
                || ! $this->invalidFindingExistsInFresh(
                    $invalid,
                    $freshInvalidConfig,
                    $semanticPayload,
                    $freshRaw,
                )
            ) {
                return [
                    'payload' => $freshRaw,
                    'conflict_path' => null,
                    'invalid_path' => $invalid->path,
                    'invalid_unit_path' => is_string($changedUnitPath)
                        ? $changedUnitPath
                        : (count($unitPaths) === 1
                            ? $unitPaths[0]
                            : str($invalid->path)->before('.')->toString()),
                ];
            }
        }

        foreach ($paths as $path) {
            $localChanged = $localChanges[$path] ?? false;
            $freshChanged = ! $this->baseline->matches(
                $openedRawBaseline,
                $freshRaw,
                $path,
            );

            if (! $localChanged) {
                continue;
            }

            if ($freshChanged && ! $this->baseline->valuesMatch($normalizedCandidate, $freshRaw, $path)) {
                return [
                    'payload' => $freshRaw,
                    'conflict_path' => $path,
                    'invalid_path' => null,
                    'invalid_unit_path' => null,
                ];
            }

            $this->applyLocalUnit($merged, $normalizedCandidate, $path);
        }

        return [
            'payload' => $merged,
            'conflict_path' => null,
            'invalid_path' => null,
            'invalid_unit_path' => null,
        ];
    }

    /**
     * @param  array<string, array{exists: bool, hash: string}>  $openedEditableBaseline
     * @param  array<string, mixed>  $localCandidate
     * @param  array<string, mixed>  $freshRaw
     * @param  array<string, mixed>  $normalizedLocalCandidate
     * @param  array<int, PublicFrontInvalidConfig>  $invalidConfig
     * @param  array<int, PublicFrontInvalidConfig>  $freshInvalidConfig
     * @return array<string, mixed>
     */
    public function validationPayload(
        array $openedEditableBaseline,
        array $localCandidate,
        array $freshRaw,
        array $normalizedLocalCandidate,
        array $invalidConfig,
        array $freshInvalidConfig,
    ): array {
        $localBaseline = $this->baseline->capture($normalizedLocalCandidate);
        $freshBaseline = $this->baseline->capture($freshRaw);
        $paths = $this->baseline->paths(
            $openedEditableBaseline,
            $localBaseline,
            $freshBaseline,
        );
        $payload = $freshRaw;
        $localChanges = [];

        foreach ($paths as $path) {
            $localChanges[$path] = ! $this->baseline->matches(
                $openedEditableBaseline,
                $normalizedLocalCandidate,
                $path,
            );
        }

        foreach ($invalidConfig as $invalid) {
            if (
                ! $invalid instanceof PublicFrontInvalidConfig
                || $this->invalidFindingExistsInFresh(
                    $invalid,
                    $freshInvalidConfig,
                    $localCandidate,
                    $freshRaw,
                )
            ) {
                continue;
            }

            $unitPaths = $this->schema->unitPathsForSemanticPath(
                $invalid->path,
                $localCandidate,
            );

            if (count($unitPaths) === 1) {
                $localChanges[$unitPaths[0]] = true;

                continue;
            }

            $topLevel = str($invalid->path)->before('.')->toString();

            if (array_key_exists($topLevel, $localCandidate)) {
                $payload[$topLevel] = $localCandidate[$topLevel];
            }
        }

        foreach ($paths as $path) {
            if (! ($localChanges[$path] ?? false)) {
                continue;
            }

            $this->applyLocalUnit($payload, $localCandidate, $path);
        }

        return $payload;
    }

    /**
     * @param  array<int, PublicFrontInvalidConfig>  $freshInvalidConfig
     * @param  array<string, mixed>  $semanticPayload
     * @param  array<string, mixed>  $freshRaw
     */
    private function invalidFindingExistsInFresh(
        PublicFrontInvalidConfig $invalid,
        array $freshInvalidConfig,
        array $semanticPayload,
        array $freshRaw,
    ): bool {
        $unitPaths = $this->schema->unitPathsForSemanticPath(
            $invalid->path,
            $semanticPayload,
        );

        foreach ($freshInvalidConfig as $freshInvalid) {
            if (
                ! $freshInvalid instanceof PublicFrontInvalidConfig
                || $freshInvalid->reason !== $invalid->reason
            ) {
                continue;
            }

            $freshUnitPaths = $this->schema->unitPathsForSemanticPath(
                $freshInvalid->path,
                $freshRaw,
            );

            if (
                count($unitPaths) === 1
                && $freshUnitPaths === $unitPaths
                && $this->baseline->valuesMatch(
                    $semanticPayload,
                    $freshRaw,
                    $unitPaths[0],
                )
            ) {
                return true;
            }

            if (
                $freshInvalid->path === $invalid->path
                && $this->semanticValueState($semanticPayload, $invalid->path)
                    === $this->semanticValueState($freshRaw, $freshInvalid->path)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{exists: bool, value: mixed}
     */
    private function semanticValueState(array $payload, string $path): array
    {
        $sentinel = new \stdClass;
        $value = data_get($payload, $path, $sentinel);

        return [
            'exists' => $value !== $sentinel,
            'value' => $value === $sentinel ? null : $value,
        ];
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $local
     */
    private function applyLocalUnit(array &$merged, array $local, string $path): void
    {
        if (! $this->schema->valueExists($local, $path)) {
            $this->schema->forgetValue($merged, $path);

            return;
        }

        $this->schema->setValue(
            $merged,
            $path,
            $this->schema->value($local, $path),
        );
    }
}
