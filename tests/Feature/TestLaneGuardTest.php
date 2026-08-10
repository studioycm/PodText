<?php

use App\Support\Testing\TestLaneContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/** A fresh, unique scratch root under the OS temp dir — never the real machine-global lane paths. */
function laneGuardScratchRoot(): string
{
    return sys_get_temp_dir().'/podtext-test-lane-guard-'.bin2hex(random_bytes(6));
}

$valid = fn (): array => [
    'default' => 'mysql_testing',
    'connections' => ['mysql_testing' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
        'database' => 'podtext_test', 'username' => 'podtext_test', 'password' => 'x',
    ], 'mysql' => ['port' => '3306', 'database' => 'podtext', 'username' => 'podtext']],
];

it('accepts the canonical lane shape', function () use ($valid): void {
    expect(TestLaneContract::refusalFor($valid(), ['podtext']))->toBeNull();
});

it('refuses every broken shape', function (callable $mutate, string $needle) use ($valid): void {
    $config = $valid();
    $mutate($config);
    expect((string) TestLaneContract::refusalFor($config, ['podtext']))->toContain($needle);
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
    expect((string) TestLaneContract::refusalFor($valid(), ['podtext', 'podtext_test']))->toContain('env');
});

/*
 * D3/DP7: the run-lock and the fingerprint are now machine-global,
 * lane-identity-keyed paths — never the real machine-global lane paths in
 * these tests, which is why every identity below uses obviously-fake
 * host/port/database strings.
 *
 * The shared root is HOME-anchored, not TMPDIR-anchored (post-review fix):
 * macOS purges TMPDIR on a roughly weekly cycle, which would silently erase
 * the fingerprint and force a hard first-use refusal on a schema the machine
 * already knows; TMPDIR is also context-dependent (launchd/cron/sudo/php-ini
 * can each see a different value), which could split "the" machine-global
 * lock into several non-communicating ones. HOME is stable per-user across
 * all of those contexts, so it is the primary root; TMPDIR is only the
 * fallback for the (unusual) case where HOME itself is unset.
 */

it('keys the run-lock and fingerprint paths under the HOME-anchored shared root with the same identity hash and distinct suffixes', function (): void {
    $root = rtrim((string) getenv('HOME'), '/').'/.cache/podtext-test-lane';
    $hash = sha1('scratch-host|scratch-port|scratch_test_db');

    expect(TestLaneContract::runLockPath('scratch-host', 'scratch-port', 'scratch_test_db'))
        ->toBe($root.'/'.$hash.'.lock')
        ->and(TestLaneContract::fingerprintPath('scratch-host', 'scratch-port', 'scratch_test_db'))
        ->toBe($root.'/'.$hash.'.fingerprint');
});

it('falls back to sys_get_temp_dir() only when HOME is unset — the degraded case, named honestly', function (): void {
    $originalHome = getenv('HOME');

    try {
        putenv('HOME');

        expect(getenv('HOME'))->toBe(false, 'the test setup did not actually unset HOME — the fallback branch was never exercised');

        $root = rtrim(sys_get_temp_dir(), '/').'/.cache/podtext-test-lane';
        $hash = sha1('scratch-host|scratch-port|scratch_test_db');

        expect(TestLaneContract::runLockPath('scratch-host', 'scratch-port', 'scratch_test_db'))
            ->toBe($root.'/'.$hash.'.lock');
    } finally {
        putenv("HOME={$originalHome}");
    }
});

it('resolves the same run-lock path for the same identity regardless of call site — the cross-worktree proof at unit scale', function (): void {
    // Two "processes" would just be two calls with the same three raw
    // strings; there is no per-tree input left anywhere in the signature.
    expect(TestLaneContract::runLockPath('127.0.0.1', '3307', 'podtext_test_2'))
        ->toBe(TestLaneContract::runLockPath('127.0.0.1', '3307', 'podtext_test_2'));
});

it('keeps the legacy per-tree fingerprint path identical to what fingerprintPath used to return before D3/DP7', function (): void {
    $hash = sha1('scratch-host|scratch-port|scratch_test_db');

    expect(TestLaneContract::legacyFingerprintPath('scratch-host', 'scratch-port', 'scratch_test_db'))
        ->toBe(storage_path('framework/testing/mysql-lane/'.$hash));
});

it('adopts the legacy fingerprint by copying its content when the global one is absent', function (): void {
    $root = laneGuardScratchRoot();
    $legacy = $root.'/legacy/fingerprint-file';
    $global = $root.'/global/fingerprint-file';

    try {
        File::ensureDirectoryExists(dirname($legacy));
        File::put($legacy, '2020-01-01T00:00:00+00:00');

        expect(TestLaneContract::adoptLegacyFingerprint($legacy, $global))->toBeTrue()
            ->and(File::exists($global))->toBeTrue()
            ->and(File::get($global))->toBe('2020-01-01T00:00:00+00:00')
            // Pure copy only — deletion of the legacy source is the caller's job.
            ->and(File::exists($legacy))->toBeTrue();
    } finally {
        File::deleteDirectory($root);
    }
});

it('does not overwrite an existing global fingerprint with the legacy one', function (): void {
    $root = laneGuardScratchRoot();
    $legacy = $root.'/legacy/fingerprint-file';
    $global = $root.'/global/fingerprint-file';

    try {
        File::ensureDirectoryExists(dirname($legacy));
        File::ensureDirectoryExists(dirname($global));
        File::put($legacy, 'legacy-content');
        File::put($global, 'global-content');

        expect(TestLaneContract::adoptLegacyFingerprint($legacy, $global))->toBeFalse()
            ->and(File::get($global))->toBe('global-content');
    } finally {
        File::deleteDirectory($root);
    }
});

it('returns false from the legacy bridge when there is no legacy fingerprint to adopt', function (): void {
    $root = laneGuardScratchRoot();

    expect(TestLaneContract::adoptLegacyFingerprint($root.'/nonexistent-legacy', $root.'/nonexistent-global'))->toBeFalse();
});

/*
 * D3: TestLaneContract::assertSafeBoot() is now the one boot-time guard
 * entrypoint TestCase::refreshApplication() calls. Every branch is driven
 * here with scratch fingerprint paths and a stubbed row-count closure — no
 * real DB connection and no touching the actual machine-global fingerprint
 * this very suite run depends on.
 */

it('assertSafeBoot throws the unchanged "Refusing to run tests" message on a refusal, and never touches the row-count closure', function () use ($valid): void {
    $config = $valid();
    $config['connections']['mysql_testing']['username'] = 'root';
    $root = laneGuardScratchRoot();

    try {
        expect(fn () => TestLaneContract::assertSafeBoot(
            $config,
            ['podtext'],
            $root.'/fingerprint',
            $root.'/legacy',
            fn (string $sql, array $bindings): int => throw new LogicException('the row-count closure must not run on a refusal'),
        ))->toThrow(RuntimeException::class, 'Refusing to run tests: ');
    } finally {
        File::deleteDirectory($root);
    }
});

it('assertSafeBoot writes a fresh fingerprint on true first use and returns before the TIMESTAMP check', function () use ($valid): void {
    $config = $valid();
    $config['connections']['mysql_testing']['timezone'] = '+00:00';
    $root = laneGuardScratchRoot();
    $fingerprint = $root.'/global/fingerprint';
    $legacy = $root.'/legacy/fingerprint';
    $calls = [];

    try {
        TestLaneContract::assertSafeBoot(
            $config,
            ['podtext'],
            $fingerprint,
            $legacy,
            function (string $sql, array $bindings) use (&$calls): int {
                $calls[] = $sql;

                return 0;
            },
        );

        expect(File::exists($fingerprint))->toBeTrue()
            // Exactly one query (the table count) — the TIMESTAMP check must
            // not fire on a true first use, timezone pinned or not.
            ->and($calls)->toHaveCount(1)
            ->and($calls[0])->toContain('information_schema.TABLES');
    } finally {
        File::deleteDirectory($root);
    }
});

it('assertSafeBoot refuses first use when the schema already holds tables', function () use ($valid): void {
    $root = laneGuardScratchRoot();

    try {
        expect(fn () => TestLaneContract::assertSafeBoot(
            $valid(),
            ['podtext'],
            $root.'/fingerprint',
            $root.'/legacy',
            fn (string $sql, array $bindings): int => 3,
        ))->toThrow(RuntimeException::class, "already holds 3 tables and no fingerprint exists — is this a stranger's database?");
    } finally {
        File::deleteDirectory($root);
    }
});

it('assertSafeBoot adopts a legacy fingerprint, removes the stale file, and still falls through to the TIMESTAMP check', function () use ($valid): void {
    $config = $valid();
    $config['connections']['mysql_testing']['timezone'] = '+00:00';
    $root = laneGuardScratchRoot();
    $fingerprint = $root.'/global/fingerprint';
    $legacy = $root.'/legacy/fingerprint';
    $calls = [];

    try {
        File::ensureDirectoryExists(dirname($legacy));
        File::put($legacy, '2020-01-01T00:00:00+00:00');

        TestLaneContract::assertSafeBoot(
            $config,
            ['podtext'],
            $fingerprint,
            $legacy,
            function (string $sql, array $bindings) use (&$calls): int {
                $calls[] = $sql;

                return 0;
            },
        );

        expect(File::get($fingerprint))->toBe('2020-01-01T00:00:00+00:00')
            ->and(File::exists($legacy))->toBeFalse()
            // Exactly one query (the TIMESTAMP count) — adopting is not a
            // true first use, so the table-count query must not run.
            ->and($calls)->toHaveCount(1)
            ->and($calls[0])->toContain('information_schema.COLUMNS');
    } finally {
        File::deleteDirectory($root);
    }
});

it('assertSafeBoot throws the pinned TIMESTAMP message for an existing fingerprint whose schema still holds TIMESTAMP columns', function () use ($valid): void {
    $config = $valid();
    $config['connections']['mysql_testing']['timezone'] = '+00:00';
    $root = laneGuardScratchRoot();
    $fingerprint = $root.'/fingerprint';

    try {
        File::ensureDirectoryExists($root);
        File::put($fingerprint, 'already-here');

        expect(fn () => TestLaneContract::assertSafeBoot(
            $config,
            ['podtext'],
            $fingerprint,
            $root.'/legacy-absent',
            fn (string $sql, array $bindings): int => 7,
        ))->toThrow(RuntimeException::class, 'The lane pins +00:00 but holds 7 TIMESTAMP columns — it would test clock semantics production does not have. Run the alignment migration first (spec §7).');
    } finally {
        File::deleteDirectory($root);
    }
});

it('assertSafeBoot passes through silently for an existing fingerprint whose schema has zero TIMESTAMP columns, and refreshes its mtime', function () use ($valid): void {
    $config = $valid();
    $config['connections']['mysql_testing']['timezone'] = '+00:00';
    $root = laneGuardScratchRoot();
    $fingerprint = $root.'/fingerprint';

    try {
        File::ensureDirectoryExists($root);
        File::put($fingerprint, 'already-here');
        touch($fingerprint, time() - 3600);
        $beforeMtime = filemtime($fingerprint);

        TestLaneContract::assertSafeBoot(
            $config,
            ['podtext'],
            $fingerprint,
            $root.'/legacy-absent',
            fn (string $sql, array $bindings): int => 0,
        );

        expect(File::get($fingerprint))->toBe('already-here')
            // The durability belt: an already-established fingerprint gets
            // its mtime refreshed on every boot that confirms it, so an
            // external cleaner consulting mtime never mistakes an
            // actively-used lane for an abandoned one.
            ->and(filemtime($fingerprint))->toBeGreaterThan($beforeMtime);
    } finally {
        File::deleteDirectory($root);
    }
});

it('assertSafeBoot does not claim it removed the stale legacy file when the unlink actually fails', function () use ($valid): void {
    Log::spy();

    $config = $valid();
    $config['connections']['mysql_testing']['timezone'] = '+00:00';
    $root = laneGuardScratchRoot();
    $fingerprint = $root.'/global/fingerprint';
    $legacyDir = $root.'/legacy';
    $legacy = $legacyDir.'/fingerprint';

    try {
        File::ensureDirectoryExists($legacyDir);
        File::put($legacy, '2020-01-01T00:00:00+00:00');
        // No write permission on the containing directory: unlink() on the
        // file inside it fails on a POSIX filesystem regardless of the
        // file's own permissions.
        chmod($legacyDir, 0555);

        TestLaneContract::assertSafeBoot(
            $config,
            ['podtext'],
            $fingerprint,
            $legacy,
            fn (string $sql, array $bindings): int => 0,
        );

        expect(File::exists($legacy))->toBeTrue('the unlink did not actually fail — this test is not exercising the intended scenario');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message): bool => ! str_contains($message, 'removed the stale file'));
    } finally {
        chmod($legacyDir, 0755);
        File::deleteDirectory($root);
    }
});
