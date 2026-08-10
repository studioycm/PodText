<?php

namespace App\Support\Testing;

/**
 * The one accepted shape for the disposable MySQL test lane, extracted from
 * tests/TestCase.php so non-test tooling (db:test-lane-reset) refuses on the
 * same clause table the suite boots on. Pure static: no state, no connection
 * — callers pass the config array and the raw env names in.
 */
final class TestLaneContract
{
    /**
     * Pure clause table (spec §7). Returns the first refusal, null when safe.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $rawEnvDatabases
     */
    public static function refusalFor(array $config, array $rawEnvDatabases): ?string
    {
        if (($config['default'] ?? null) !== 'mysql_testing') {
            return 'database.default must be mysql_testing, never the app connection; got '.json_encode($config['default'] ?? null).'.';
        }

        $lane = $config['connections']['mysql_testing'] ?? [];

        return match (true) {
            ($lane['driver'] ?? null) !== 'mysql' => 'the lane driver must be mysql.',
            array_key_exists('url', $lane) => 'a url/DSN key silently overrides host and database — remove it.',
            array_key_exists('unix_socket', $lane) => 'a unix_socket key bypasses host and port — remove it.',
            ($lane['database'] ?? null) === null || $lane['database'] === '' => 'no lane database configured — failing closed.',
            preg_match('/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/', (string) $lane['database']) !== 1 => 'the lane database name must match /^[a-z][a-z0-9_]*_test(_[0-9]+)?$/.',
            in_array((string) $lane['database'], $rawEnvDatabases, true) => 'the lane database name appears as a DB_DATABASE in the raw .env files — a forced var could be masking the real name.',
            ! in_array((string) ($lane['host'] ?? ''), ['127.0.0.1', '::1'], true) => 'the lane host must be 127.0.0.1 or ::1 — localhost means the unix socket (the app daemon), and a remote host is never a test target.',
            preg_match('/^\d+$/', (string) ($lane['port'] ?? '')) !== 1 => 'the lane port must be an explicit number — an empty port silently resolves to the app daemon.',
            (string) ($lane['port'] ?? '') === (string) ($config['connections']['mysql']['port'] ?? '') => 'the lane port equals the app connection port — the lane must live on its own daemon.',
            ($lane['username'] ?? '') === '' => 'the lane username is empty.',
            ($lane['username'] ?? '') === 'root' => 'root would bypass the schema-scoped grant — the last barrier. Refused.',
            ($lane['username'] ?? '') === ($config['connections']['mysql']['username'] ?? null) => 'the lane username equals the app username — the grant barrier would be gone.',
            default => null,
        };
    }

    /**
     * DB_DATABASE values read from the raw .env files — NOT env(), which a
     * forced phpunit var could mask.
     *
     * @return list<string>
     */
    public static function rawEnvDatabases(): array
    {
        $values = [];

        foreach ([base_path('.env'), base_path('.env.example')] as $file) {
            if (! is_file($file)) {
                continue;
            }
            if (preg_match('/^DB_DATABASE=(.*)$/m', (string) file_get_contents($file), $m) === 1) {
                $values[] = trim($m[1], "\"' \r");
            }
        }

        return array_values(array_filter($values));
    }

    /** The first-use fingerprint file for a lane identity (host|port|database). */
    public static function fingerprintPath(string $host, string $port, string $database): string
    {
        return storage_path('framework/testing/mysql-lane/'.sha1($host.'|'.$port.'|'.$database));
    }
}
