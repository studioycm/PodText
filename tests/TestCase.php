<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        $this->forceSafeTestingEnvironment();

        parent::refreshApplication();

        config([
            'app.env' => 'testing',
            'cache.default' => 'array',
            'database.default' => 'mysql_testing',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            // Even an explicit DB::connection('mysql') inside a test must not
            // reach the real schema (spec §7 free hardening).
            'database.connections.mysql.database' => 'unreachable_from_tests',
            'database.connections.mariadb.database' => 'unreachable_from_tests',
            // A stray DB::connection('sqlite') must hit memory, never a repo file.
            'database.connections.sqlite.database' => ':memory:',
        ]);
        $this->app->detectEnvironment(fn (): string => 'testing');

        $this->assertSafeTestingDatabase();
    }

    private function forceSafeTestingEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'mysql_testing',
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * One accepted shape. Everything else throws, BEFORE any migration runs.
     * NOTE for tests/Unit: Pest binds this class to Feature/Browser only —
     * unit tests bypass the guard (latent while they do not boot the app;
     * carried from the superseded spec).
     */
    private function assertSafeTestingDatabase(): void
    {
        $refusal = self::refusalFor(config('database'), self::rawEnvDatabases());

        if ($refusal === null) {
            $this->assertDisposableSchema();

            return;
        }

        throw new RuntimeException('Refusing to run tests: '.$refusal);
    }

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
    private static function rawEnvDatabases(): array
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

    /**
     * First use of a schema must find it empty; afterwards a fingerprint file
     * remembers it. Also asserts the lane carries zero TIMESTAMP columns while
     * its connection pins +00:00 — the spec §7 ordering refusal made real.
     */
    private function assertDisposableSchema(): void
    {
        $lane = config('database.connections.mysql_testing');
        $directory = storage_path('framework/testing/mysql-lane');
        $fingerprint = $directory.'/'.sha1($lane['host'].'|'.$lane['port'].'|'.$lane['database']);

        if (! is_file($fingerprint)) {
            $tables = (int) DB::connection('mysql_testing')
                ->selectOne('SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$lane['database']])->n;

            if ($tables > 0) {
                throw new RuntimeException("Refusing first use: `{$lane['database']}` already holds {$tables} tables and no fingerprint exists — is this a stranger's database?");
            }

            @mkdir($directory, 0755, true);
            file_put_contents($fingerprint, now()->toIso8601String());

            return;
        }

        if (($lane['timezone'] ?? null) === '+00:00') {
            $timestamps = (int) DB::connection('mysql_testing')
                ->selectOne('SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND DATA_TYPE = "timestamp"', [$lane['database']])->n;

            if ($timestamps > 0) {
                throw new RuntimeException("The lane pins +00:00 but holds {$timestamps} TIMESTAMP columns — it would test clock semantics production does not have. Run the alignment migration first (spec §7).");
            }
        }
    }
}
