<?php

use App\Console\Commands\RestoreDatabase;
use App\Console\Commands\SnapshotDatabase;
use Carbon\CarbonImmutable;

/**
 * The snapshot/restore pair is MySQL-only and the suite runs on SQLite, so
 * these tests pin the two things that do not need a server: the refusals and
 * the exact shell/flag contracts. The flags ARE the safety design — each one
 * traces to a measured trap in database-alignment-spec.md §10.5 — so drift in
 * the command string is drift in the safety, and this file is what catches it.
 */
it('refuses to snapshot a non-mysql connection', function (): void {
    $this->artisan('db:snapshot')
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
});

it('refuses to restore into a non-mysql connection', function (): void {
    $this->artisan('db:restore', ['file' => 'anything.sql.gz'])
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
});

it('builds a dump command that avoids both measured dump traps', function (): void {
    $command = SnapshotDatabase::dumpShellCommand([
        'host' => '127.0.0.1', 'port' => '3306', 'username' => 'podtext',
        'password' => 'secret', 'database' => 'podtext',
    ], '/tmp/out.sql.gz');

    // Trap B2: --tz-utc defaults ON and re-renders every TIMESTAMP literal.
    expect($command)->toContain('--skip-tz-utc')
        // Trap B1: --databases embeds CREATE DATABASE/USE and hijacks restores.
        ->not->toContain('--databases')
        // Consistent read of a live database.
        ->toContain('--single-transaction')
        // The benign PROCESS-privilege warning for non-root users.
        ->toContain('--no-tablespaces')
        ->toContain("'podtext'")
        ->toContain('gzip')
        // The password must never reach argv, where ps would show it.
        ->not->toContain('secret');

    expect(SnapshotDatabase::processEnvironment(['password' => 'secret']))
        ->toBe(['MYSQL_PWD' => 'secret']);
});

it('builds a restore command that targets only the configured database', function (): void {
    $command = RestoreDatabase::restoreShellCommand([
        'host' => '127.0.0.1', 'port' => '3306', 'username' => 'podtext',
        'password' => 'secret', 'database' => 'podtext',
    ], '/tmp/in.sql.gz');

    expect($command)->toContain('gzip -dc')
        ->toContain("'podtext'")
        ->not->toContain('secret');
});

it('names snapshots chronologically with an optional slugged label', function (): void {
    $moment = CarbonImmutable::parse('2026-08-08 12:34:56', 'UTC');

    expect(SnapshotDatabase::snapshotBasename('podtext', $moment))
        ->toBe('podtext-20260808-123456.sql.gz');
    expect(SnapshotDatabase::snapshotBasename('podtext', $moment, 'Before Alignment'))
        ->toBe('podtext-20260808-123456-before-alignment.sql.gz');
});

describe('restore content guards', function (): void {
    /** @param non-empty-string $content */
    function gzFixture(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'snap-test-').'.sql.gz';
        file_put_contents($path, gzencode($content));

        return $path;
    }

    it('refuses a dump that would retarget the restore (B1)', function (string $header): void {
        $path = gzFixture($header."\nINSERT INTO `t` VALUES (1);\n");

        expect(RestoreDatabase::contentRefusal($path, false))
            ->toContain('CREATE DATABASE/USE');

        @unlink($path);
    })->with([
        'create database' => 'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `podtext`;',
        'use statement' => 'USE `podtext`;',
    ]);

    it('refuses a --tz-utc dump unless explicitly allowed (B2)', function (): void {
        $path = gzFixture("/*!40103 SET TIME_ZONE='+00:00' */;\nINSERT INTO `t` VALUES (1);\n");

        expect(RestoreDatabase::contentRefusal($path, false))->toContain('--tz-utc');
        expect(RestoreDatabase::contentRefusal($path, true))->toBeNull();

        @unlink($path);
    });

    it('accepts a clean table-level dump', function (): void {
        $path = gzFixture("-- MySQL dump\nDROP TABLE IF EXISTS `t`;\nCREATE TABLE `t` (`id` int);\nINSERT INTO `t` VALUES (1);\n");

        expect(RestoreDatabase::contentRefusal($path, false))->toBeNull();

        @unlink($path);
    });

    it('does not mistake column comments for a USE statement', function (): void {
        $path = gzFixture("-- because users USE the search box\nINSERT INTO `t` VALUES ('USE with care');\n");

        expect(RestoreDatabase::contentRefusal($path, false))->toBeNull();

        @unlink($path);
    });
});
