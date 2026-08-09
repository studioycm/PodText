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
