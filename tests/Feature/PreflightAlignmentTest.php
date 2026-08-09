<?php

use App\Console\Commands\PreflightAlignment;

it('runs clean against the aligned mysql lane', function (): void {
    // Read-only (the class docblock: "safe anywhere, including
    // production") — the lane's schema is aligned by the time any test
    // runs, so the real B3/B5 scans have nothing to find.
    $this->artisan('db:preflight-alignment')
        ->expectsOutputToContain('Pre-flight clean')
        ->assertSuccessful();
});

it('builds a duplicate scan that collates only collated columns and excludes NULL rows', function (): void {
    $sql = PreflightAlignment::duplicateScanSql('roles', ['name', 'guard_name', 'team_id'], ['name', 'guard_name']);

    expect($sql)
        ->toContain('`name` COLLATE utf8mb4_0900_ai_ci')
        ->toContain('`guard_name` COLLATE utf8mb4_0900_ai_ci')
        ->not->toContain('`team_id` COLLATE')
        ->toContain('`name` IS NOT NULL AND `guard_name` IS NOT NULL AND `team_id` IS NOT NULL')
        ->toContain('HAVING COUNT(*) > 1');
});
