<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rehearsal-only: spray the alignment migration's edge matrix across every
 * table that has TIMESTAMP columns, by cloning an existing template row per
 * table and overwriting its date and collated-string values. Clone-based so
 * FKs and NOT NULLs are satisfied without hand-listing 40 table shapes.
 *
 * Per table, chooses an INSERT-clone or UPDATE-in-place path based on whether
 * every unique/primary index is covered by a column the row actually varies.
 * COLUMN_KEY cannot see composite uniqueness — only the first column of a
 * composite unique index shows MUL, indistinguishable from a plain non-unique
 * index — so index membership is read from information_schema.STATISTICS
 * instead, the same source PreflightAlignment already uses. Composite unique
 * indexes over untouched integer FK columns (pivot tables — measured:
 * author_transcription's author_id+transcription_id) fail that coverage
 * check: cloning would copy the pair verbatim into all seven rows and
 * collide. Those tables get up to seven existing rows' timestamps updated in
 * place instead; every other column, including collated ones, stays
 * untouched on that path.
 *
 * Refuses any database not named like a rehearsal copy. Never deployed logic —
 * a tool for spec §9's "rehearsal before reality" gate.
 */
#[Signature('db:seed-rehearsal-edges')]
#[Description('Clone edge-case rows (DST boundaries, epoch edges, hostile Hebrew/emoji strings) into a rehearsal database.')]
class SeedRehearsalEdges extends Command
{
    /** Wall-clock literals, written verbatim through the current session. */
    public const DateEdges = [
        '2026-01-15 10:00:00', '2026-07-15 10:00:00',
        '2026-03-27 01:59:00', '2026-03-27 03:00:00',
        '2026-10-25 01:30:00',
        '1970-01-01 02:00:01', '2038-01-19 05:14:07',
    ];

    /** Hostile strings for collated columns riding the same rows. */
    public const CollationPayloads = ['שָׁלוֹם', 'שלום', 'כׇּל-הזמן', 'ם סופית', 'טעם ', '🎧', '🎤'];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:seed-rehearsal-edges only supports MySQL; got '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $database = DB::connection()->getDatabaseName();

        if (preg_match('/rehearsal|restore_check/', $database) !== 1) {
            $this->error("Refused: `{$database}` is not a rehearsal database (name must match /rehearsal|restore_check/).");

            return self::FAILURE;
        }

        $tables = DB::select('SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = "timestamp"');

        $inserted = 0;
        $updated = 0;
        $insertTables = [];
        $updateTables = [];
        $skippedEmpty = [];
        $skippedNoKey = [];

        foreach ($tables as $table) {
            $template = (array) (DB::table($table->t)->first() ?? []);

            if ($template === []) {
                $skippedEmpty[] = $table->t;

                continue;
            }

            $columns = DB::select('SELECT COLUMN_NAME c, DATA_TYPE dt, IS_NULLABLE n, COLLATION_NAME coll, EXTRA e, CHARACTER_MAXIMUM_LENGTH len
                FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table->t]);

            $uniqueIndexes = self::uniqueIndexColumnSets($table->t);
            $varying = self::varyingColumns($columns, $template);

            if (self::insertSafe($uniqueIndexes, $varying)) {
                foreach (self::DateEdges as $i => $edge) {
                    $row = $template;

                    foreach ($columns as $column) {
                        if ($column->e === 'auto_increment') {
                            unset($row[$column->c]);
                        } elseif ($column->dt === 'timestamp') {
                            // NULL every other clone on nullable date columns, so
                            // NULL-preservation is rehearsed too.
                            $row[$column->c] = ($column->n === 'YES' && $i % 2 === 1) ? null : $edge;
                        } elseif ($column->coll !== null && isset($row[$column->c]) && is_string($row[$column->c])) {
                            // Unique-safe: payload + a nonce suffix, capped to the
                            // column's real capacity (narrow code columns get a
                            // truncated payload; none of them is unique — measured).
                            // mb_substr counts codepoints like MySQL does; Str::limit counts display width (niqqud = 0) and can overflow narrow columns at the boundary.
                            // Truncation may land exactly on the separator space (measured:
                            // 7-codepoint 'שָׁלוֹם' + ' ' cut at a length-8 column) — rtrim it so
                            // the seeder never manufactures a B5 trailing-space finding by
                            // construction. The 'טעם ' payload's own designed space still sits
                            // mid-string in every wide column, so the designed collation
                            // coverage is unchanged.
                            $payload = self::CollationPayloads[$i % count(self::CollationPayloads)];
                            $row[$column->c] = rtrim(mb_substr($payload.' '.Str::random(8), 0, min(60, (int) ($column->len ?? 60))), ' ');
                        }
                    }

                    DB::table($table->t)->insert($row);
                    $inserted++;
                }

                $insertTables[] = $table->t;

                continue;
            }

            // Some unique index's columns are copied verbatim across every
            // clone (measured: composite pivot FK pairs like
            // author_transcription) — cloning would collide on them. Update
            // existing rows' timestamps in place instead.
            if (! isset($uniqueIndexes['PRIMARY'])) {
                $skippedNoKey[] = $table->t;

                continue;
            }

            $primaryKeyColumns = $uniqueIndexes['PRIMARY'];

            $query = DB::table($table->t);

            foreach ($primaryKeyColumns as $pkColumn) {
                $query->orderBy($pkColumn);
            }

            $rows = $query->limit(count(self::DateEdges))->get();

            foreach ($rows as $i => $row) {
                $edge = self::DateEdges[$i];
                $changes = [];

                foreach ($columns as $column) {
                    if ($column->dt === 'timestamp') {
                        $changes[$column->c] = ($column->n === 'YES' && $i % 2 === 1) ? null : $edge;
                    }
                }

                $identity = [];

                foreach ($primaryKeyColumns as $pkColumn) {
                    $identity[$pkColumn] = $row->{$pkColumn};
                }

                DB::table($table->t)->where($identity)->update($changes);
                $updated++;
            }

            $updateTables[] = $table->t;
        }

        $this->info("Inserted {$inserted} clone row(s) across ".count($insertTables).' table(s) via the insert path; updated '.$updated.' row(s) across '.count($updateTables).' table(s) via the update path.');

        if ($insertTables !== []) {
            $this->line('Insert path: '.implode(', ', $insertTables));
        }

        if ($updateTables !== []) {
            $this->line('Update path: '.implode(', ', $updateTables));
        }

        if ($skippedEmpty !== []) {
            // Count canary: empty tables cannot be cloned — say so, never silently.
            $this->warn('Skipped (no template row): '.implode(', ', $skippedEmpty));
        }

        if ($skippedNoKey !== []) {
            // Count canary: updating without a primary key to identify rows
            // isn't worth the risk even on a rehearsal copy — say so, never silently.
            $this->warn('Skipped (needs the update path but has no primary key): '.implode(', ', $skippedNoKey));
        }

        return self::SUCCESS;
    }

    /**
     * True when every unique/primary index has at least one column the row
     * actually varies — vacuously true for a table with no unique indexes at
     * all. False means some index's columns are copied verbatim across every
     * clone/update, which would collide (the composite-pivot case measured on
     * author_transcription).
     *
     * @param  array<string, list<string>>  $uniqueIndexColumnSets  Index name => column names.
     * @param  list<string>  $varyingColumns
     */
    public static function insertSafe(array $uniqueIndexColumnSets, array $varyingColumns): bool
    {
        foreach ($uniqueIndexColumnSets as $columns) {
            if (array_intersect($columns, $varyingColumns) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Unique and primary index column sets for a table, read from
     * information_schema.STATISTICS rather than COLUMN_KEY. COLUMN_KEY only
     * marks the first column of a composite unique index (as MUL,
     * indistinguishable from a plain non-unique index), so it cannot see
     * composite uniqueness at all — the measured cause of the duplicate-entry
     * abort this method exists to prevent. Same source PreflightAlignment
     * already uses.
     *
     * @return array<string, list<string>> Index name (e.g. "PRIMARY") => column names, ordered by SEQ_IN_INDEX.
     */
    public static function uniqueIndexColumnSets(string $table): array
    {
        $rows = DB::select('SELECT INDEX_NAME i, COLUMN_NAME c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
            ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$table]);

        $indexes = [];

        foreach ($rows as $row) {
            $indexes[$row->i][] = $row->c;
        }

        return $indexes;
    }

    /**
     * Columns whose value always differs between a template row's seven
     * clones or updates: every timestamp column (the whole point of edge
     * dates), every collated column the payload branch actually rewrites, and
     * every auto_increment column (a fresh id is assigned per insert — moot
     * on the update path, which never inserts, but harmless to include).
     *
     * @param  array<string, mixed>  $template
     * @return list<string>
     */
    private static function varyingColumns(array $columns, array $template): array
    {
        $varying = [];

        foreach ($columns as $column) {
            if ($column->e === 'auto_increment'
                || $column->dt === 'timestamp'
                || ($column->coll !== null && isset($template[$column->c]) && is_string($template[$column->c]))
            ) {
                $varying[] = $column->c;
            }
        }

        return $varying;
    }
}
