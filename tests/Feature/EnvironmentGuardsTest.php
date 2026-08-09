<?php

use App\Support\UiTimezone;
use Tests\TestCase;

/**
 * The environment invariants, each as its own named failure.
 *
 * WHAT THIS IS NOT: a circuit breaker. Pest gives no ordering guarantee across
 * files, so this cannot run "first" and halt the suite. `TestCase` already
 * throws on the database guard before any test body runs, and that is the real
 * stop.
 *
 * WHAT IT IS: a diagnosis. When the database guard trips, every one of ~1800
 * tests fails with the same RuntimeException and the actual cause is buried.
 * One named failing test — "the suite is pointed at a database it may not
 * destroy" — is the line someone reads first. It is also the only home for
 * invariants nothing else enforces, like where enums live.
 *
 * Feature, not Unit: tests/Pest.php binds Tests\\TestCase to Feature and Browser
 * only, so a Unit file never boots the app and `config()` is unresolvable. That
 * gap is itself recorded as residual risk in mysql-test-lane-spec.md — the
 * database guard cannot reach tests/Unit either.
 */
it('runs against the dedicated mysql test lane it cannot destroy', function (): void {
    // The suite runs migrate:fresh, which drops every table it finds. The real
    // local database is MySQL `podtext` on the app connection, one daemon away
    // from the lane. If the default connection ever resolves to anything but
    // the dedicated lane, the next `RefreshDatabase` is destructive — see
    // mysql-test-lane-spec.md.
    expect(config('database.default'))->toBe('mysql_testing', 'The suite is pointed at a database it may not destroy.')
        ->and(config('database.connections.mysql.database'))->toBe('unreachable_from_tests')
        ->and(config('database.connections.mariadb.database'))->toBe('unreachable_from_tests')
        ->and(app()->environment())->toBe('testing');
});

it('refuses to run when the booted lane connection is tampered with', function (): void {
    // Not a re-run of TestLaneGuardTest's stubbed clause table — this feeds
    // the REAL booted connections (host, port, username, database as they
    // exist right now) through the same pure refusalFor, mutating one
    // connection-level field (the lane host) to prove the booted guard would
    // refuse a tampered connection. Mutating `default` instead would
    // short-circuit on the very first clause before ever touching the booted
    // connection array, which would not actually prove anything about the
    // booted state.
    $config = config('database');
    $config['connections']['mysql_testing']['host'] = '10.0.0.9';

    expect(TestCase::refusalFor($config, []))->not->toBeNull();
});

it('keeps storage in UTC and Jerusalem at the presentation layer only', function (): void {
    // Israel observes DST, so a local wall clock is not a unique name for an
    // instant: two moments an hour apart on the autumn transition render as the
    // same string, and the spring gap has times that do not exist. Storing
    // local time destroys ordering an hour a year, silently and unrecoverably.
    expect(config('app.timezone'))->toBe('UTC', 'Storage timezone left UTC — Israel DST makes local wall clocks ambiguous.')
        ->and(UiTimezone::name())->toBe('Asia/Jerusalem');
});

it('declares every enum under app/Enums', function (): void {
    // Two enums used to live in their subsystems, and every tool that globs
    // app/Enums/*.php silently missed them — which is how every enum count in
    // the playbook came out wrong. Counted by declaration, never by directory.
    $all = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        if (preg_match('/^\s*enum\s+\w+/m', (string) file_get_contents($file->getPathname())) === 1) {
            $all[] = str_replace(app_path().'/', '', $file->getPathname());
        }
    }

    $strays = collect($all)->reject(fn (string $path): bool => str_starts_with($path, 'Enums/'));

    expect($strays->all())->toBe([], "These enums are declared outside app/Enums, where directory-globbing tools cannot see them:\n  ".$strays->implode("\n  "))
        ->and(count($all))->toBeGreaterThanOrEqual(45, 'The enum sweep found fewer files than exist — it stopped seeing code.');
});
