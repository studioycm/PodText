<?php

use Illuminate\Support\Facades\DB;

it('no-ops on the sqlite suite driver', function (): void {
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    $migration = require database_path('migrations/2026_08_09_000000_align_collation_and_datetime_columns.php');

    // Past the guard the first statement is ALTER DATABASE — which would
    // throw on sqlite, so completing up() IS the proof the guard held.
    $migration->up();
});

it('generates MODIFY definitions that preserve nullability and drop CURRENT_TIMESTAMP defaults', function (): void {
    $migration = require database_path('migrations/2026_08_09_000000_align_collation_and_datetime_columns.php');

    $rows = [
        (object) ['t' => 'jobs', 'c' => 'created_at', 'n' => 'YES', 'd' => null, 'e' => '', 'cm' => '', 'p' => 0],
        (object) ['t' => 'failed_jobs', 'c' => 'failed_at', 'n' => 'NO', 'd' => 'CURRENT_TIMESTAMP', 'e' => 'DEFAULT_GENERATED', 'cm' => '', 'p' => '0'],
        (object) ['t' => 'tokens', 'c' => 'expires_at', 'n' => 'NO', 'd' => null, 'e' => '', 'cm' => '', 'p' => 0],
    ];

    expect($migration->modifyClauses($rows))->toBe([
        'MODIFY `created_at` DATETIME NULL',
        'MODIFY `failed_at` DATETIME NOT NULL',
        'MODIFY `expires_at` DATETIME NOT NULL',
    ]);
});

it('refuses attributes it is not sanctioned to drop', function (array $row, string $needle): void {
    $migration = require database_path('migrations/2026_08_09_000000_align_collation_and_datetime_columns.php');

    expect(fn () => $migration->modifyClauses([(object) $row]))
        ->toThrow(RuntimeException::class, $needle);
})->with([
    'ON UPDATE clause' => [['t' => 'x', 'c' => 'updated_at', 'n' => 'NO', 'd' => 'CURRENT_TIMESTAMP', 'e' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP', 'cm' => '', 'p' => 0], 'extra=[DEFAULT_GENERATED on update CURRENT_TIMESTAMP]'],
    'literal default' => [['t' => 'x', 'c' => 'seen_at', 'n' => 'NO', 'd' => '2020-01-01 00:00:00', 'e' => '', 'cm' => '', 'p' => 0], 'default=[2020-01-01 00:00:00]'],
    'column comment' => [['t' => 'x', 'c' => 'made_at', 'n' => 'YES', 'd' => null, 'e' => '', 'cm' => 'legacy', 'p' => 0], 'comment=[legacy]'],
    'fractional precision' => [['t' => 'x', 'c' => 'ping_at', 'n' => 'YES', 'd' => null, 'e' => '', 'cm' => '', 'p' => 6], 'precision=[6]'],
]);
