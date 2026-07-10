<?php

namespace App\Support\SettingsLifecycle;

class SettingsPackageImportAnalysis
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $selectedPaths
     */
    public function __construct(
        public readonly PublicSettingsPackage $package,
        public readonly array $rows,
        public readonly array $warnings = [],
        public readonly array $errors = [],
        public readonly array $selectedPaths = [],
    ) {}

    public function refused(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<int, string>
     */
    public function selectablePaths(): array
    {
        return collect($this->rows)
            ->filter(fn (array $row): bool => (bool) ($row['selectable'] ?? false))
            ->pluck('path')
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function rowsByPath(): array
    {
        return collect($this->rows)
            ->keyBy('path')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function signatureRows(): array
    {
        return collect($this->rows)
            ->map(fn (array $row): array => [
                'path' => $row['path'],
                'state' => $row['state'],
                'outcome' => $row['outcome'],
                'current_preview' => $row['current_preview'],
                'imported_preview' => $row['imported_preview'],
                'error' => $row['error'],
            ])
            ->values()
            ->all();
    }
}
