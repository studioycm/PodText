<?php

use App\Console\Commands\PreflightAlignment;

it('refuses the sqlite suite driver', function (): void {
    $this->artisan('db:preflight-alignment')
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
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
