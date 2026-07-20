<?php

namespace App\Console\Commands;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Throwable;

class BackfillSettingsMediaReferenceKeys extends Command
{
    protected $signature = 'media:backfill-settings-reference-keys
        {--apply : Persist unambiguous media reference keys in public settings}';

    protected $description = 'Report or backfill immutable media reference keys alongside legacy public-settings paths';

    public function handle(
        PublicContentSettings $settings,
        SettingsMediaIdentityProjector $projector,
        PublicFrontConfigCache $cache,
    ): int {
        $repository = $settings->getRepository();
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

        if ($changed->isEmpty()) {
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
            $updated = DB::transaction(function () use ($repository, $projector): int {
                $names = ['menu_config', 'about_page', 'default_images'];
                $properties = SettingsProperty::query()
                    ->where('group', PublicContentSettings::group())
                    ->whereIn('name', $names)
                    ->lockForUpdate()
                    ->get(['name', 'locked']);

                if ($properties->contains(fn (SettingsProperty $property): bool => (bool) $property->locked)) {
                    throw new RuntimeException('A public settings property is locked; no media keys were backfilled.');
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
}
