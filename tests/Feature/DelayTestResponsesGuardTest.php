<?php

declare(strict_types=1);

use App\Http\Middleware\DelayTestResponses;
use Illuminate\Contracts\Http\Kernel;

/*
 * The delay middleware is a reproduction tool for browser-test races, and it
 * sits in the GLOBAL middleware stack — so the property that matters is not
 * that it can sleep, it is that it cannot sleep by accident. Everything below
 * pins the refusal side.
 *
 * Nothing here times anything. A test asserting "this slept for roughly N ms"
 * fails on a busy machine, which is the precise defect family the middleware
 * exists to hunt; asserting the decision instead keeps the guard honest under
 * the load it was built for.
 */

it('stays inert when no delay is configured', function (mixed $configured): void {
    expect(DelayTestResponses::delayMilliseconds($configured, true))->toBe(0);
})->with([
    'unset' => [null],
    'empty string' => [''],
    'non-numeric' => ['soon'],
    'zero' => [0],
    'negative' => [-500],
]);

it('refuses to delay outside the testing environment, whatever is configured', function (): void {
    // The one that would matter in production: a delay middleware reachable
    // from a live request is a denial of service with a friendly name.
    expect(DelayTestResponses::delayMilliseconds(5000, false))->toBe(0)
        ->and(DelayTestResponses::delayMilliseconds('5000', false))->toBe(0);
});

it('delays by the configured milliseconds while testing', function (): void {
    expect(DelayTestResponses::delayMilliseconds(250, true))->toBe(250)
        ->and(DelayTestResponses::delayMilliseconds('250', true))->toBe(250);
});

it('is registered in the global middleware stack', function (): void {
    // Registration is the half a unit test cannot see: the decision above could
    // be perfect while the middleware never runs. Asserted against the kernel
    // rather than by reading bootstrap/app.php, so moving the registration is
    // fine and removing it is not.
    // No toBeInstanceOf() on the kernel: PHPStan already narrows app(Kernel::class)
    // to the concrete Foundation kernel, so that assertion cannot fail and adds
    // nothing (`pest.expectation.redundant`). Caught by the D2 sweep against
    // this very file, an hour after writing it — which is the argument for
    // running that sweep rather than trusting a fresh test.
    $kernel = app(Kernel::class);
    $middleware = (new ReflectionClass($kernel))->getProperty('middleware')->getValue($kernel);

    expect($middleware)->toBeArray()
        ->toContain(DelayTestResponses::class);
});
