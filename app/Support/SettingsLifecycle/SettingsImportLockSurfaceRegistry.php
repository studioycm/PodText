<?php

namespace App\Support\SettingsLifecycle;

class SettingsImportLockSurfaceRegistry
{
    /** @var array<int, string> */
    private const IMPORTANT_FIELDS = [
        'maintenance.enabled',
        'maintenance.raw_html_override',
        'public_forms.require_email_verification',
        'transcription_policy.public_mode',
        'transcription_policy.count_mode',
        'transcription_policy.show_multiple_transcriptions_on_item_page',
    ];

    public function __construct(
        private readonly SettingsLifecycleSchema $schema,
    ) {}

    /**
     * @return array<int, array{id: string, type: string, group: string, group_label: string, label: string, unit_paths: array<int, string>}>
     */
    public function surfaces(?array $payload = null): array
    {
        $payload ??= $this->schema->payloadForGroup();
        $units = $this->schema->units($payload);
        $sections = collect($units)
            ->groupBy('section')
            ->map(function ($sectionUnits, string $section): array {
                /** @var SettingsLifecycleUnit $first */
                $first = $sectionUnits->first();

                return [
                    'id' => "section:{$section}",
                    'type' => 'section',
                    'group' => $section,
                    'group_label' => $first->sectionLabel,
                    'label' => $first->sectionLabel,
                    'unit_paths' => $sectionUnits->pluck('path')->values()->all(),
                ];
            })
            ->values();

        $fields = collect(self::IMPORTANT_FIELDS)
            ->map(function (string $path) use ($payload): ?array {
                $unit = $this->schema->unitFor($path, $payload);

                if (! $unit) {
                    return null;
                }

                return [
                    'id' => "field:{$path}",
                    'type' => 'field',
                    'group' => $unit->section,
                    'group_label' => $unit->sectionLabel,
                    'label' => $unit->label,
                    'unit_paths' => [$unit->path],
                ];
            })
            ->filter();

        return $sections->concat($fields)->values()->all();
    }

    /** @return array<int, string> */
    public function sectionUnitPaths(string $section, ?array $payload = null): array
    {
        return $this->surface("section:{$section}", $payload)['unit_paths'] ?? [];
    }

    public function importantFieldUnitPath(string $semanticPath, ?array $payload = null): ?string
    {
        return $this->surface("field:{$semanticPath}", $payload)['unit_paths'][0] ?? null;
    }

    /** @param array<int, string> $surfaceIds
     * @return array<int, string>
     */
    public function unitPathsForSurfaceIds(array $surfaceIds, ?array $payload = null): array
    {
        $surfaces = collect($this->surfaces($payload))->keyBy('id');
        $paths = collect($surfaceIds)
            ->flatMap(fn (string $id): array => $surfaces->get($id)['unit_paths'] ?? [])
            ->unique()
            ->values()
            ->all();

        sort($paths);

        return $paths;
    }

    /** @param array<int, string> $lockedPaths
     * @return array<int, string>
     */
    public function selectedSurfaceIds(array $lockedPaths, ?array $payload = null): array
    {
        return collect($this->surfaces($payload))
            ->filter(fn (array $surface): bool => $surface['unit_paths'] !== []
                && array_diff($surface['unit_paths'], $lockedPaths) === [])
            ->pluck('id')
            ->values()
            ->all();
    }

    /** @param array<int, string> $lockedPaths
     * @return array<int, string>
     */
    public function retiredLockedPaths(array $lockedPaths, ?array $payload = null): array
    {
        $covered = $this->unitPathsForSurfaceIds($this->selectedSurfaceIds($lockedPaths, $payload), $payload);

        return collect($lockedPaths)->diff($covered)->values()->all();
    }

    /** @return array<int, string> */
    public function surfaceIdsForFrontText(?array $payload = null): array
    {
        $payload ??= $this->schema->payloadForGroup();
        $frontTextPaths = collect($this->schema->overlaySemantics()['front_text'] ?? [])
            ->flatMap(fn (string $path): array => $this->schema->unitPathsForSemanticPath($path, $payload))
            ->unique();
        $surfaces = collect($this->surfaces($payload));

        return $frontTextPaths
            ->map(function (string $path) use ($surfaces): ?string {
                $field = $surfaces->first(fn (array $surface): bool => $surface['type'] === 'field'
                    && $surface['unit_paths'] === [$path]);

                if ($field) {
                    return $field['id'];
                }

                return $surfaces->first(fn (array $surface): bool => $surface['type'] === 'section'
                    && in_array($path, $surface['unit_paths'], true))['id'] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, type: string, group: string, group_label: string, label: string, unit_paths: array<int, string>}|null
     */
    private function surface(string $id, ?array $payload = null): ?array
    {
        return collect($this->surfaces($payload))->firstWhere('id', $id);
    }
}
