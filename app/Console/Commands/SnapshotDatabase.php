<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * A restorable, timestamped dump of the current MySQL database.
 *
 * Exists so browser-testing against real data is reversible: snapshot before,
 * restore after, and the app database is back to the bytes it had
 * (docs/phase-02/database-alignment-spec.md §13 step 0). Also the rehearsal
 * input for the alignment migration, which is why the flags are chosen against
 * the two dump traps that plan measured:
 *
 * - No `--databases`: its header embeds `CREATE DATABASE` + `USE`, which
 *   hijacks any restore aimed at a different schema (trap B1). A table-level
 *   dump has neither, so the restore target is always the caller's choice.
 * - `--skip-tz-utc`: the default `--tz-utc` re-renders every TIMESTAMP through
 *   a `SET TIME_ZONE='+00:00'` header (trap B2). With it skipped, literals
 *   dump exactly as the app reads them — byte-faithful on the same server,
 *   and inert entirely once the columns are DATETIME.
 * - `--no-tablespaces`: silences the benign PROCESS-privilege warning for
 *   non-root users (seen on production 2026-07-30).
 *
 * The password travels in MYSQL_PWD, never in argv where `ps` would show it.
 */
#[Signature('db:snapshot {--label= : Optional suffix for the snapshot filename}')]
#[Description('Write a gzipped, restorable mysqldump of the default database to storage.')]
class SnapshotDatabase extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:snapshot only supports MySQL; the default connection is '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $directory = self::snapshotDirectory();

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Could not create the snapshot directory {$directory}.");

            return self::FAILURE;
        }

        $config = DB::connection()->getConfig();
        $label = (string) $this->option('label');
        $path = $directory.'/'.self::snapshotBasename((string) $config['database'], now(), $label);

        $result = Process::env(self::processEnvironment($config))
            ->run(self::dumpShellCommand($config, $path));

        if ($result->failed()) {
            @unlink($path);
            $this->error('mysqldump failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $manifest = self::manifest($config, $path);
        file_put_contents(Str::replaceLast('.sql.gz', '.json', $path), json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ).PHP_EOL);

        $this->info(sprintf(
            'Snapshot written: %s (%s, %d migrations, server %s)',
            basename($path),
            Str::of((string) round(filesize($path) / 1024))->append(' KB'),
            $manifest['migrations'],
            $manifest['server_version'],
        ));

        return self::SUCCESS;
    }

    public static function snapshotDirectory(): string
    {
        return storage_path('app/db-snapshots');
    }

    /**
     * Deterministic name: db, UTC stamp, optional label. Sorts chronologically.
     */
    public static function snapshotBasename(string $database, \DateTimeInterface $moment, string $label = ''): string
    {
        $suffix = $label === '' ? '' : '-'.Str::slug($label);

        return $database.'-'.$moment->format('Ymd-His').$suffix.'.sql.gz';
    }

    /**
     * The full dump pipeline. Public and pure so tests can pin the flag set
     * without a MySQL server: the traps here are config, not runtime.
     *
     * @param  array<string, mixed>  $config
     */
    public static function dumpShellCommand(array $config, string $outputPath): string
    {
        return sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --skip-tz-utc --no-tablespaces --quick %s | gzip > %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg((string) $config['username']),
            escapeshellarg((string) $config['database']),
            escapeshellarg($outputPath),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public static function processEnvironment(array $config): array
    {
        return ['MYSQL_PWD' => (string) ($config['password'] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{database: string, host: string, port: string, connection: string, server_version: string, migrations: int, created_at: string, app_env: string, sha1: string}
     */
    private static function manifest(array $config, string $path): array
    {
        return [
            'database' => (string) $config['database'],
            'host' => (string) $config['host'],
            'port' => (string) $config['port'],
            'connection' => (string) $config['name'],
            'server_version' => (string) DB::selectOne('SELECT VERSION() v')->v,
            'migrations' => (int) DB::table('migrations')->count(),
            'created_at' => now()->toIso8601String(),
            'app_env' => (string) app()->environment(),
            'sha1' => sha1_file($path) ?: '',
        ];
    }
}
