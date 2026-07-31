<?php

namespace App\Support;

/**
 * The one timezone humans read and type in.
 *
 * Laravel stores timestamps in the application timezone and this class does not
 * change that. It answers the separate, presentational question the house rule
 * poses — which walls a date is shown and entered on — for admin tables and
 * forms, public pages, exports, and the daily buckets the editorial dashboard
 * counts on. The value itself lives in `config/localization.php`; this class
 * exists so the roughly fifty call sites share one typed reader instead of one
 * literal each.
 */
final class UiTimezone
{
    public static function name(): string
    {
        return (string) config('localization.ui_timezone');
    }
}
