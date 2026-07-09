<?php

namespace App\Support\PublicFront;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class PublicFrontConfigCache
{
    public const CONFIG_KEY = 'public_front.config.v1';

    public const PODCAST_PALETTE_KEY = 'public_front.podcast_palette.v1';

    private const PAYLOAD_VERSION = 1;

    public function enabled(): bool
    {
        return (bool) config('settings.cache.enabled', false);
    }

    public function remember(callable $resolver): PublicFrontConfigResult
    {
        if (! $this->enabled()) {
            return $resolver();
        }

        $key = $this->key();
        $payload = Cache::get($key);
        $cachedResult = $this->resultFromPayload($payload);

        if ($cachedResult instanceof PublicFrontConfigResult) {
            return $cachedResult;
        }

        if ($payload !== null) {
            Cache::forget($key);
        }

        $result = $resolver();

        Cache::forever($key, $this->payloadFromResult($result));

        return $result;
    }

    public function forget(): void
    {
        Cache::forget($this->key());
    }

    public function key(): string
    {
        return self::CONFIG_KEY.'.'.$this->settingsMigrationWatermark();
    }

    public function podcastPaletteKey(string $coverPath, int $mtime): string
    {
        return self::PODCAST_PALETTE_KEY.'.'.sha1($coverPath.'|'.$mtime);
    }

    public function settingsMigrationWatermark(): string
    {
        $migrationNames = collect((array) config('settings.migrations_paths', [database_path('settings')]))
            ->flatMap(fn (string $path): array => File::glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php') ?: [])
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->unique()
            ->sort()
            ->values();

        return $migrationNames->count().'.'.($migrationNames->last() ?? 'none');
    }

    /**
     * @return array{version: int, key: string, watermark: string, config: array<string, mixed>, invalid_config: array<array{path: string, reason: string, value_preview: string}>, cached_at: string}
     */
    private function payloadFromResult(PublicFrontConfigResult $result): array
    {
        return [
            'version' => self::PAYLOAD_VERSION,
            'key' => self::CONFIG_KEY,
            'watermark' => $this->settingsMigrationWatermark(),
            'config' => $result->config(),
            'invalid_config' => $result->invalidConfigArray(),
            'cached_at' => now()->toIso8601String(),
        ];
    }

    private function resultFromPayload(mixed $payload): ?PublicFrontConfigResult
    {
        if (! is_array($payload)) {
            return null;
        }

        if (($payload['version'] ?? null) !== self::PAYLOAD_VERSION) {
            return null;
        }

        if (($payload['key'] ?? null) !== self::CONFIG_KEY) {
            return null;
        }

        if (! is_array($payload['config'] ?? null)) {
            return null;
        }

        $invalidConfig = $this->invalidConfigFromPayload($payload['invalid_config'] ?? null);

        if ($invalidConfig === null) {
            return null;
        }

        return new PublicFrontConfigResult($payload['config'], $invalidConfig);
    }

    /**
     * @return array<PublicFrontInvalidConfig>|null
     */
    private function invalidConfigFromPayload(mixed $payload): ?array
    {
        if (! is_array($payload) || ! array_is_list($payload)) {
            return null;
        }

        $invalidConfig = [];

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                return null;
            }

            if (! is_string($entry['path'] ?? null) || ! is_string($entry['reason'] ?? null)) {
                return null;
            }

            $invalidConfig[] = new PublicFrontInvalidConfig(
                path: $entry['path'],
                reason: $entry['reason'],
                valuePreview: is_string($entry['value_preview'] ?? null) ? $entry['value_preview'] : '',
            );
        }

        return $invalidConfig;
    }
}
