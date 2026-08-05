<?php

namespace App\Support;

use App\Enums\NavigationBadge;
use Illuminate\Support\Facades\Cache;

/**
 * The policy behind every sidebar count badge: key naming, freshness, and how
 * a number is rendered. One home for all three (NavigationBadge::cases()).
 *
 * Filament 5.7 has no deferred-badge API for navigation items — unlike tabs
 * and relation managers, which both expose `deferBadge()` — so the sidebar
 * cannot load its counts after paint. The substitute is the one NAV1
 * established: the count is evaluated only when the badge is asked for, and
 * the answer is cached so it does not repeat on every request.
 *
 * Two rules this class cannot enforce for its callers, both load-bearing:
 *
 * 1. The `Cache::` call stays lexically inside each `getNavigationBadge()`.
 *    FilaCheck Pro's `navigation-badge-not-cached` rule is a syntactic AST
 *    walk of that method body (NavigationBadgeNotCachedRule::usesCache()); it
 *    accepts no indirection, so moving the call in here would fail the gate
 *    at all three sites. Pinned by `it('keeps every sidebar badge's cache
 *    call where FilaCheck can see it')`.
 *
 * 2. Callers cache the *integer* and format it on the way out. A cached
 *    `null` is indistinguishable from a miss (Repository::flexible()'s
 *    `in_array(null, [$value, $created], true)`), so caching the formatted
 *    string means an empty badge re-runs its COUNT on every single render —
 *    exactly the steady state a work queue sits in. Pinned by
 *    `it('caches a zero count instead of re-counting an empty badge')`.
 */
final class NavigationBadgeCount
{
    public static function cacheKey(NavigationBadge $badge): string
    {
        return "admin.navigation-badge.{$badge->value}";
    }

    /**
     * Fresh for a minute, then served stale for up to ten while Laravel
     * recomputes after the response. `remember()` would make the first
     * visitor past expiry pay the query; with `flexible()` only the very
     * first request ever waits, which is the closest a navigation badge can
     * get to the deferral tabs have.
     *
     * The wide stale window is safe for the one badge that must be exact,
     * because every write forgets the key and a forgotten value recomputes
     * synchronously on the next read.
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

    /**
     * Void on purpose — see the callers in PublicFormSubmission::booted().
     * A model-event listener returning exactly false halts the dispatcher's
     * listener loop, and `Cache::forget()` returns false for an absent key.
     */
    public static function forget(NavigationBadge $badge): void
    {
        Cache::forget(self::cacheKey($badge));
    }
}
