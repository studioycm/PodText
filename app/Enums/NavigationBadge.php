<?php

namespace App\Enums;

/**
 * The admin sidebar count badges (NAV1). Each case names one badge; the key,
 * the freshness window, the formatting and the invalidation entry point all
 * live in NavigationBadgeCount — the one home.
 *
 * Freshness is deliberately not uniform. Episodes and media are inventory
 * counters, allowed to lag their TTL because nothing waits on them. Form
 * submissions is a work queue, so its model forgets the key on every write
 * and a handled submission leaves the badge at once.
 */
enum NavigationBadge: string
{
    case Episodes = 'episodes';
    case Media = 'media';
    case FormSubmissions = 'form-submissions';
}
