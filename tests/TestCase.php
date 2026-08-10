<?php

namespace Tests;

use App\Support\Testing\TestLaneContract;
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
        $refusal = TestLaneContract::refusalFor(config('database'), TestLaneContract::rawEnvDatabases());

        if ($refusal === null) {
            $this->assertDisposableSchema();

            return;
        }

        throw new RuntimeException('Refusing to run tests: '.$refusal);
    }

    /**
     * First use of a schema must find it empty; afterwards a fingerprint file
     * remembers it. Also asserts the lane carries zero TIMESTAMP columns while
     * its connection pins +00:00 — the spec §7 ordering refusal made real.
     */
    private function assertDisposableSchema(): void
    {
        $lane = config('database.connections.mysql_testing');
        $fingerprint = TestLaneContract::fingerprintPath((string) $lane['host'], (string) $lane['port'], (string) $lane['database']);
        $directory = dirname($fingerprint);

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
