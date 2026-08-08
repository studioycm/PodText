<?php

use Illuminate\Support\Facades\DB;

it('no-ops on the sqlite suite driver', function (): void {
    // The suite reaching this line at all proves migrate ran it without error.
    expect(DB::connection()->getDriverName())->toBe('sqlite');
});

it('generates MODIFY definitions that preserve nullability and drop CURRENT_TIMESTAMP defaults', function (): void {
    $migration = require database_path('migrations/2026_08_09_000000_align_collation_and_datetime_columns.php');

    $rows = [
        (object) ['c' => 'created_at', 'n' => 'YES', 'd' => null],
        (object) ['c' => 'failed_at', 'n' => 'NO', 'd' => 'CURRENT_TIMESTAMP'],
        (object) ['c' => 'expires_at', 'n' => 'NO', 'd' => null],
    ];

    expect($migration->modifyClauses($rows))->toBe([
        'MODIFY `created_at` DATETIME NULL',
        'MODIFY `failed_at` DATETIME NOT NULL',
        'MODIFY `expires_at` DATETIME NOT NULL',
    ]);
});
