<?php

namespace Tests;

use App\Support\Testing\TestLaneContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * One accepted shape, one guard entrypoint (D3). Everything else throws,
     * BEFORE any migration runs.
     * NOTE for tests/Unit: Pest binds this class to Feature/Browser only —
     * unit tests bypass the guard (latent while they do not boot the app;
     * carried from the superseded spec).
     */
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

        $lane = config('database.connections.mysql_testing');
        $host = (string) ($lane['host'] ?? '');
        $port = (string) ($lane['port'] ?? '');
        $database = (string) ($lane['database'] ?? '');

        // The one guard entrypoint (D3): refusalFor -> fingerprint
        // (first-use / legacy bridge / existing) -> TIMESTAMP check, all
        // inside TestLaneContract so db:test-lane-reset and this boot path
        // can never drift apart. The run-lock/fingerprint paths are now
        // machine-global (DP7); the closure is the only DB access in this
        // method, kept injectable so TestLaneContract's orchestration stays
        // testable without a real connection.
        TestLaneContract::assertSafeBoot(
            config('database'),
            TestLaneContract::rawEnvDatabases(),
            TestLaneContract::fingerprintPath($host, $port, $database),
            TestLaneContract::legacyFingerprintPath($host, $port, $database),
            fn (string $sql, array $bindings): int => (int) DB::connection('mysql_testing')->selectOne($sql, $bindings)->n,
        );
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
}
