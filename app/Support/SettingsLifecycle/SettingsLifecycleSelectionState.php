<?php

namespace App\Support\SettingsLifecycle;

class SettingsLifecycleSelectionState
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $selectedPaths
     */
    public function groupState(array $rows, array $selectedPaths, string $group): string
    {
        $paths = $this->selectableGroupPaths($rows, $group);

        if ($paths === []) {
            return 'none';
        }

        $selected = array_values(array_intersect($paths, $selectedPaths));

        if (count($selected) === 0) {
            return 'none';
        }

        return count($selected) === count($paths) ? 'all' : 'some';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $selectedPaths
     * @return array<int, string>
     */
    public function toggleGroup(array $rows, array $selectedPaths, string $group): array
    {
        $paths = $this->selectableGroupPaths($rows, $group);

        if ($this->groupState($rows, $selectedPaths, $group) === 'all') {
            return array_values(array_diff($selectedPaths, $paths));
        }

        return collect([...$selectedPaths, ...$paths])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $selectedPaths
     * @return array<int, string>
     */
    public function togglePath(array $rows, array $selectedPaths, string $path): array
    {
        $row = collect($rows)->first(fn (array $candidate): bool => ($candidate['path'] ?? null) === $path);

        if (! (bool) ($row['selectable'] ?? false)) {
            return $selectedPaths;
        }

        if (in_array($path, $selectedPaths, true)) {
            return array_values(array_diff($selectedPaths, [$path]));
        }

        return collect([...$selectedPaths, $path])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function selectableGroupPaths(array $rows, string $group): array
    {
        return collect($rows)
            ->filter(fn (array $row): bool => ($row['group'] ?? null) === $group && (bool) ($row['selectable'] ?? false))
            ->pluck('path')
            ->values()
            ->all();
    }
}
