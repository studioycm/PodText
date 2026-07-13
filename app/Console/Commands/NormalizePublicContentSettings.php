<?php

namespace App\Console\Commands;

use App\Models\SettingsBackupVersion;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigResult;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use Illuminate\Console\Command;
use Spatie\LaravelSettings\SettingsContainer;

class NormalizePublicContentSettings extends Command
{
    protected $signature = 'settings:normalize-public-content
        {--apply : Persist normalized public content JSON settings after creating a system backup.}';

    protected $description = 'Report and optionally persist normalized public content JSON settings.';

    public function __construct(
        private readonly PublicFrontConfigValidator $validator,
        private readonly SettingsBackupManager $backups,
        private readonly PublicFrontConfigCache $cache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $payload = PublicSettingsPackage::fromCurrentSettings()->payload();
        $settingsKeys = PublicFrontConfigRegistry::settingsKeys();
        $settingGroups = array_intersect_key($payload, array_flip($settingsKeys));
        $result = $this->validator->validateGroups($settingGroups, $settingsKeys);
        $normalizedGroups = $result->config();
        $report = $this->report($payload, $normalizedGroups, $result, $settingsKeys);

        $this->renderReport($report, $result);

        if (! $this->option('apply')) {
            $this->components->warn('Dry run only. Re-run with --apply to write normalized values.');

            return self::SUCCESS;
        }

        if (! $this->hasChanges($payload, $normalizedGroups, $settingsKeys)) {
            $this->components->info('No public content JSON settings changes to apply.');

            return self::SUCCESS;
        }

        $backup = $this->backups->createSystem();
        $settings = app(PublicContentSettings::class);

        foreach ($normalizedGroups as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->{$key} = $value;
            }
        }

        $settings->save();
        $this->forgetState();

        if ($backup instanceof SettingsBackupVersion) {
            $this->components->info("Created system backup #{$backup->getKey()}.");
        } else {
            $this->components->info('System backup skipped because an identical backup already exists.');
        }

        $this->components->info('Normalized public content JSON settings saved.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $normalizedGroups
     * @param  array<int, string>  $settingsKeys
     * @return array<int, array{group: string, unknown_keys_dropped: int, invalid_values_reset: int, missing_keys_filled: int, changed: bool}>
     */
    private function report(array $payload, array $normalizedGroups, PublicFrontConfigResult $result, array $settingsKeys): array
    {
        $defaults = PublicFrontConfigRegistry::defaults();
        $invalid = collect($result->invalidConfigArray());

        return collect($settingsKeys)
            ->map(function (string $key) use ($payload, $normalizedGroups, $defaults, $invalid): array {
                $groupInvalid = $invalid
                    ->filter(fn (array $warning): bool => $warning['path'] === $key || str_starts_with($warning['path'], "{$key}."));
                $rawValue = $payload[$key] ?? null;
                $normalizedValue = $normalizedGroups[$key] ?? $defaults[$key];
                $unknownKeys = $groupInvalid
                    ->whereIn('reason', ['unknown_top_level_key', 'unknown_nested_key'])
                    ->count();

                return [
                    'group' => $key,
                    'unknown_keys_dropped' => $unknownKeys,
                    'invalid_values_reset' => $groupInvalid->count() - $unknownKeys,
                    'missing_keys_filled' => $this->missingKeyCount($defaults[$key], $rawValue),
                    'changed' => $this->canonical($rawValue) !== $this->canonical($normalizedValue),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{group: string, unknown_keys_dropped: int, invalid_values_reset: int, missing_keys_filled: int, changed: bool}>  $report
     */
    private function renderReport(array $report, PublicFrontConfigResult $result): void
    {
        $this->components->info('Public content JSON settings normalization report.');

        $this->table(
            ['Group', 'Unknown keys dropped', 'Invalid values reset', 'Missing keys filled', 'Changed'],
            collect($report)
                ->map(fn (array $row): array => [
                    $row['group'],
                    $row['unknown_keys_dropped'],
                    $row['invalid_values_reset'],
                    $row['missing_keys_filled'],
                    $row['changed'] ? 'yes' : 'no',
                ])
                ->all(),
        );

        if (! $result->hasInvalidConfig()) {
            $this->components->info('No invalid public content JSON values were found.');

            return;
        }

        $this->newLine();
        $this->line('Invalid config warnings:');

        foreach (array_slice($result->invalidConfigArray(), 0, 20) as $warning) {
            $this->line(" - {$warning['path']}: {$warning['reason']}");
        }

        if (count($result->invalidConfigArray()) > 20) {
            $remaining = count($result->invalidConfigArray()) - 20;

            $this->line(" - ... {$remaining} more warning(s)");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $normalizedGroups
     * @param  array<int, string>  $settingsKeys
     */
    private function hasChanges(array $payload, array $normalizedGroups, array $settingsKeys): bool
    {
        $currentGroups = collect($settingsKeys)
            ->mapWithKeys(fn (string $key): array => [$key => $payload[$key] ?? null])
            ->all();

        return $this->canonical($currentGroups) !== $this->canonical($normalizedGroups);
    }

    private function missingKeyCount(mixed $defaults, mixed $raw): int
    {
        if (! is_array($defaults)) {
            return 0;
        }

        if (! $this->isAssociativeArray($defaults)) {
            return 0;
        }

        if (! is_array($raw)) {
            return $this->defaultPathCount($defaults);
        }

        $missing = 0;

        foreach ($defaults as $key => $defaultValue) {
            if (! array_key_exists($key, $raw)) {
                $missing += $this->defaultPathCount($defaultValue);

                continue;
            }

            if (is_array($defaultValue) && $this->isAssociativeArray($defaultValue)) {
                $missing += $this->missingKeyCount($defaultValue, $raw[$key]);
            }
        }

        return $missing;
    }

    private function defaultPathCount(mixed $value): int
    {
        if (! is_array($value) || ! $this->isAssociativeArray($value)) {
            return 1;
        }

        return array_sum(array_map($this->defaultPathCount(...), $value));
    }

    private function isAssociativeArray(array $value): bool
    {
        return $value !== [] && ! array_is_list($value);
    }

    private function canonical(mixed $value): string
    {
        return json_encode($this->sortKeysRecursively($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function sortKeysRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->sortKeysRecursively(...), $value);
        }

        ksort($value);

        return array_map($this->sortKeysRecursively(...), $value);
    }

    private function forgetState(): void
    {
        $this->cache->forget();
        app()->forgetInstance(PublicContentSettings::class);
        app()->forgetInstance(PublicFrontRenderContext::class);
        app(SettingsContainer::class)->clearCache();
    }
}
