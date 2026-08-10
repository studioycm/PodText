<?php

use Illuminate\Support\Facades\DB;

/**
 * Task 19's family-by-family fixes re-pinned every "only supports MySQL"
 * refusal test to the next real, live-testable guard, because the lane
 * itself is mysql now — but that left the actual driver-check GUARD CLAUSE
 * in each command untested. Nothing would catch a broken/removed check.
 *
 * Same pattern as AuthzPackageFoundationTest's isolated-SQLite test: swap
 * `database.default` to a throwaway :memory: connection INSIDE the test
 * body, restored in `finally`. This never touches the lane connection or
 * TestLaneContract::assertSafeBoot() (called once from
 * TestCase::refreshApplication() at boot), which only runs once at boot,
 * before the test body executes — it just gives DB::connection() (no args)
 * a non-mysql driver to report for the length of one command call.
 */
it('refuses a non-mysql default connection', function (string $command, array $arguments): void {
    $isolatedConnection = 'non_mysql_refusal_probe';
    $originalConnection = DB::getDefaultConnection();

    config([
        "database.connections.{$isolatedConnection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => $isolatedConnection,
    ]);

    DB::purge($isolatedConnection);
    DB::setDefaultConnection($isolatedConnection);

    try {
        $this->artisan($command, $arguments)
            ->expectsOutputToContain('only supports MySQL')
            ->assertFailed();
    } finally {
        DB::disconnect($isolatedConnection);
        DB::setDefaultConnection($originalConnection);
        config(['database.default' => $originalConnection]);
    }
})->with([
    'db:snapshot' => ['db:snapshot', []],
    'db:restore' => ['db:restore', ['file' => 'anything.sql.gz']],
    'db:alignment-oracle capture' => ['db:alignment-oracle', ['mode' => 'capture']],
    'db:preflight-alignment' => ['db:preflight-alignment', []],
    'db:seed-rehearsal-edges' => ['db:seed-rehearsal-edges', []],
]);
