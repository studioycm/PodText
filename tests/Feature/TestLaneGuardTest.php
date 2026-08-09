<?php

use Tests\TestCase;

$valid = fn (): array => [
    'default' => 'mysql_testing',
    'connections' => ['mysql_testing' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
        'database' => 'podtext_test', 'username' => 'podtext_test', 'password' => 'x',
    ], 'mysql' => ['port' => '3306', 'database' => 'podtext', 'username' => 'podtext']],
];

it('accepts the canonical lane shape', function () use ($valid): void {
    expect(TestCase::refusalFor($valid(), ['podtext']))->toBeNull();
});

it('refuses every broken shape', function (callable $mutate, string $needle) use ($valid): void {
    $config = $valid();
    $mutate($config);
    expect((string) TestCase::refusalFor($config, ['podtext']))->toContain($needle);
})->with([
    'app connection as default' => [fn (array &$c) => $c['default'] = 'mysql', 'mysql_testing'],
    'DSN present' => [fn (array &$c) => $c['connections']['mysql_testing']['url'] = 'mysql://x', 'url'],
    'bad name' => [fn (array &$c) => $c['connections']['mysql_testing']['database'] = 'podtext', 'name'],
    'remote host' => [fn (array &$c) => $c['connections']['mysql_testing']['host'] = '10.0.0.9', 'host'],
    'app port' => [fn (array &$c) => $c['connections']['mysql_testing']['port'] = '3306', 'port'],
    'root user' => [fn (array &$c) => $c['connections']['mysql_testing']['username'] = 'root', 'root'],
    'app username' => [fn (array &$c) => $c['connections']['mysql_testing']['username'] = 'podtext', 'username'],
    'empty database' => [fn (array &$c) => $c['connections']['mysql_testing']['database'] = null, 'closed'],
    'localhost host' => [fn (array &$c) => $c['connections']['mysql_testing']['host'] = 'localhost', 'unix socket'],
    'unix_socket key' => [fn (array &$c) => $c['connections']['mysql_testing']['unix_socket'] = '/tmp/mysql.sock', 'unix_socket'],
    'empty port' => [fn (array &$c) => $c['connections']['mysql_testing']['port'] = '', 'explicit number'],
    'wrong driver' => [fn (array &$c) => $c['connections']['mysql_testing']['driver'] = 'pgsql', 'driver'],
    'empty username' => [fn (array &$c) => $c['connections']['mysql_testing']['username'] = '', 'empty'],
]);

it('refuses when the lane name appears in the raw env files', function () use ($valid): void {
    expect((string) TestCase::refusalFor($valid(), ['podtext', 'podtext_test']))->toContain('env');
});
