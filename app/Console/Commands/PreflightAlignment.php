<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The three pre-flights the alignment migration requires, generated from
 * information_schema — never hand-listed (spec §10.5: the old plan produced
 * three different index counts by hand; the generated figure is 30).
 * Read-only; safe anywhere, including production.
 */
#[Signature('db:preflight-alignment')]
#[Description('B3 duplicate scan, B5 trailing-space scan, and their structural precondition, for the 0900_ai_ci change.')]
class PreflightAlignment extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:preflight-alignment only supports MySQL; got '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $findings = 0;

        // Precondition: a prefix or functional unique index would make the
        // full-column GROUP BY under-detect. Assert none exist (spec §10.5).
        $odd = DB::selectOne('SELECT
            SUM(CASE WHEN SUB_PART IS NOT NULL THEN 1 ELSE 0 END) prefixed,
            SUM(CASE WHEN EXPRESSION IS NOT NULL THEN 1 ELSE 0 END) functional
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0');

        if ((int) $odd->prefixed > 0 || (int) $odd->functional > 0) {
            $this->error("Precondition failed: {$odd->prefixed} prefix / {$odd->functional} functional unique index parts — the B3 scan cannot be trusted. Stop.");

            return self::FAILURE;
        }

        $indexes = DB::select('SELECT s.TABLE_NAME t, s.INDEX_NAME i,
                GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX) cols,
                GROUP_CONCAT(CASE WHEN c.COLLATION_NAME IS NOT NULL THEN s.COLUMN_NAME END ORDER BY s.SEQ_IN_INDEX) collated
            FROM information_schema.STATISTICS s
            JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = s.TABLE_SCHEMA
                AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME
            WHERE s.TABLE_SCHEMA = DATABASE() AND s.NON_UNIQUE = 0
            GROUP BY 1, 2 HAVING collated IS NOT NULL');

        $this->line('unique/primary indexes with collated columns: '.count($indexes));

        foreach ($indexes as $index) {
            $columns = explode(',', $index->cols);
            $collated = explode(',', (string) $index->collated);

            $n = (int) DB::selectOne(self::duplicateScanSql($index->t, $columns, $collated))->n;

            if ($n > 0) {
                $this->error("B3: {$index->t}.{$index->i} → {$n} colliding group(s) under utf8mb4_0900_ai_ci");
                $findings++;
            }

            foreach ($collated as $column) {
                if ($column === '') {
                    continue;
                }
                $pad = (int) DB::table($index->t)
                    ->whereRaw("LENGTH(`{$column}`) <> LENGTH(TRIM(TRAILING CHAR(32) FROM `{$column}`))")->count();

                if ($pad > 0) {
                    $this->warn("B5: {$index->t}.{$column} → {$pad} row(s) with trailing spaces (NO PAD makes these stop matching)");
                    $findings++;
                }
            }
        }

        if ($findings === 0) {
            $this->info('Pre-flight clean: zero B3 collisions, zero B5 trailing-space rows.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $collatedColumns
     */
    public static function duplicateScanSql(string $table, array $columns, array $collatedColumns): string
    {
        $select = implode(', ', array_map(
            fn (string $column): string => in_array($column, $collatedColumns, true)
                ? "`{$column}` COLLATE utf8mb4_0900_ai_ci"
                : "`{$column}`",
            $columns,
        ));
        $notNull = implode(' AND ', array_map(fn (string $column): string => "`{$column}` IS NOT NULL", $columns));

        return "SELECT COUNT(*) n FROM (SELECT 1 FROM `{$table}` WHERE {$notNull} GROUP BY {$select} HAVING COUNT(*) > 1) x";
    }
}
