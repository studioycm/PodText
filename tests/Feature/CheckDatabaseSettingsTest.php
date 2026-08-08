<?php

use App\Console\Commands\CheckDatabaseSettings;

it('warns and exits cleanly on the sqlite suite driver', function (): void {
    $this->artisan('db:check-settings')
        ->expectsOutputToContain('Not a MySQL connection')
        ->assertSuccessful();
});

it('flags timestamp columns as alignment drift', function (): void {
    expect(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77]))
        ->toHaveCount(1)
        ->and(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77])[0])
        ->toContain('3 column(s) are still TIMESTAMP');
    expect(CheckDatabaseSettings::columnTypeProblems(['datetime' => 80]))->toBeEmpty();
});
