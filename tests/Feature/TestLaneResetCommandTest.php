<?php

use App\Console\Commands\ResetTestLane;

/*
 * The destructive happy path is structurally untestable in-suite: the suite
 * process itself holds the flock run-lock, so the command MUST refuse while
 * being tested — and that refusal is exactly what the first test pins. The
 * drop is covered by the pure statement generator plus one manual
 * end-to-end run recorded in the round report (spec F4).
 */

it("refuses while this tree's pest process holds the lane run-lock", function (): void {
    $this->artisan('db:test-lane-reset')
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
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $lane['host'], $lane['port'], $lane['database']),
        (string) $lane['username'],
        (string) $lane['password'],
    );

    try {
        expect(ResetTestLane::foreignLaneConnections((string) $lane['database']))->toBeGreaterThanOrEqual(1);
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
