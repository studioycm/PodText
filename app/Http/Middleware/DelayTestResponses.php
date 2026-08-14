<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tunable SERVER-SIDE-ONLY delay, for reproducing browser-test races.
 *
 * Why this exists rather than CPU contention. Contention slows the browser and
 * PHP together, so their *relative* timing is roughly preserved, and it
 * reproduces races about how fast a state settles — measured 2026-08-14, it
 * reproduced three of R4 row 8's four canaries with byte-identical historical
 * messages. What it does not reproduce is `Execution context was destroyed,
 * most likely because of a navigation`, because that needs the asymmetry: the
 * browser running at full speed while a navigation or Livewire swap lands late,
 * tearing down the context an in-flight `evaluate()` was holding. Historically
 * that string arrived at
 * `CardTemplatePreviewBrowserTest.php:2015` and `:2067` — both
 * `refresh()`-then-`evaluate()` boundaries — recorded in
 * `~/.cache/podtext-coord/upstream-pest/tia-filtered-2026-08-12/logs/`.
 *
 * INERT BY DEFAULT, and that is the whole safety story: with
 * `PODTEXT_TEST_REQUEST_DELAY_MS` unset or non-positive the middleware returns
 * immediately, so an ordinary gate run is untouched. It additionally refuses to
 * sleep outside the testing environment, because a delay middleware that can
 * reach production is a denial of service with a friendly name.
 *
 * Usage:
 *
 *     PODTEXT_TEST_REQUEST_DELAY_MS=250 php -d memory_limit=2G vendor/bin/pest …
 *
 * The delay is per request, so a page doing N Livewire round-trips pays N × the
 * value; start small (50-100ms) before reaching for large numbers.
 */
class DelayTestResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $milliseconds = self::delayMilliseconds(
            env('PODTEXT_TEST_REQUEST_DELAY_MS'),
            app()->environment('testing'),
        );

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }

        return $next($request);
    }

    /**
     * The effective delay, as a pure decision.
     *
     * Split out from handle() so the guard can be tested without timing
     * anything: a test that asserts "this slept for roughly N ms" is a test
     * that fails on a busy machine, which is the exact family of defect this
     * middleware exists to hunt.
     */
    public static function delayMilliseconds(mixed $configured, bool $isTesting): int
    {
        if (! $isTesting || ! is_numeric($configured)) {
            return 0;
        }

        return max(0, (int) $configured);
    }
}
