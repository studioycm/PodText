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

/*
 * The run-lock's legibility half (2026-08-12), designed jointly from two
 * incidents in one evening: a WRITER breached T23b because nothing told it
 * another session's gate was in flight, and a RUNNER's blocked pest run
 * returned NOTHING VISIBLE — the refusal was STDERR-only and the runner's
 * output filter reads stdout JSON, so "blocked" and "silently passed" were
 * indistinguishable until `pgrep -fl "vendor/bin/pest"` turned up holder PID
 * 74407.
 *
 * flock remains the authority; the record in the file is advisory. It can
 * only ever be stale in the SAFE direction, because a holder stamps `held`
 * before it does anything else: a reader trusting a stale `held` waits longer
 * than it had to, never the reverse. These tests pin the record, the reader
 * and the refusal payload; the mid-run two-process probe proves the boot path
 * itself, which nothing in-process can.
 */

$holderIsAlive = static fn (bool $alive): Closure => static fn (int $pid): bool => $alive;

it('stamps a one-line JSON holder record naming the pid, the label and the lane', function (): void {
    $encoded = TestLaneContract::runLockRecord(
        'held',
        74407,
        '/Users/studioycm/Herd/PodText: vendor/bin/pest --compact',
        'podtext_test@127.0.0.1:3307',
        1_770_000_000,
    );

    expect(json_decode($encoded, true, flags: JSON_THROW_ON_ERROR))->toEqual([
        'state' => 'held',
        'pid' => 74407,
        'label' => '/Users/studioycm/Herd/PodText: vendor/bin/pest --compact',
        'lane' => 'podtext_test@127.0.0.1:3307',
        'started_at' => 1_770_000_000,
        'released_at' => null,
    ])
        // One line, so `cat ~/.cache/podtext-test-lane/*.lock` stays readable
        // to a human who never learns the record's shape.
        ->and(substr_count($encoded, "\n"))->toBe(1)
        ->and($encoded)->toEndWith("\n");
});

it('keeps a runaway argv from bloating the record, and a newline in it from breaking the one-line shape', function (): void {
    $encoded = TestLaneContract::runLockRecord('held', 1, str_repeat('x', 5000)."\nsecond line", 'lane', 1);

    expect(mb_strlen((string) json_decode($encoded, true, flags: JSON_THROW_ON_ERROR)['label']))->toBe(300)
        ->and(substr_count($encoded, "\n"))->toBe(1);
});

it('replaces the whole previous record when stamping, leaving no trailing bytes of the longer one', function (): void {
    $path = laneGuardScratchRoot();
    $handle = fopen($path, 'c+');

    try {
        TestLaneContract::writeRunLockRecord($handle, 'held', 999, str_repeat('long-label-', 20), 'lane', 1_770_000_000);
        TestLaneContract::writeRunLockRecord($handle, 'released', 999, 'short', 'lane', 1_770_000_000, 1_770_000_060);

        $contents = (string) file_get_contents($path);

        expect(substr_count($contents, "\n"))->toBe(1)
            ->and($contents)->not->toContain('long-label-')
            ->and(json_decode(trim($contents), true, flags: JSON_THROW_ON_ERROR))
            ->toMatchArray(['state' => 'released', 'released_at' => 1_770_000_060]);
    } finally {
        fclose($handle);
        File::delete($path);
    }
});

it('describes a live holder by pid, label and how long it has held the lane', function () use ($holderIsAlive): void {
    $raw = TestLaneContract::runLockRecord('held', 74407, 'PodText: vendor/bin/pest --compact', 'podtext_test@127.0.0.1:3307', 1_770_000_000);

    expect(TestLaneContract::describeRunLockHolder($raw, 1_770_000_192, $holderIsAlive(true)))
        ->toContain('74407')
        ->toContain('PodText: vendor/bin/pest --compact')
        ->toContain('3m 12s');
});

it('calls the record stale rather than naming a holder process that is gone', function () use ($holderIsAlive): void {
    $raw = TestLaneContract::runLockRecord('held', 74407, 'a finished run', 'lane', 1_770_000_000);

    expect(TestLaneContract::describeRunLockHolder($raw, 1_770_000_010, $holderIsAlive(false)))
        ->toContain('stale')
        ->toContain('74407');
});

it('reports an unidentified holder when the lock file carries no readable record', function (string|false|null $raw) use ($holderIsAlive): void {
    // Every one of these is reachable: a zero-byte file is what the lock has
    // held until today, `false` is @file_get_contents on an unreadable path,
    // and a partial line is what a reader catches between ftruncate and write.
    expect(TestLaneContract::describeRunLockHolder($raw, 1_770_000_000, $holderIsAlive(true)))
        ->toContain('unidentified');
})->with([
    'zero-byte file (every build before this one)' => [''],
    'unreadable file' => [false],
    'never written' => [null],
    'torn write' => ['{"state":"held","pi'],
    'valid json, wrong shape' => ['{"unrelated":true}'],
]);

it('treats a released record as stale too, because flock has already refused the caller', function () use ($holderIsAlive): void {
    $raw = TestLaneContract::runLockRecord('released', 74407, 'a finished run', 'lane', 1_770_000_000, 1_770_000_060);

    expect(TestLaneContract::describeRunLockHolder($raw, 1_770_000_061, $holderIsAlive(true)))
        ->toContain('unidentified');
});

it('never prints a negative age when the clock has gone backwards', function () use ($holderIsAlive): void {
    $raw = TestLaneContract::runLockRecord('held', 74407, 'a run', 'lane', 1_770_000_500);

    expect(TestLaneContract::describeRunLockHolder($raw, 1_770_000_000, $holderIsAlive(true)))
        ->toContain('0s')
        ->not->toContain('-');
});

/*
 * The more dangerous half. An agent runner filtering stdout for JSON read the
 * old STDERR-only refusal as an empty pass. Pest emits no agent-mode JSON of
 * its own — verified by reading vendor/pestphp/pest/src, there is no such
 * formatter — so the refusal has to produce that payload itself.
 */

it('refuses onto stdout, never stdout-silently, so a blocked run cannot be read as a pass', function () use ($holderIsAlive): void {
    $raw = TestLaneContract::runLockRecord('held', 74407, 'PodText: vendor/bin/pest', 'lane', 1_770_000_000);
    $refusal = TestLaneContract::runLockRefusal($raw, 1_770_000_060, $holderIsAlive(true));

    $lines = array_values(array_filter(explode("\n", $refusal['stdout'])));
    $json = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);

    expect($refusal['stdout'])->not->toBe('')
        ->and($json['result'])->toBe('refused')
        // A filter reading counts must not see a green run either.
        ->and($json['tests'])->toBe(0)
        ->and($json['holder'])->toContain('74407')
        // And a human reading raw output sees it without parsing anything.
        ->and($refusal['stdout'])->toContain('REFUSED')
        ->and($refusal['stderr'])->toContain('REFUSED');
});

it('refuses with EX_TEMPFAIL, so "blocked" is distinguishable from "tests failed" by exit code alone', function () use ($holderIsAlive): void {
    $refusal = TestLaneContract::runLockRefusal('', 1_770_000_000, $holderIsAlive(true));

    expect($refusal['code'])->toBe(75)
        // 1 is what a failing suite exits with — the code the refusal used to
        // share with it, which is why exit status alone could not tell a
        // blocked run from a red one.
        ->and($refusal['code'])->not->toBe(1)
        ->and($refusal['code'])->not->toBe(0);
});

it('is just as loud when the lock file itself cannot be opened — same hazard, different cause', function (): void {
    $abort = TestLaneContract::runLockUnopenable('/nope/unwritable.lock');

    $lines = array_values(array_filter(explode("\n", $abort['stdout'])));
    $json = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);

    expect($abort['stdout'])->not->toBe('')
        ->and($json['result'])->not->toBe('passed')
        ->and($json['tests'])->toBe(0)
        // The path IS the diagnosis for this one — say it in both places.
        ->and($json['lock_path'])->toBe('/nope/unwritable.lock')
        ->and($abort['stdout'])->toContain('/nope/unwritable.lock')
        ->and($abort['stderr'])->toContain('/nope/unwritable.lock');
});

it('separates "the lane is busy" from "the environment is broken" by exit code', function (): void {
    $broken = TestLaneContract::runLockUnopenable('/nope.lock')['code'];
    $busy = TestLaneContract::runLockRefusal('', 1, static fn (int $pid): bool => true)['code'];

    // 75 EX_TEMPFAIL invites a retry, which is true of a busy lane and false of
    // an unwritable lane root — retrying a permanent failure loops forever on a
    // condition no waiting fixes. Neither may be 1; that is a red suite.
    expect($broken)->toBe(73)
        ->and($busy)->toBe(75)
        ->and($broken)->not->toBe($busy)
        ->and($broken)->not->toBe(1)
        ->and($broken)->not->toBe(0);
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
