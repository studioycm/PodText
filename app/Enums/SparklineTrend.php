<?php

namespace App\Enums;

use App\Support\Dashboard\Data\BreakdownRow;
use App\Support\Dashboard\Data\SeriesRow;

/**
 * Which way a period's movement went, and the one home of its colours.
 *
 * Before this enum the up/down/neutral palette was hand-written wherever a
 * delta rendered — the funnel's movement line and the transcriber board each
 * carried their own copy — and the sparkline stroke had no trend colour at
 * all. A view derives the case through {@see SeriesRow::trend()}
 * or {@see BreakdownRow::trend()} and reads the
 * classes here; DashboardSparklineTest fails any widget view that re-writes
 * the palette by hand.
 */
enum SparklineTrend: string
{
    case Up = 'up';
    case Down = 'down';
    case Neutral = 'neutral';

    public static function fromDelta(int $delta): self
    {
        return match (true) {
            $delta > 0 => self::Up,
            $delta < 0 => self::Down,
            default => self::Neutral,
        };
    }

    /** Tailwind stroke for a sparkline drawn in this trend's colour. */
    public function strokeClass(): string
    {
        return match ($this) {
            self::Up => 'stroke-success-500 dark:stroke-success-400',
            self::Down => 'stroke-danger-500 dark:stroke-danger-400',
            self::Neutral => 'stroke-gray-400 dark:stroke-gray-500',
        };
    }

    /** Tailwind text colour for a delta figure in this trend's colour. */
    public function textClass(): string
    {
        return match ($this) {
            self::Up => 'text-success-600 dark:text-success-400',
            self::Down => 'text-danger-600 dark:text-danger-400',
            self::Neutral => 'text-gray-400 dark:text-gray-500',
        };
    }
}
