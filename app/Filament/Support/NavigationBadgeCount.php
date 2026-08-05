<?php

namespace App\Filament\Support;

use App\Support\UiFormats;
use Illuminate\Support\Facades\Cache;

/**
 * The policy behind sidebar count badges: key naming, freshness, and how a
 * number is rendered.
 *
 * Filament 5.7 has no deferred-badge API for navigation items — unlike tabs
 * and relation managers, which both expose `deferBadge()` — so the sidebar
 * cannot load its counts after paint. The substitute is the one NAV1
 * established: the count is evaluated only when the badge is asked for, and
 * the answer is cached briefly so it does not repeat on every request.
 *
 * The `Cache::remember()` call itself stays at each call site rather than
 * hiding in here, so a reader (and FilaCheck's navigation-badge rule) can
 * see that the badge is cached without following an indirection.
 */
final class NavigationBadgeCount
{
    public static function cacheKey(string $name): string
    {
        return "admin.navigation-badge.{$name}";
    }

    /**
     * Fresh for a minute, then served stale for up to ten while Laravel
     * recomputes after the response. `remember()` would make the first
     * visitor past expiry pay the query; with `flexible()` only the very
     * first request ever waits, which is the closest a navigation badge can
     * get to the deferral tabs have.
     *
     * @return array{0: int, 1: int}
     */
    public static function ttl(): array
    {
        return [60, 600];
    }

    /** A badge reading "0" is noise, not news. */
    public static function format(int $count): ?string
    {
        return $count > 0 ? UiFormats::number($count) : null;
    }

    public static function forget(string $name): void
    {
        Cache::forget(self::cacheKey($name));
    }
}
