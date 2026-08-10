<?php

use App\Console\Commands\RestoreDatabase;
use App\Console\Commands\SnapshotDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The snapshot/restore pair is MySQL-only. The suite now boots on the
 * dedicated mysql_testing lane, so the driver refusal these tests used to
 * pin is unreachable — each now exercises the next real, live-testable
 * guard instead. The exact shell/flag contracts stay pinned below without a
 * server, same as before: the flags ARE the safety design — each one traces
 * to a measured trap in database-alignment-spec.md §10.5 — so drift in the
 * command string is drift in the safety, and this file is what catches it.
 */
it('writes a real snapshot and manifest against the aligned mysql lane', function (): void {
    // db:snapshot has no required argument to validate before it shells out
    // — unlike db:restore below, there is no cheap pre-flight refusal left
    // once the driver check passes. mysqldump is a real local dependency of
    // the same Herd MySQL install the lane connection itself depends on, so
    // this runs for real and cleans up what it writes.
    $directory = SnapshotDatabase::snapshotDirectory();
    $before = collect(glob($directory.'/*.sql.gz') ?: []);

    $this->artisan('db:snapshot', ['--label' => 'db-snapshot-command-test'])
        ->expectsOutputToContain('Snapshot written:')
        ->assertSuccessful();

    $created = collect(glob($directory.'/*.sql.gz') ?: [])->diff($before)->values();

    // finally, not sequential: a failed assertion here must not skip
    // cleanup and orphan a real file in storage/app/db-snapshots/.
    try {
        expect($created)->toHaveCount(1);

        // The test's own name promises "and manifest" — assert the
        // manifest file db:snapshot writes alongside the dump actually
        // exists, not just the .sql.gz.
        expect(is_file(Str::replaceLast('.sql.gz', '.json', $created->first())))->toBeTrue();
    } finally {
        foreach ($created as $sqlFile) {
            @unlink($sqlFile);
            @unlink(Str::replaceLast('.sql.gz', '.json', $sqlFile));
        }
    }
});

it('refuses a restore file that does not exist', function (): void {
    // The driver refusal is unreachable on the mysql lane — the next real
    // guard is resolveSnapshotPath()'s file-existence check, which runs
    // before any dump content or database is touched.
    $this->artisan('db:restore', ['file' => 'anything.sql.gz'])
        ->expectsOutputToContain('Snapshot not found')
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

/** A tiny gzipped dump fixture; $withTimestamp switches the one column type under test, $pastFirstChunk pushes it beyond the first 64KB read. */
function timestampProbeDump(bool $withTimestamp, bool $pastFirstChunk = false): string
{
    $column = $withTimestamp ? '`created_at` timestamp NULL DEFAULT NULL' : '`created_at` datetime NULL DEFAULT NULL';
    $filler = $pastFirstChunk ? str_repeat("-- filler\n", 30000) : '';
    $sql = "-- fixture\n{$filler}CREATE TABLE `probe` (\n  `id` bigint unsigned NOT NULL,\n  {$column},\n  PRIMARY KEY (`id`)\n);\n";
    File::ensureDirectoryExists(SnapshotDatabase::snapshotDirectory());
    $path = SnapshotDatabase::snapshotDirectory().'/fixture-timestamp-probe.sql.gz';
    file_put_contents($path, gzencode($sql));

    return $path;
}

it('classifies TIMESTAMP DDL against the pinned connection', function (bool $withTimestamp, ?string $timezone, bool $allow, bool $refused, bool $pastFirstChunk = false): void {
    $path = timestampProbeDump($withTimestamp, $pastFirstChunk);

    try {
        $refusal = RestoreDatabase::timestampDdlRefusal($path, $timezone, $allow);
        $refused ? expect($refusal)->toContain('TIMESTAMP') : expect($refusal)->toBeNull();
    } finally {
        @unlink($path);
    }
})->with([
    'timestamp + pinned = refused' => [true, '+00:00', false, true],
    'timestamp + unpinned = allowed' => [true, null, false, false],
    'timestamp + pinned + flag = allowed' => [true, '+00:00', true, false],
    'datetime + pinned = allowed' => [false, '+00:00', false, false],
    'timestamp past the first 64KB = still refused' => [true, '+00:00', false, true, true],
]);

it('refuses to restore a TIMESTAMP dump through the command before any confirmation', function (): void {
    timestampProbeDump(true);

    try {
        $this->artisan('db:restore', ['file' => 'fixture-timestamp-probe.sql.gz'])
            ->expectsOutputToContain('pins +00:00')
            ->assertExitCode(1);
    } finally {
        @unlink(SnapshotDatabase::snapshotDirectory().'/fixture-timestamp-probe.sql.gz');
    }
});

it('leaves the B1 retargeting refusal untouched by --allow-timestamp-dump', function (): void {
    File::ensureDirectoryExists(SnapshotDatabase::snapshotDirectory());
    $path = SnapshotDatabase::snapshotDirectory().'/fixture-b1-probe.sql.gz';
    file_put_contents($path, gzencode("CREATE DATABASE `hijack`;\nUSE `hijack`;\n"));

    try {
        $this->artisan('db:restore', ['file' => 'fixture-b1-probe.sql.gz', '--allow-timestamp-dump' => true])
            ->expectsOutputToContain('CREATE DATABASE')
            ->assertExitCode(1);
    } finally {
        @unlink($path);
    }
});
