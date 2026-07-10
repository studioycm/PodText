<?php

namespace App\Support\SettingsLifecycle;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use RuntimeException;

class SettingsImportLocks
{
    public function __construct(
        private readonly SettingsLifecycleSchema $schema,
    ) {}

    /**
     * @return array<int, string>
     */
    public function lockedPaths(): array
    {
        $settings = app(PublicContentSettings::class);
        $locks = is_array($settings->import_locks ?? null)
            ? $settings->import_locks
            : PublicFrontConfigRegistry::defaults()['import_locks'];

        return $this->normalize($locks['locked_paths'] ?? []);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function save(array $paths): array
    {
        $lockedPaths = $this->normalize($paths);
        $settings = app(PublicContentSettings::class);
        $settings->import_locks = [
            'locked_paths' => $lockedPaths,
        ];
        $settings->save();

        app()->forgetInstance(PublicContentSettings::class);

        return $lockedPaths;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function normalize(array $paths): array
    {
        $knownPaths = $this->schema->unitPaths();
        $lockedPaths = collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path))
            ->intersect($knownPaths)
            ->unique()
            ->values()
            ->all();

        sort($lockedPaths);

        return $lockedPaths;
    }

    /**
     * @return array<int, string>
     */
    public function frontTextLockPaths(): array
    {
        $payload = $this->schema->payloadForGroup();

        return collect($this->schema->overlaySemantics()['front_text'] ?? [])
            ->map(function (string $semanticPath) use ($payload): string {
                $unitPaths = $this->schema->unitPathsForSemanticPath($semanticPath, $payload);

                if (count($unitPaths) !== 1) {
                    throw new RuntimeException("Front-text path [{$semanticPath}] must map to exactly one lockable settings unit.");
                }

                return $unitPaths[0];
            })
            ->unique()
            ->values()
            ->all();
    }
}
