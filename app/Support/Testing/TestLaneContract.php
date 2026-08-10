<?php

namespace App\Support\Testing;

use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The one accepted shape for the disposable MySQL test lane, extracted from
 * tests/TestCase.php so non-test tooling (db:test-lane-reset) refuses on the
 * same clause table the suite boots on. `refusalFor()`/`rawEnvDatabases()`
 * are pure static: no state, no connection — callers pass the config array
 * and the raw env names in. `assertSafeBoot()` is the one boot-time guard
 * entrypoint built on top of them (D3); it does file I/O and logging by
 * necessity, but every input it needs (config, paths, row counts) is still a
 * parameter, never read internally, so tests can drive it without a real
 * connection or the real machine-global fingerprint.
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

    /**
     * Same-user machine-global directory holding both the run-lock and the
     * fingerprint (D3/DP7, relocated post-review from sys_get_temp_dir()):
     * any process, any cwd, under this user resolves the same path for the
     * same lane identity — the cross-worktree gap closes because there is no
     * per-tree input left in the path at all.
     *
     * HOME-anchored, not TMPDIR-anchored, and that distinction is
     * load-bearing, not cosmetic: macOS purges TMPDIR on a roughly weekly
     * cycle (measured), which would silently erase the fingerprint and force
     * a hard first-use refusal on a schema the machine already knows —
     * F4 pain on a timer. TMPDIR is also context-dependent: launchd, cron,
     * sudo, and php.ini can each report a different TMPDIR for what is
     * conceptually "the same machine," which would silently split the one
     * lock into several that never see each other. HOME does not have
     * either problem for a same-user suite. Falling back to
     * sys_get_temp_dir() happens ONLY when HOME itself is unset (unusual —
     * normal shells, cron, and launchd contexts all set it) — that fallback
     * reintroduces both risks named above, so it is a named degradation, not
     * a silent one.
     *
     * Directory creation is the writer's job, not this method's: tests/Pest.php
     * and ResetTestLane both mkdir before opening the lock file; assertSafeBoot
     * (first use) and adoptLegacyFingerprint (the bridge) both mkdir before
     * writing the fingerprint.
     */
    private static function sharedRoot(): string
    {
        return rtrim((string) (getenv('HOME') ?: sys_get_temp_dir()), '/').'/.cache/podtext-test-lane';
    }

    private static function identityHash(string $host, string $port, string $database): string
    {
        return sha1($host.'|'.$port.'|'.$database);
    }

    /**
     * The machine-global run-lock file for a lane identity (host|port|database).
     * Pre-boot safe: getenv(), sys_get_temp_dir(), and sha1() only, no
     * config()/app() — tests/Pest.php calls this before the framework boots.
     */
    public static function runLockPath(string $host, string $port, string $database): string
    {
        return self::sharedRoot().'/'.self::identityHash($host, $port, $database).'.lock';
    }

    /**
     * The machine-global first-use fingerprint file for a lane identity
     * (host|port|database). Pre-boot safe for the same reason as
     * runLockPath(), even though nothing currently calls it before boot.
     */
    public static function fingerprintPath(string $host, string $port, string $database): string
    {
        return self::sharedRoot().'/'.self::identityHash($host, $port, $database).'.fingerprint';
    }

    /**
     * The pre-D3/DP7 per-tree fingerprint location. Kept only so the
     * one-time bridge (adoptLegacyFingerprint()) can find a tree's existing
     * fingerprint after the relocation — never write here again.
     */
    public static function legacyFingerprintPath(string $host, string $port, string $database): string
    {
        return storage_path('framework/testing/mysql-lane/'.self::identityHash($host, $port, $database));
    }

    /**
     * One-time migration bridge: when the machine-global fingerprint is
     * absent but a legacy per-tree fingerprint exists for the same lane
     * identity, adopt its content instead of hard-refusing a lane the
     * machine already knows. Pure copy only — both paths are parameters, no
     * identity computation, and no deletion of the legacy source here; the
     * caller (assertSafeBoot) decides on cleanup and logging.
     */
    public static function adoptLegacyFingerprint(string $legacyPath, string $globalPath): bool
    {
        if (is_file($globalPath) || ! is_file($legacyPath)) {
            return false;
        }

        @mkdir(dirname($globalPath), 0755, true);

        return copy($legacyPath, $globalPath);
    }

    /**
     * The one boot-time guard entrypoint (D3): refusalFor -> fingerprint
     * (first-use / legacy bridge / existing) -> TIMESTAMP check, throwing
     * the same named-failure RuntimeExceptions TestCase has always thrown,
     * now behind a single call so a reader has one entrypoint to find.
     *
     * Both fingerprint paths and the row-count access are parameters, never
     * resolved internally, so a test can drive every branch against scratch
     * files and a stubbed count without ever touching the real
     * machine-global fingerprint or the real lane connection.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $rawEnvDatabases
     * @param  Closure(string, array<int, mixed>): int  $countRows  runs an
     *                                                              information_schema count query (sql, bindings) against the
     *                                                              lane connection and returns the single counted row
     */
    public static function assertSafeBoot(
        array $config,
        array $rawEnvDatabases,
        string $fingerprintPath,
        string $legacyFingerprintPath,
        Closure $countRows,
    ): void {
        $refusal = self::refusalFor($config, $rawEnvDatabases);

        if ($refusal !== null) {
            throw new RuntimeException('Refusing to run tests: '.$refusal);
        }

        $lane = $config['connections']['mysql_testing'] ?? [];
        $database = (string) ($lane['database'] ?? '');

        if (! is_file($fingerprintPath)) {
            if (self::adoptLegacyFingerprint($legacyFingerprintPath, $fingerprintPath)) {
                $removed = @unlink($legacyFingerprintPath);
                Log::info("Adopted the legacy per-tree lane fingerprint at {$legacyFingerprintPath} into the machine-global fingerprint at {$fingerprintPath} (D3/DP7 relocation)".($removed ? ' and removed the stale file.' : '.'));
            } else {
                $tables = $countRows('SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]);

                if ($tables > 0) {
                    throw new RuntimeException("Refusing first use: `{$database}` already holds {$tables} tables and no fingerprint exists — is this a stranger's database?");
                }

                @mkdir(dirname($fingerprintPath), 0755, true);
                file_put_contents($fingerprintPath, now()->toIso8601String());

                return;
            }
        } else {
            // Durability belt: an already-established fingerprint gets its
            // mtime refreshed on every boot that confirms it (copy() already
            // gives the just-adopted branch above a fresh mtime, so this is
            // only needed here) — an external cleaner consulting mtime must
            // never mistake an actively-used lane for an abandoned one.
            @touch($fingerprintPath);
        }

        if (($lane['timezone'] ?? null) === '+00:00') {
            $timestamps = $countRows('SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND DATA_TYPE = "timestamp"', [$database]);

            if ($timestamps > 0) {
                throw new RuntimeException("The lane pins +00:00 but holds {$timestamps} TIMESTAMP columns — it would test clock semantics production does not have. Run the alignment migration first (spec §7).");
            }
        }
    }
}
