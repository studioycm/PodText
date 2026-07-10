<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\SettingsImportMode;
use App\Models\SettingsBackupVersion;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

class SettingsImportReport implements Arrayable
{
    public const APPLIED = 'applied';

    public const SKIPPED_LOCKED = 'skipped_locked';

    public const SKIPPED_EXISTS = 'skipped_exists';

    public const SKIPPED_UNCHANGED = 'skipped_unchanged';

    public const ERRORS = 'errors';

    public const OUTCOME_GROUPS = [
        self::APPLIED,
        self::SKIPPED_LOCKED,
        self::SKIPPED_EXISTS,
        self::SKIPPED_UNCHANGED,
        self::ERRORS,
    ];

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $outcomes
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly string $mode,
        public readonly ?string $sourceLabel,
        public readonly string $generatedAt,
        public readonly ?int $beforeImportBackupId,
        public readonly array $outcomes,
        public readonly array $warnings = [],
    ) {}

    /**
     * @param  array<int, string>  $selectedPaths
     * @param  array<int, string>  $appliedPaths
     */
    public static function fromAnalysis(
        SettingsPackageImportAnalysis $analysis,
        array $selectedPaths,
        array $appliedPaths,
        SettingsBackupVersion $beforeImportBackup,
        SettingsImportMode $mode,
        ?string $sourceLabel = null,
    ): self {
        $selectedPaths = array_values(array_unique($selectedPaths));
        $appliedPaths = array_values(array_unique($appliedPaths));

        return new self(
            mode: $mode->value,
            sourceLabel: $sourceLabel,
            generatedAt: now()->toIso8601String(),
            beforeImportBackupId: (int) $beforeImportBackup->getKey(),
            outcomes: [
                self::APPLIED => self::rowsFor($analysis, $appliedPaths, self::APPLIED, $selectedPaths),
                self::SKIPPED_LOCKED => self::rowsForOutcome($analysis, 'skip_locked', self::SKIPPED_LOCKED, $selectedPaths),
                self::SKIPPED_EXISTS => self::rowsForOutcome($analysis, 'skip_exists', self::SKIPPED_EXISTS, $selectedPaths),
                self::SKIPPED_UNCHANGED => self::rowsForOutcome($analysis, 'skip_unchanged', self::SKIPPED_UNCHANGED, $selectedPaths),
                self::ERRORS => self::rowsForOutcome($analysis, 'error', self::ERRORS, $selectedPaths),
            ],
            warnings: $analysis->warnings,
        );
    }

    /**
     * @param  array<string, mixed>|null  $report
     */
    public static function fromArray(?array $report): self
    {
        $report ??= [];

        return new self(
            mode: (string) ($report['mode'] ?? SettingsImportMode::Replace->value),
            sourceLabel: isset($report['source_label']) ? (string) $report['source_label'] : null,
            generatedAt: (string) ($report['generated_at'] ?? now()->toIso8601String()),
            beforeImportBackupId: isset($report['before_import_backup_id']) ? (int) $report['before_import_backup_id'] : null,
            outcomes: self::normalizeOutcomes($report['outcomes'] ?? []),
            warnings: array_values(array_filter(
                (array) ($report['warnings'] ?? []),
                fn (mixed $warning): bool => is_string($warning) && filled($warning),
            )),
        );
    }

    public function appliedCount(): int
    {
        return count($this->outcomes[self::APPLIED] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function appliedPaths(): array
    {
        return collect($this->outcomes[self::APPLIED] ?? [])
            ->pluck('path')
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function outcomeRows(string $outcome): array
    {
        return $this->outcomes[$outcome] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function outcomeLines(string $outcome): array
    {
        return collect($this->outcomeRows($outcome))
            ->map(fn (array $row): string => $this->lineForRow($row))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'source_label' => $this->sourceLabel,
            'generated_at' => $this->generatedAt,
            'before_import_backup_id' => $this->beforeImportBackupId,
            'outcomes' => self::normalizeOutcomes($this->outcomes),
            'warnings' => array_values($this->warnings),
        ];
    }

    public function generatedAtLabel(): string
    {
        return Carbon::parse($this->generatedAt)->timezone('Asia/Jerusalem')->format('d/m/Y H:i');
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $selectedPaths
     * @return array<int, array<string, mixed>>
     */
    private static function rowsFor(SettingsPackageImportAnalysis $analysis, array $paths, string $group, array $selectedPaths): array
    {
        $rows = $analysis->rowsByPath();

        return collect($paths)
            ->map(fn (string $path): ?array => isset($rows[$path])
                ? self::reportRow($rows[$path], $group, in_array($path, $selectedPaths, true))
                : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $selectedPaths
     * @return array<int, array<string, mixed>>
     */
    private static function rowsForOutcome(SettingsPackageImportAnalysis $analysis, string $outcome, string $group, array $selectedPaths): array
    {
        return collect($analysis->rows)
            ->filter(fn (array $row): bool => ($row['outcome'] ?? null) === $outcome)
            ->map(fn (array $row): array => self::reportRow($row, $group, in_array($row['path'] ?? null, $selectedPaths, true)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function reportRow(array $row, string $group, bool $selected): array
    {
        $reason = $group === self::ERRORS
            ? (string) ($row['error'] ?? __('admin.settings_import_report.reasons.error'))
            : __('admin.settings_import_report.reasons.'.$group);

        $reportRow = [
            'path' => (string) ($row['path'] ?? ''),
            'label' => (string) ($row['label'] ?? ($row['path'] ?? '')),
            'group' => (string) ($row['group'] ?? ''),
            'group_label' => (string) ($row['group_label'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
            'outcome' => $group,
            'reason' => $reason,
            'selected' => $selected,
        ];

        if ($group === self::SKIPPED_LOCKED) {
            $reportRow['lock'] = [
                'label' => __('admin.settings_import_report.lock_import_locks'),
                'path' => $reportRow['path'],
            ];
        }

        return $reportRow;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function normalizeOutcomes(mixed $outcomes): array
    {
        $outcomes = is_array($outcomes) ? $outcomes : [];

        return collect(self::OUTCOME_GROUPS)
            ->mapWithKeys(fn (string $group): array => [
                $group => collect($outcomes[$group] ?? [])
                    ->filter(fn (mixed $row): bool => is_array($row))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function lineForRow(array $row): string
    {
        $label = (string) ($row['label'] ?? $row['path'] ?? '');
        $path = (string) ($row['path'] ?? '');
        $reason = (string) ($row['reason'] ?? '');

        return filled($reason)
            ? "{$label} ({$path}) - {$reason}"
            : "{$label} ({$path})";
    }
}
