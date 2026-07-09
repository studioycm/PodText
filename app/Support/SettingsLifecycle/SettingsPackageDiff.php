<?php

namespace App\Support\SettingsLifecycle;

class SettingsPackageDiff
{
    /**
     * @param  array<string, array{added: array<int, array{path: string, current: string}>, removed: array<int, array{path: string, backup: string}>, changed: array<int, array{path: string, backup: string, current: string}>}>  $groups
     */
    public function __construct(private readonly array $groups) {}

    public static function between(array $backupPayload, array $currentPayload): self
    {
        $backup = self::flatten($backupPayload);
        $current = self::flatten($currentPayload);
        $paths = collect(array_unique([...array_keys($backup), ...array_keys($current)]))->sort()->values();
        $groups = [];

        foreach ($paths as $path) {
            $group = self::groupFor($path);
            $groups[$group] ??= [
                'added' => [],
                'removed' => [],
                'changed' => [],
            ];

            if (! array_key_exists($path, $backup)) {
                $groups[$group]['added'][] = [
                    'path' => $path,
                    'current' => self::preview($current[$path]),
                ];

                continue;
            }

            if (! array_key_exists($path, $current)) {
                $groups[$group]['removed'][] = [
                    'path' => $path,
                    'backup' => self::preview($backup[$path]),
                ];

                continue;
            }

            if ($backup[$path] !== $current[$path]) {
                $groups[$group]['changed'][] = [
                    'path' => $path,
                    'backup' => self::preview($backup[$path]),
                    'current' => self::preview($current[$path]),
                ];
            }
        }

        return new self(array_filter(
            $groups,
            fn (array $group): bool => $group['added'] !== [] || $group['removed'] !== [] || $group['changed'] !== [],
        ));
    }

    public function hasChanges(): bool
    {
        return $this->groups !== [];
    }

    public function summaryCounts(): array
    {
        return collect($this->groups)
            ->reduce(function (array $counts, array $group): array {
                $counts['added'] += count($group['added']);
                $counts['removed'] += count($group['removed']);
                $counts['changed'] += count($group['changed']);

                return $counts;
            }, ['added' => 0, 'removed' => 0, 'changed' => 0]);
    }

    public function summaryText(): string
    {
        if (! $this->hasChanges()) {
            return __('admin.messages.settings_backup_no_diff');
        }

        $counts = $this->summaryCounts();

        return __('admin.messages.settings_backup_diff_summary', [
            'added' => $counts['added'],
            'removed' => $counts['removed'],
            'changed' => $counts['changed'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function lines(int $limit = 200): array
    {
        if (! $this->hasChanges()) {
            return [__('admin.messages.settings_backup_no_diff')];
        }

        $lines = [];

        foreach ($this->groups as $groupName => $group) {
            $lines[] = $groupName;

            foreach ($group['added'] as $entry) {
                $lines[] = "  + {$entry['path']}: current={$entry['current']}";
            }

            foreach ($group['removed'] as $entry) {
                $lines[] = "  - {$entry['path']}: backup={$entry['backup']}";
            }

            foreach ($group['changed'] as $entry) {
                $lines[] = "  * {$entry['path']}: backup={$entry['backup']} current={$entry['current']}";
            }

            if (count($lines) >= $limit) {
                $lines[] = __('admin.messages.settings_backup_diff_truncated');

                break;
            }
        }

        return array_slice($lines, 0, $limit + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function flatten(mixed $value, string $prefix = ''): array
    {
        if (! is_array($value)) {
            return [$prefix => $value];
        }

        if ($value === []) {
            return [$prefix => []];
        }

        $items = [];

        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $items += self::flatten($item, $path);
        }

        return $items;
    }

    private static function groupFor(string $path): string
    {
        return explode('.', $path, 2)[0];
    }

    private static function preview(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return PublicSettingsPackage::canonicalPayloadJson($value);
        }

        $string = (string) $value;

        return str($string)->limit(120)->toString();
    }
}
