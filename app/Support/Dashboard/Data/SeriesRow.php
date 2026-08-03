<?php

namespace App\Support\Dashboard\Data;

use App\Enums\SparklineTrend;

/**
 * One flow measure over the selected range: how much moved, how that compares
 * with the period before it, and the daily shape.
 *
 * Replaces filawidgets' `SparklineTableRowData`. Kept from it: a period value
 * with a first-class previous-period companion — the idea that made every
 * funnel stage able to show a delta. Added here: `delta()` and `points()` so no
 * Blade view recomputes them, and an explicit statement that `value` is
 * movement, never stock.
 */
readonly class SeriesRow
{
    /**
     * @param  array<int, float>  $points  one entry per Jerusalem day, oldest first
     * @param  array<int, string>  $days  the `Y-m-d` Jerusalem day of each point, aligned with $points
     */
    public function __construct(
        public string $key,
        public string $label,
        public float $value,
        public float $previous = 0.0,
        public array $points = [],
        public array $days = [],
    ) {}

    public function delta(): int
    {
        return (int) round($this->value - $this->previous);
    }

    public function trend(): SparklineTrend
    {
        return SparklineTrend::fromDelta($this->delta());
    }

    /** @return array{key: string, label: string, value: float, previous: float, points: array<int, float>, days: array<int, string>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'previous' => $this->previous,
            'points' => $this->points,
            'days' => $this->days,
        ];
    }
}
