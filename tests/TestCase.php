<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);
        $this->app->detectEnvironment(fn (): string => 'testing');

        $this->assertSafeTestingDatabase();
    }

    private function forceSafeTestingEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function assertSafeTestingDatabase(): void
    {
        $defaultConnection = config('database.default');

        if (
            app()->environment('testing')
            && $defaultConnection === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:'
        ) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests against an unsafe database. Expected APP_ENV=testing, database.default=sqlite, and database.connections.sqlite.database=:memory:; got APP_ENV=%s, database.default=%s, sqlite.database=%s.',
            app()->environment(),
            (string) $defaultConnection,
            (string) config('database.connections.sqlite.database'),
        ));
    }
}
