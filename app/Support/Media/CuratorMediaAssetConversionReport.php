<?php

namespace App\Support\Media;

class CuratorMediaAssetConversionReport
{
    /**
     * @param  array<string, int>  $counts
     * @param  array<string, array<int, mixed>>  $details
     */
    public function __construct(
        private array $counts,
        private array $details,
    ) {}

    public function count(string $name): int
    {
        return $this->counts[$name] ?? 0;
    }

    /**
     * @return array<int, mixed>
     */
    public function details(string $name): array
    {
        return $this->details[$name] ?? [];
    }

    /**
     * @return array{counts: array<string, int>, details: array<string, array<int, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'counts' => $this->counts,
            'details' => $this->details,
        ];
    }
}
