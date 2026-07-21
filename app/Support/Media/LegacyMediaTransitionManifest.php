<?php

namespace App\Support\Media;

use Illuminate\Support\Arr;

readonly class LegacyMediaTransitionManifest
{
    /** @param array<string, mixed> $schema @param array<int, array<string, mixed>> $entries */
    public function __construct(public array $schema, public array $entries, public string $createdAt) {}

    /** @return array<string, mixed> */
    public function canonical(): array
    {
        $entries = collect($this->entries)
            ->map(fn (array $entry): array => Arr::sortRecursive($entry))
            ->sortBy(fn (array $entry): string => sprintf('%010d:%s', (int) ($entry['media_id'] ?? 0), (string) ($entry['path'] ?? '')))
            ->values()
            ->all();

        return [
            'schema' => Arr::sortRecursive($this->schema),
            'entries' => $entries,
        ];
    }

    public function digest(): string
    {
        return hash('sha256', json_encode($this->canonical(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge($this->canonical(), [
            'digest' => $this->digest(),
            'created_at' => $this->createdAt,
        ]);
    }
}
