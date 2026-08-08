<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Restore a db:snapshot dump into the default database.
 *
 * The restore target is always the CURRENT default connection's database —
 * never a name parsed out of the dump. Two content guards enforce that
 * contract before a single statement runs, both aimed at traps the alignment
 * plan measured (docs/phase-02/database-alignment-spec.md §10.5):
 *
 * - A dump carrying `CREATE DATABASE` / `USE` would silently retarget the
 *   restore at whatever schema its header names (trap B1). db:snapshot never
 *   produces one; a foreign dump that does is refused.
 * - A dump carrying `SET TIME_ZONE='+00:00'` was taken with `--tz-utc`, so its
 *   TIMESTAMP literals are re-rendered UTC, not the app-visible bytes this
 *   tool promises to restore (trap B2). Refused unless --allow-utc-dump.
 *
 * Destructive by design — the dump's DROP TABLE IF EXISTS statements replace
 * every table it contains. Tables created after the snapshot survive, which is
 * why the migrations table riding the dump keeps `php artisan migrate` honest.
 */
#[Signature('db:restore {file? : Snapshot filename or path; omit to list} {--latest : Restore the newest snapshot} {--allow-utc-dump : Permit a --tz-utc dump} {--force : Skip the typed confirmation}')]
#[Description('Restore a db:snapshot dump into the default MySQL database, guarded against retargeting.')]
class RestoreDatabase extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:restore only supports MySQL; the default connection is '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $path = $this->resolveSnapshotPath();

        if ($path === null) {
            return self::FAILURE;
        }

        $refusal = self::contentRefusal($path, (bool) $this->option('allow-utc-dump'));

        if ($refusal !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }

        $config = DB::connection()->getConfig();
        $database = (string) $config['database'];

        if (! $this->option('force')) {
            $typed = $this->ask("Restoring will DROP and rewrite every table the dump contains, inside `{$database}`. Type the database name to continue");

            if ($typed !== $database) {
                $this->warn('Aborted — the typed name did not match.');

                return self::FAILURE;
            }
        }

        $result = Process::env(SnapshotDatabase::processEnvironment($config))
            ->run(self::restoreShellCommand($config, $path));

        if ($result->failed()) {
            $this->error('Restore failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info(sprintf('Restored %s into `%s` (%d migrations now recorded).', basename($path), $database, DB::table('migrations')->count()));

        return self::SUCCESS;
    }

    /**
     * The full restore pipeline. Public and pure for the same reason as
     * SnapshotDatabase::dumpShellCommand().
     *
     * @param  array<string, mixed>  $config
     */
    public static function restoreShellCommand(array $config, string $dumpPath): string
    {
        return sprintf(
            'gzip -dc %s | mysql --host=%s --port=%s --user=%s %s',
            escapeshellarg($dumpPath),
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg((string) $config['username']),
            escapeshellarg((string) $config['database']),
        );
    }

    /**
     * Scan the decompressed head of the dump for the two retargeting traps.
     * The statements both live in mysqldump's header, so 64 KB is generous.
     */
    public static function contentRefusal(string $path, bool $allowUtcDump): ?string
    {
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            return "Could not open {$path} as a gzip stream.";
        }

        $head = (string) gzread($handle, 65536);
        gzclose($handle);

        if (preg_match('/^\s*CREATE DATABASE\b|^\s*USE\b|\bUSE `/mi', $head) === 1) {
            return 'Refused: the dump carries CREATE DATABASE/USE and would retarget the restore at the schema its header names (alignment spec trap B1). db:snapshot never produces such a dump.';
        }

        if (! $allowUtcDump && str_contains($head, "SET TIME_ZONE='+00:00'")) {
            return 'Refused: the dump was taken with --tz-utc, so its TIMESTAMP literals are re-rendered UTC rather than the app-visible bytes (alignment spec trap B2). Pass --allow-utc-dump only if that is understood.';
        }

        return null;
    }

    private function resolveSnapshotPath(): ?string
    {
        $directory = SnapshotDatabase::snapshotDirectory();
        $snapshots = collect(glob($directory.'/*.sql.gz') ?: [])->sort()->values();

        if ($this->option('latest')) {
            if ($snapshots->isEmpty()) {
                $this->error("No snapshots found in {$directory}.");

                return null;
            }

            return $snapshots->last();
        }

        $file = (string) $this->argument('file');

        if ($file === '') {
            $this->line($snapshots->isEmpty() ? "No snapshots in {$directory}." : "Snapshots in {$directory}:");
            $snapshots->each(fn (string $snapshot) => $this->line('  '.basename($snapshot)));
            $this->line('Pass a filename, or --latest.');

            return null;
        }

        $path = str_contains($file, '/') ? $file : $directory.'/'.$file;

        if (! is_file($path)) {
            $this->error("Snapshot not found: {$path}");

            return null;
        }

        return $path;
    }
}
