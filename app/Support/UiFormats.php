<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * The one shape dates and numbers take when humans read them.
 *
 * The sibling of {@see UiTimezone}: that class answers which walls a
 * timestamp is shown on, this one answers what the value looks like once
 * there — day-first dates and locale-grouped numbers. The values live in
 * `config/localization.php`; this class exists so call sites share one typed
 * reader instead of one literal each. `number()` goes through
 * `Illuminate\Support\Number`, which respects the configured locale where
 * `number_format()` silently ignored it. (For non-negative integers — every
 * dashboard count — the Hebrew output matches `number_format()` exactly;
 * negatives additionally gain an LTR mark so the minus sign renders sanely
 * in RTL copy.)
 */
final class UiFormats
{
    public static function date(): string
    {
        return (string) config('localization.date_format');
    }

    public static function dateTime(): string
    {
        return (string) config('localization.date_time_format');
    }

    public static function time(): string
    {
        return (string) config('localization.time_format');
    }

    public static function number(int|float $value): string
    {
        return (string) Number::format($value, locale: (string) config('localization.number_locale'));
    }
}
