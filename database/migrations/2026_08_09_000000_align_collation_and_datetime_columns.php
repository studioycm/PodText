<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The alignment migration: utf8mb4_0900_ai_ci everywhere, TIMESTAMP → DATETIME
 * everywhere, generated from information_schema — never hand-listed.
 *
 * ORDER IS THE TRICK (spec §4/§9): this must run with the session timezone
 * LEFT AS-IS. Each TIMESTAMP value materializes as exactly the literal the app
 * has always read back, so nothing shifts. Pinning the session to UTC first
 * would shift every literal by -2/-3h — that is why clock work is a later
 * phase, gated on this migration having run.
 *
 * A Laravel migration rather than hand SQL because the test lane rebuilds its
 * schema by replaying migrations: hand SQL would leave the lane on 80
 * TIMESTAMP columns forever (spec §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return; // SQLite suite (until the MySQL lane lands) — nothing to align.
        }

        $database = DB::connection()->getDatabaseName();

        // Schema default first: it only affects future CREATE TABLEs, and putting
        // the most privilege-sensitive statement before the table rebuilds means
        // a denied ALTER fails in seconds, not after the full conversion.
        DB::statement("ALTER DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

        $tables = DB::select("SELECT TABLE_NAME t FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [$database]);

        foreach ($tables as $table) {
            // Two statements on purpose: CONVERT TO cannot be combined with
            // other alter options, and it must precede the MODIFYs so the
            // datetime columns land in an already-converted table.
            DB::statement("ALTER TABLE `{$table->t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

            $clauses = $this->modifyClauses(DB::select("SELECT TABLE_NAME t, COLUMN_NAME c, IS_NULLABLE n, COLUMN_DEFAULT d, EXTRA e, COLUMN_COMMENT cm
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND DATA_TYPE = 'timestamp'
                ORDER BY ORDINAL_POSITION", [$database, $table->t]));

            if ($clauses !== []) {
                DB::statement("ALTER TABLE `{$table->t}` ".implode(', ', $clauses));
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible by design — restore a db:snapshot instead (spec §9).');
    }

    /**
     * A bare `MODIFY col DATETIME` silently drops NOT NULL (measured, spec §9),
     * so nullability is restated from information_schema. A CURRENT_TIMESTAMP
     * default is deliberately NOT restated: the one such column
     * (failed_jobs.failed_at) is app-written on this Laravel version and the
     * default was a dormant DB-generated-time hazard (spec §10.1).
     *
     * Every attribute this generator does not restate must be one it is
     * sanctioned to drop. An unsanctioned default, an ON UPDATE clause, or a
     * column comment throws instead of converting silently.
     *
     * @param  list<object{t: string, c: string, n: string, d: ?string, e: string, cm: string}>  $rows
     * @return list<string>
     */
    public function modifyClauses(array $rows): array
    {
        foreach ($rows as $row) {
            $defaultSanctioned = $row->d === null || $row->d === 'CURRENT_TIMESTAMP';
            $extraSanctioned = $row->e === '' || $row->e === 'DEFAULT_GENERATED';

            if (! $defaultSanctioned || ! $extraSanctioned || $row->cm !== '') {
                throw new RuntimeException(
                    "Refusing to convert `{$row->t}`.`{$row->c}`: default=[{$row->d}] extra=[{$row->e}] comment=[{$row->cm}] — "
                    .'this generator restates only nullability; handle this column deliberately first (alignment spec §10.1).'
                );
            }
        }

        return array_map(
            fn (object $row): string => sprintf(
                'MODIFY `%s` DATETIME %s',
                $row->c,
                $row->n === 'NO' ? 'NOT NULL' : 'NULL',
            ),
            $rows,
        );
    }
};
