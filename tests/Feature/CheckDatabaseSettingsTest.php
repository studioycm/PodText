<?php

use App\Console\Commands\CheckDatabaseSettings;

it('reports no drift against the aligned mysql lane', function (): void {
    // Read-only ("Safe to run anywhere, including production" per the class
    // docblock) — the lane's schema is aligned by the time any test runs, so
    // the real charset/collation/clock checks all come back clean.
    $this->artisan('db:check-settings')
        ->expectsOutputToContain('No drift found.')
        ->assertSuccessful();
});

it('flags timestamp columns as alignment drift', function (): void {
    expect(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77]))
        ->toHaveCount(1)
        ->and(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77])[0])
        ->toContain('3 column(s) are still TIMESTAMP');
    expect(CheckDatabaseSettings::columnTypeProblems(['datetime' => 80]))->toBeEmpty();
});
