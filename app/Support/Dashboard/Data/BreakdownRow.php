<?php

namespace App\Support\Dashboard\Data;

use App\Enums\SparklineTrend;

/**
 * One labelled row of a breakdown, with an optional whole it is part of.
 *
 * Replaces filawidgets' `BreakdownItemData`, whose only comparison field was
 * `previousValue`. Adopting it forced two mistranslations: podcast health had
 * to mean "total published" by `previousValue`, and the transcriber board had
 * to be wrapped in an array because there was nowhere to put word counts.
 *
 * Here `of` is the whole (so `percent()` is honest), `previous` stays available
 * for genuine period comparisons, and `meta` carries a second measure without a
 * wrapper. A row uses `of` or `previous` — never one pretending to be the other.
 */
readonly class BreakdownRow
{
    /** @param array<string, int|float|string> $meta */
    public function __construct(
        public string $label,
        public float $value,
        public ?float $of = null,
        public ?float $previous = null,
        public ?string $color = null,
        public ?string $url = null,
        public array $meta = [],
    ) {}

    public function percent(): int
    {
        return ($this->of ?? 0.0) > 0
            ? (int) round(($this->value / $this->of) * 100)
            : 0;
    }

    /** The part of the whole this row does not cover. */
    public function remainder(): int
    {
        return (int) max(0, ($this->of ?? 0.0) - $this->value);
    }

    public function delta(): ?int
    {
        return $this->previous === null
            ? null
            : (int) round($this->value - $this->previous);
    }

    /** Null when there is no previous period: no comparison, not a neutral one. */
    public function trend(): ?SparklineTrend
    {
        $delta = $this->delta();

        return $delta === null ? null : SparklineTrend::fromDelta($delta);
    }

    public function meta(string $key, int|float|string|null $default = null): int|float|string|null
    {
        return $this->meta[$key] ?? $default;
    }

    /** @return array{label: string, value: float, of: ?float, previous: ?float, color: ?string, url: ?string, meta: array<string, int|float|string>} */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'of' => $this->of,
            'previous' => $this->previous,
            'color' => $this->color,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }
}
