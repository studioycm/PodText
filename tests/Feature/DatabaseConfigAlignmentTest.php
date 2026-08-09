<?php

it('hardcodes the aligned charset and collation with no env indirection', function (): void {
    foreach (['mysql', 'mariadb'] as $connection) {
        expect(config("database.connections.{$connection}.charset"))->toBe('utf8mb4');
        expect(config("database.connections.{$connection}.collation"))->toBe('utf8mb4_0900_ai_ci');
    }

    $source = file_get_contents(config_path('database.php'));
    expect($source)->not->toContain('DB_CHARSET')->not->toContain('DB_COLLATION');
});

it('pins the mysql session clock to UTC', function (): void {
    expect(config('database.connections.mysql.timezone'))->toBe('+00:00');
});

it('defines the lane connection with no url key and no shared env', function (): void {
    $lane = config('database.connections.mysql_testing');

    expect($lane['driver'])->toBe('mysql');
    expect($lane)->not->toHaveKey('url');       // a DSN silently overrides host+database
    expect($lane['charset'])->toBe('utf8mb4');
    expect($lane['collation'])->toBe('utf8mb4_0900_ai_ci');
    expect($lane['timezone'])->toBe('+00:00');

    $source = file_get_contents(config_path('database.php'));
    // The lane must never read the app's database name.
    // sqlite + mysql + the three dead vendor blocks (mariadb/pgsql/sqlsrv).
    // The lane must not make it 6 — it reads DB_TESTING_* keys only.
    expect(substr_count($source, "env('DB_DATABASE'"))->toBe(5);
});
