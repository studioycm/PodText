<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The check that would have found `utf8mb3`.
 *
 * Production's schema default was `utf8mb3_unicode_ci` while all 40 tables were
 * `utf8mb4_unicode_ci`. Nothing was broken — but any table created without an
 * explicit charset would have inherited three-byte Unicode, and nothing in the
 * repo, the test suite or the deploy would ever have said so. The suite runs on
 * sqlite, which has no charset, no collation and no session timezone, so this
 * whole class of setting is structurally invisible to `php artisan test`.
 *
 * Read-only. Safe to run anywhere, including production.
 */
#[Signature('db:check-settings')]
#[Description('Report charset, collation and clock settings, and flag drift between them.')]
class CheckDatabaseSettings extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->warn('Not a MySQL connection ('.DB::connection()->getDriverName().') — nothing to check.');

            return self::SUCCESS;
        }

        $problems = [];

        $schema = DB::selectOne('SELECT DEFAULT_CHARACTER_SET_NAME cs, DEFAULT_COLLATION_NAME col
            FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()');

        $this->line("schema default   {$schema->cs} / {$schema->col}");

        if ($schema->cs !== 'utf8mb4') {
            $problems[] = "The schema default is {$schema->cs}, not utf8mb4. Tables created without an explicit "
                .'charset inherit it — three-byte Unicode drops emoji and some CJK. '
                .'Fix: ALTER DATABASE `'.DB::connection()->getDatabaseName().'` CHARACTER SET utf8mb4 COLLATE '
                .config('database.connections.mysql.collation', 'utf8mb4_unicode_ci').';';
        }

        foreach (['TABLES' => 'TABLE_COLLATION', 'COLUMNS' => 'COLLATION_NAME'] as $source => $column) {
            $rows = DB::select("SELECT {$column} c, COUNT(*) n FROM information_schema.{$source}
                WHERE TABLE_SCHEMA = DATABASE() AND {$column} IS NOT NULL GROUP BY 1 ORDER BY 2 DESC");

            foreach ($rows as $row) {
                $this->line(str_pad(mb_strtolower($source), 17).$row->c.'  ×'.$row->n);
            }

            // More than one collation in play is the expensive kind of drift:
            // a JOIN across two of them raises "Illegal mix of collations".
            if (count($rows) > 1) {
                $problems[] = mb_strtolower($source).' use '.count($rows).' different collations. A join across '
                    .'two of them fails with "Illegal mix of collations".';
            }
        }

        $types = collect(DB::select("SELECT DATA_TYPE dt, COUNT(*) n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('timestamp','datetime','date','time') GROUP BY 1"))
            ->mapWithKeys(fn (object $row): array => [$row->dt => (int) $row->n])->all();

        foreach ($types as $type => $count) {
            $this->line(str_pad('column types', 17)."{$type}  ×{$count}");
        }

        array_push($problems, ...self::columnTypeProblems($types));

        $pad = DB::selectOne('SELECT PAD_ATTRIBUTE p FROM information_schema.COLLATIONS WHERE COLLATION_NAME = ?',
            [config('database.connections.mysql.collation')]);

        if ($pad !== null && $pad->p !== 'NO PAD') {
            $problems[] = "The configured collation pads ({$pad->p}); the alignment target utf8mb4_0900_ai_ci is NO PAD.";
        }

        $clock = DB::selectOne('SELECT @@global.time_zone g, @@session.time_zone s, @@system_time_zone sys,
            TIMEDIFF(NOW(), UTC_TIMESTAMP()) offset');

        $this->line("clock            global={$clock->g} session={$clock->s} system={$clock->sys} offset={$clock->offset}");

        if ($clock->offset !== '00:00:00') {
            $problems[] = "The database clock is {$clock->offset} from UTC while the app stores UTC "
                .'(config/app.php). Values the DATABASE generates — NOW(), CURRENT_TIMESTAMP, '
                .'DEFAULT CURRENT_TIMESTAMP — disagree with app-written ones by that much. '
                .'App-written literals round-trip fine either way. See open-findings-triage.md §A.';
        }

        if ($problems === []) {
            $this->info('No drift found.');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($problems as $problem) {
            $this->warn('· '.$problem);
        }

        return self::FAILURE;
    }

    /**
     * @param  array<string, int>  $typeCounts
     * @return list<string>
     */
    public static function columnTypeProblems(array $typeCounts): array
    {
        $timestamps = $typeCounts['timestamp'] ?? 0;

        return $timestamps === 0 ? [] : [
            "{$timestamps} column(s) are still TIMESTAMP. The alignment target is DATETIME everywhere — "
            .'TIMESTAMP converts through the session timezone on read and write. See database-alignment-spec.md §4.',
        ];
    }
}
