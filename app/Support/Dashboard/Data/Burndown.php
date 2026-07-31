<?php

namespace App\Support\Dashboard\Data;

use App\Enums\DashboardTier;
use Illuminate\Support\Carbon;

/**
 * A queue's finish line: how much of a population is still outstanding.
 *
 * Replaces filawidgets' `ProgressWidgetData`, which spoke `currentValue` /
 * `goalValue` — a vocabulary that inverted this domain, where the number that
 * matters is what *remains*. This speaks remaining-of-total directly, computes
 * `cleared()` and `percent()`, and carries the forecast as a real `Carbon` so
 * the view decides the format rather than the service pre-formatting a string.
 *
 * A forecast is optional by design: only work whose completions carry a
 * timestamp can be paced honestly.
 */
readonly class Burndown
{
    public function __construct(
        public DashboardTier $tier,
        public int $remaining,
        public int $total,
        public string $description,
        public ?Carbon $forecast = null,
    ) {}

    public function cleared(): int
    {
        return max(0, $this->total - $this->remaining);
    }

    public function percent(): int
    {
        return $this->total > 0
            ? (int) round(($this->cleared() / $this->total) * 100)
            : 100;
    }

    public function isClear(): bool
    {
        return $this->remaining < 1;
    }

    /** @return array{tier: string, remaining: int, total: int, description: string, forecast: ?string} */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier->value,
            'remaining' => $this->remaining,
            'total' => $this->total,
            'description' => $this->description,
            'forecast' => $this->forecast?->toIso8601String(),
        ];
    }
}
