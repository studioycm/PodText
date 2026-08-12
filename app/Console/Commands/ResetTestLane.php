<?php

namespace App\Console\Commands;

use App\Support\Testing\TestLaneContract;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset the dedicated MySQL test lane to first-use state.
 *
 * The run-lock and fingerprint are machine-global, lane-identity-keyed paths
 * (D3/DP7), so a fresh worktree normally INHERITS the fingerprint the
 * machine already holds for this lane identity and does not hard-refuse it
 * — the old "fresh worktree, no fingerprint file, hard refusal" pain is
 * solved at the root. This command is the sanctioned remedy for a genuinely
 * foreign lane (or a deliberate reset): it empties the lane schema and
 * removes the fingerprint, so the next pest boot re-fingerprints an empty
 * schema and migrates fresh.
 *
 * Refusal layers, in order (spec F4):
 * - the extracted one-shape clause table — the same table the suite boots on;
 * - the machine-global flock run-lock (a suite ANYWHERE ON THIS MACHINE is
 *   mid-run; a second same-process fd is denied too, which is what the
 *   in-suite test pins);
 * - live lane connections via information_schema.PROCESSLIST — every suite
 *   connects as the lane user, and PROCESSLIST shows the caller's own user
 *   without the PROCESS privilege; kept as a second, independent layer below
 *   the now-machine-global flock (belt-and-suspenders against a live
 *   connection whose flock is stale or was never acquired through Pest.php);
 * - the typed-name confirmation (unless --force);
 * - lock_wait_timeout=3 on the drop session, so a holder the probes missed
 *   fails the drop fast instead of hanging it.
 */
#[Signature('db:test-lane-reset {--force : Skip the typed confirmation}')]
#[Description('Empty the dedicated MySQL test lane and remove its fingerprint, so the next pest boot starts first-use clean.')]
class ResetTestLane extends Command
{
    private const LANE = 'mysql_testing';

    public function handle(): int
    {
        $config = array_merge(config('database'), ['default' => self::LANE]);
        $refusal = TestLaneContract::refusalFor($config, TestLaneContract::rawEnvDatabases());

        if ($refusal !== null) {
            $this->error('Refusing to reset: '.$refusal);

            return self::FAILURE;
        }

        $lane = $config['connections'][self::LANE];
        $database = (string) $lane['database'];
        $lockPath = TestLaneContract::runLockPath((string) $lane['host'], (string) $lane['port'], $database);
        @mkdir(dirname($lockPath), 0755, true);
        $lockHandle = fopen($lockPath, 'c+');

        if ($lockHandle === false) {
            $this->error("Could not open the run-lock file at {$lockPath} — check the shared lane root is writable.");

            return self::FAILURE;
        }

        if (! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $this->error('Refusing to reset: the MySQL lane run-lock is held by '.TestLaneContract::describeRunLockHolder(
                @file_get_contents($lockPath),
                time(),
                static fn (int $pid): bool => posix_kill($pid, 0),
            ).'.');

            return self::FAILURE;
        }

        /*
         * Stamp ourselves into the lock while we work. Without this, a pest
         * run refused BY THIS RESET would read the record of whichever run
         * held the lane before us — naming a dead process instead of the
         * live command actually in its way.
         */
        $laneLabel = $database.'@'.$lane['host'].':'.$lane['port'];
        $holderLabel = base_path().': php artisan db:test-lane-reset';
        $holderPid = (int) getmypid();
        $heldSince = time();

        TestLaneContract::writeRunLockRecord($lockHandle, 'held', $holderPid, $holderLabel, $laneLabel, $heldSince);

        try {
            $foreign = self::foreignLaneConnections($database);

            if ($foreign > 0) {
                $this->error("Refusing to reset: {$foreign} other connection(s) are live on `{$database}` — a suite may be running from another worktree.");

                return self::FAILURE;
            }

            if (! $this->option('force')) {
                $typed = $this->ask("This EMPTIES `{$database}` and deletes its fingerprint. Type the database name to continue");

                if ($typed !== $database) {
                    $this->warn('Aborted — the typed name did not match.');

                    return self::FAILURE;
                }
            }

            $connection = DB::connection(self::LANE);
            $tables = array_map(
                static fn (object $row): string => (string) $row->name,
                $connection->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [$database]),
            );

            $connection->statement('SET SESSION lock_wait_timeout = 3');
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

            try {
                foreach (self::dropStatements($database, $tables) as $statement) {
                    $connection->statement($statement);
                }
            } finally {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $fingerprint = TestLaneContract::fingerprintPath((string) $lane['host'], (string) $lane['port'], $database);

            if (is_file($fingerprint) && ! unlink($fingerprint)) {
                $this->error("Could not delete the fingerprint file: {$fingerprint}");

                return self::FAILURE;
            }

            $this->info(sprintf('Lane `%s` emptied (%d tables dropped) and fingerprint removed. The next pest boot re-fingerprints (or adopts a legacy fingerprint) and migrates fresh.', $database, count($tables)));

            return self::SUCCESS;
        } finally {
            TestLaneContract::writeRunLockRecord($lockHandle, 'released', $holderPid, $holderLabel, $laneLabel, $heldSince, time());
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** Lane connections other than this one — the cross-worktree suite probe. */
    public static function foreignLaneConnections(string $database): int
    {
        return (int) DB::connection(self::LANE)
            ->selectOne('SELECT COUNT(*) n FROM information_schema.PROCESSLIST WHERE DB = ? AND ID <> CONNECTION_ID()', [$database])->n;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    public static function dropStatements(string $database, array $tables): array
    {
        return array_map(
            static fn (string $table): string => sprintf('DROP TABLE IF EXISTS `%s`.`%s`', str_replace('`', '``', $database), str_replace('`', '``', $table)),
            $tables,
        );
    }
}
