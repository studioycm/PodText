<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Settings\PublicContentSettings;
use App\Support\Media\LegacyMediaTransitionPlanner;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Console\Command;
use RuntimeException;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Spatie\LaravelSettings\SettingsContainer;
use Throwable;

class BackfillSettingsMediaReferenceKeys extends Command
{
    protected $signature = 'media:backfill-settings-reference-keys
        {--apply : Persist unambiguous media reference keys in public settings}
        {--digest= : Exact reviewed transition manifest digest required for apply}';

    protected $description = 'Report or backfill immutable media reference keys alongside legacy public-settings paths';

    public function handle(
        SettingsMediaIdentityProjector $projector,
        PublicFrontConfigCache $cache,
        LegacyMediaTransitionPlanner $planner,
        PublicContentSettingsWriteCoordinator $writeCoordinator,
    ): int {
        $manifest = $planner->manifest();
        $planner->assertClosed($manifest);
        if ($this->option('apply') && (! is_string($this->option('digest')) || ! hash_equals((string) $this->option('digest'), $manifest->digest()))) {
            $this->error('Apply requires the exact reviewed transition manifest --digest.');

            return self::FAILURE;
        }
        $readiness = $this->settingsReadiness($manifest->entries);
        if ($readiness !== []) {
            $this->table(['setting reference', 'disposition', 'reason'], $readiness);
            if ($this->option('apply')) {
                $this->error('Settings backfill is ordered after keyed/transitioned media and attachment reconciliation.');

                return self::FAILURE;
            }

            $this->warn('Dry run only. Settings media references still require their reviewed transition or explicit detach-to-default.');

            return self::SUCCESS;
        }

        $repository = app(PublicContentSettings::class)->getRepository();
        $payload = $repository->getPropertiesInGroup(PublicContentSettings::group());

        try {
            $reconciled = $projector->backfill($payload);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $changed = collect(['menu_config', 'about_page', 'default_images'])
            ->filter(fn (string $name): bool => ($payload[$name] ?? null) !== ($reconciled[$name] ?? null))
            ->values();

        if ($changed->isEmpty() && $readiness === []) {
            $this->info('Settings media reference keys are already reconciled.');

            return self::SUCCESS;
        }

        $this->table(
            ['setting', 'disposition'],
            $changed->map(fn (string $name): array => [
                $name,
                $this->option('apply') ? 'backfilled' : 'would backfill',
            ])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply after reviewing the report.');

            return self::SUCCESS;
        }

        try {
            $updated = $writeCoordinator->transaction(function () use ($projector, $planner, $manifest): int {
                app()->forgetInstance(PublicContentSettings::class);
                app(SettingsContainer::class)->clearCache();
                $repository = app(PublicContentSettings::class)->getRepository();
                $names = ['menu_config', 'about_page', 'default_images'];
                $properties = SettingsProperty::query()
                    ->where('group', PublicContentSettings::group())
                    ->whereIn('name', $names)
                    ->lockForUpdate()
                    ->get(['name', 'locked']);

                if ($properties->contains(fn (SettingsProperty $property): bool => (bool) $property->locked)) {
                    throw new RuntimeException('A public settings property is locked; no media keys were backfilled.');
                }

                $mediaIds = collect($manifest->entries)
                    ->filter(fn (array $entry): bool => collect($entry['active_references'] ?? [])->contains(fn (string $token): bool => str_starts_with($token, 'settings:')))
                    ->pluck('media_id')
                    ->filter(fn (mixed $id): bool => (int) $id > 0)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                if ($mediaIds !== []) {
                    Media::query()->whereKey($mediaIds)->lockForUpdate()->get(['id']);
                }

                $freshManifest = $planner->manifest();
                if (! hash_equals($manifest->digest(), $freshManifest->digest())) {
                    throw new RuntimeException('The reviewed transition manifest changed before settings backfill.');
                }
                if ($this->settingsReadiness($freshManifest->entries) !== []) {
                    throw new RuntimeException('A referenced setting media row is not yet transition-ready.');
                }

                $fresh = $repository->getPropertiesInGroup(PublicContentSettings::group());
                $planned = $projector->backfill($fresh);
                $updates = collect($names)
                    ->filter(fn (string $name): bool => ($fresh[$name] ?? null) !== ($planned[$name] ?? null))
                    ->mapWithKeys(fn (string $name): array => [$name => $planned[$name]])
                    ->all();

                if ($updates !== []) {
                    $repository->updatePropertiesPayload(PublicContentSettings::group(), $updates);
                }

                return count($updates);
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $cache->forget();
        app()->forgetInstance(PublicContentSettings::class);
        $this->info("Backfilled {$updated} public settings group(s).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function settingsReadiness(array $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $tokens = collect($entry['active_references'] ?? [])
                ->filter(fn (string $token): bool => str_starts_with($token, 'settings:'));
            if ($tokens->isEmpty()) {
                continue;
            }

            $ready = filled($entry['reference_key'] ?? null);
            if ($ready) {
                continue;
            }
            $disposition = (string) ($entry['disposition'] ?? 'blocked');
            $reason = match ($disposition) {
                'key_only', 'normalize_existing', 'sanitize_svg', 'import_exact_path' => 'transition_pending',
                'detach_to_default' => 'detach_to_default',
                default => (string) ($entry['reason'] ?? 'blocked'),
            };
            foreach ($tokens as $token) {
                $rows[] = [(string) $token, $disposition, $reason];
            }
        }

        return $rows;
    }
}
