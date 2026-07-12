<?php

namespace App\Support\Settings;

use Illuminate\Support\Str;

class SettingsItemCloner
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<int|string, array<string, mixed>>  $collection
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function clone(
        array $item,
        array $collection,
        string $keyName = 'key',
        string $nameName = 'name',
        string $copySuffix = 'Copy',
        array $overrides = [],
    ): array {
        $clone = $item;
        $existingKeys = collect($collection)
            ->filter(fn (mixed $collectionItem): bool => is_array($collectionItem))
            ->pluck($keyName)
            ->filter()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();

        $baseKey = $this->baseKey((string) ($item[$keyName] ?? $item[$nameName] ?? 'item'));
        $clone[$keyName] = $this->uniqueKey($baseKey, $existingKeys);

        if (filled($clone[$nameName] ?? null)) {
            $clone[$nameName] = trim((string) $clone[$nameName].' '.$copySuffix);
        }

        foreach ($overrides as $key => $value) {
            $clone[$key] = $value;
        }

        return $clone;
    }

    /**
     * @param  array<int, string>  $existingKeys
     */
    private function uniqueKey(string $baseKey, array $existingKeys): string
    {
        $baseKey = preg_replace('/_\d+$/', '', $baseKey) ?: 'item';
        $candidate = $baseKey;
        $suffix = 2;

        while (in_array($candidate, $existingKeys, true)) {
            $candidate = "{$baseKey}_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function baseKey(string $value): string
    {
        $key = Str::of($value)
            ->ascii()
            ->slug('_')
            ->lower()
            ->toString();

        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: 'item';
        $key = trim($key, '_');

        if ($key === '' || ! preg_match('/^[a-z]/', $key)) {
            return 'item';
        }

        return $key;
    }
}
