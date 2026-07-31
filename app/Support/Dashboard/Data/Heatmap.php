<?php

namespace App\Support\Dashboard\Data;

use Illuminate\Support\Carbon;

/**
 * Daily counts laid out as a calendar, keyed by Jerusalem `Y-m-d`.
 *
 * Replaces filawidgets' `HeatmapCalendarWidgetData`. Kept: entries keyed by day
 * and an optional per-day URL. Added: `cells()`, which returns the day, count,
 * day-first label and shading level together, so the Blade view stops computing
 * the peak and the level itself.
 */
readonly class Heatmap
{
    private const LEVELS = 4;

    /**
     * @param  array<string, int>  $entries
     * @param  array<string, string>  $urls
     */
    public function __construct(
        public array $entries,
        public ?string $description = null,
        public array $urls = [],
    ) {}

    public function total(): int
    {
        return array_sum($this->entries);
    }

    /**
     * @return array<int, array{day: string, count: int, label: string, level: int, url: ?string}>
     */
    public function cells(): array
    {
        $peak = max(1, ...array_values($this->entries ?: [0]));

        return collect($this->entries)
            ->map(fn (int $count, string $day): array => [
                'day' => $day,
                'count' => $count,
                'label' => Carbon::parse($day)->format('d/m/Y'),
                'level' => $count === 0 ? 0 : (int) max(1, ceil(($count / $peak) * self::LEVELS)),
                'url' => $this->urls[$day] ?? null,
            ])
            ->values()
            ->all();
    }

    /** @return array{entries: array<string, int>, description: ?string, urls: array<string, string>} */
    public function toArray(): array
    {
        return [
            'entries' => $this->entries,
            'description' => $this->description,
            'urls' => $this->urls,
        ];
    }
}
