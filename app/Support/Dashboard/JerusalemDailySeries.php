<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardRange;
use App\Support\UiTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Daily counts bucketed on Jerusalem walls.
 *
 * Written because every off-the-shelf helper we looked at groups with a raw SQL
 * `DATE(column)`, which buckets on the database timezone: wrong for a board
 * whose editorial day is a Jerusalem day, wrong across daylight-saving shifts,
 * and different between production MySQL and the SQLite test database.
 * Bucketing therefore happens in PHP, where the timezone is explicit.
 */
class JerusalemDailySeries
{
    /**
     * Zero-filled Jerusalem-day counts, keyed `Y-m-d` and aligned to
     * {@see DashboardRange::dayKeys()}.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    public static function map(Builder $query, string $column, DashboardRange $range): array
    {
        [$start, $end] = $range->currentPeriod();
        $buckets = array_fill_keys($range->dayKeys(), 0);

        (clone $query)
            ->whereBetween($column, [$start, $end])
            ->pluck($column)
            ->each(function ($value) use (&$buckets): void {
                $day = Carbon::parse($value)->timezone(UiTimezone::name())->format('Y-m-d');

                if (array_key_exists($day, $buckets)) {
                    $buckets[$day]++;
                }
            });

        return $buckets;
    }

    /**
     * The same bucketing for timestamps computed in PHP rather than read from a
     * column — used where the meaningful instant is derived, such as the day an
     * episode became visible.
     *
     * @param  iterable<int, \DateTimeInterface|string|null>  $timestamps
     * @return array<string, int>
     */
    public static function fromTimestamps(iterable $timestamps, DashboardRange $range): array
    {
        $buckets = array_fill_keys($range->dayKeys(), 0);

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }

            $day = Carbon::parse($timestamp)->timezone(UiTimezone::name())->format('Y-m-d');

            if (array_key_exists($day, $buckets)) {
                $buckets[$day]++;
            }
        }

        return $buckets;
    }

    /**
     * The ordered float list `SparklineTableRowData::$sparkline` expects.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, float>
     */
    public static function values(Builder $query, string $column, DashboardRange $range): array
    {
        return array_map(floatval(...), array_values(self::map($query, $column, $range)));
    }

    /**
     * The same count over an arbitrary window, for previous-period deltas.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function total(Builder $query, string $column, \DateTimeInterface $start, \DateTimeInterface $end): float
    {
        return (float) (clone $query)->whereBetween($column, [$start, $end])->count();
    }
}
