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

        $seeded = 0;
        $skipped = [];

        foreach ($tables as $table) {
            $template = (array) (DB::table($table->t)->first() ?? []);

            if ($template === []) {
                $skipped[] = $table->t;

                continue;
            }

            $columns = DB::select('SELECT COLUMN_NAME c, DATA_TYPE dt, IS_NULLABLE n, COLLATION_NAME coll, COLUMN_KEY k, EXTRA e, CHARACTER_MAXIMUM_LENGTH len
                FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table->t]);

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
                        $payload = self::CollationPayloads[$i % count(self::CollationPayloads)];
                        $row[$column->c] = Str::limit($payload.' '.Str::random(8), min(60, (int) ($column->len ?? 60)), '');
                    }
                }

                DB::table($table->t)->insert($row);
                $seeded++;
            }
        }

        $this->info("Seeded {$seeded} edge rows across ".(count($tables) - count($skipped)).' tables.');

        if ($skipped !== []) {
            // Count canary: empty tables cannot be cloned — say so, never silently.
            $this->warn('Skipped (no template row): '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }
}
