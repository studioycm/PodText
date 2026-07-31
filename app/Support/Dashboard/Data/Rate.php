<?php

namespace App\Support\Dashboard\Data;

/**
 * A share of a whole, expressed as a percentage with the counts behind it.
 *
 * Replaces filawidgets' `CompletionRateWidgetData`, which carried only a value
 * plus min/max. Keeping `covered` and `of` means the widget can state the
 * percentage *and* the two numbers it came from — a percentage with no
 * denominator is exactly the kind of figure this dashboard exists to avoid.
 * `threshold()` returns the health band, replacing the package's per-widget
 * `getThresholds()` array.
 */
readonly class Rate
{
    public function __construct(
        public int $covered,
        public int $of,
        public ?string $description = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->of < 1;
    }

    public function percent(): float
    {
        return $this->isEmpty()
            ? 0.0
            : round(($this->covered / $this->of) * 100, 1);
    }

    public function threshold(): string
    {
        return match (true) {
            $this->isEmpty() => 'gray',
            $this->percent() >= 100.0 => 'success',
            $this->percent() >= 75.0 => 'warning',
            default => 'danger',
        };
    }

    /** @return array{covered: int, of: int, percent: float, description: ?string} */
    public function toArray(): array
    {
        return [
            'covered' => $this->covered,
            'of' => $this->of,
            'percent' => $this->percent(),
            'description' => $this->description,
        ];
    }
}
