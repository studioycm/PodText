<?php

use App\Console\Commands\ResetTestLane;

/*
 * The destructive happy path is structurally untestable in-suite: the suite
 * process itself holds the flock run-lock, so the command MUST refuse while
 * being tested — and that refusal is exactly what the first test pins. The
 * drop is covered by the pure statement generator plus one manual
 * end-to-end run recorded in the round report (spec F4).
 * The processlist and typed-confirmation branches are likewise unreachable
 * in-suite — the flock refusal always fires first — so Task 7's manual
 * end-to-end run is the compensating control for those layers too.
 */

it('refuses while the running pest process holds the lane run-lock', function (): void {
    $this->artisan('db:test-lane-reset')
        ->expectsOutputToContain('run-lock')
        ->assertExitCode(1);
});

it('refuses even with --force while the run-lock is held — force only skips the typed confirmation', function (): void {
    $this->artisan('db:test-lane-reset', ['--force' => true])
        ->expectsOutputToContain('run-lock')
        ->assertExitCode(1);
});

it('refuses a non-lane-shaped config before any probe or prompt', function (): void {
    config(['database.connections.mysql_testing.username' => 'root']);

    $this->artisan('db:test-lane-reset')
        ->expectsOutputToContain('root')
        ->assertExitCode(1);
});

it('sees a second live lane connection through the processlist probe', function (): void {
    $lane = config('database.connections.mysql_testing');
    $before = ResetTestLane::foreignLaneConnections((string) $lane['database']);
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $lane['host'], $lane['port'], $lane['database']),
        (string) $lane['username'],
        (string) $lane['password'],
    );

    try {
        expect(ResetTestLane::foreignLaneConnections((string) $lane['database']))->toBeGreaterThan($before);
    } finally {
        $pdo = null;
    }
});

it('generates schema-qualified drop statements', function (): void {
    expect(ResetTestLane::dropStatements('podtext_test', ['alpha', 'beta']))->toBe([
        'DROP TABLE IF EXISTS `podtext_test`.`alpha`',
        'DROP TABLE IF EXISTS `podtext_test`.`beta`',
    ]);
});

it('doubles backticks in table names when generating drop statements', function (): void {
    expect(ResetTestLane::dropStatements('podtext_test', ['alpha`b']))->toBe([
        'DROP TABLE IF EXISTS `podtext_test`.`alpha``b`',
    ]);
});
