<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->assertSafeTestingDatabase();
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
