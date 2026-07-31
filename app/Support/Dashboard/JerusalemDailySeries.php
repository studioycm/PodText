<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use LaravelDaily\FilaWidgets\Support\SparklineSeries;

/**
 * The Jerusalem-walls replacement for filawidgets'
 * {@see SparklineSeries::daily()}.
 *
 * The package helper groups with a raw SQL `DATE(column)`, which buckets on the
 * database timezone: wrong for a board whose editorial day is a Jerusalem day,
 * wrong across daylight-saving shifts, and different between production MySQL
 * and the SQLite test database. Bucketing therefore happens in PHP here, while
 * the output shape stays exactly what the package's DTOs expect — this is the
 * one piece of the package's data layer we deliberately do not use.
 */
class JerusalemDailySeries
{
    public const TIMEZONE = 'Asia/Jerusalem';

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
                $day = Carbon::parse($value)->timezone(self::TIMEZONE)->format('Y-m-d');

                if (array_key_exists($day, $buckets)) {
                    $buckets[$day]++;
                }
            });

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
