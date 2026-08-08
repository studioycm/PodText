<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * The mechanical before/after oracle for the alignment migration (spec §9):
 * value preservation is proven by hash equality, property change by an exact
 * expected diff — not by eyeballing. The dangerous failure mode is a false
 * PASS, so every I/O and provenance check here is fail-closed: capture
 * refuses to write a snapshot it cannot trust, and compare refuses to trust a
 * snapshot — or a live re-read — it cannot verify.
 */
#[Signature('db:alignment-oracle {mode : capture or compare}')]
#[Description('Capture or compare per-table value hashes and column properties around the alignment migration.')]
class AlignmentOracle extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:alignment-oracle only supports MySQL; got '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $mode = (string) $this->argument('mode');

        if ($mode !== 'capture' && $mode !== 'compare') {
            $this->error("db:alignment-oracle mode must be 'capture' or 'compare'; got '{$mode}'.");

            return self::FAILURE;
        }

        $path = storage_path('app/db-snapshots/oracle-'.DB::connection()->getDatabaseName().'.json');

        return $mode === 'capture' ? $this->handleCapture($path) : $this->handleCompare($path);
    }

    private function handleCapture(string $path): int
    {
        $state = $this->state();

        foreach ($state['row_failures'] as $failure) {
            $this->error($failure);
        }

        if ($state['hashes'] === [] || $state['properties'] === []) {
            $this->error('Refusing to capture: hashes or properties came back empty.');

            return self::FAILURE;
        }

        if ($state['row_failures'] !== []) {
            $this->error('Refusing to capture: one or more rows were unhashable (see above) — the hash would silently omit them.');

            return self::FAILURE;
        }

        $json = json_encode([
            'meta' => $state['meta'],
            'hashes' => $state['hashes'],
            'properties' => $state['properties'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            $this->error('Refusing to capture: json_encode failed on the captured state.');

            return self::FAILURE;
        }

        if (file_put_contents($path, $json) === false) {
            $this->error("Refusing to capture: could not write {$path}.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Oracle captured to %s (%d tables, %d columns).',
            $path,
            $state['meta']['tables'],
            $state['meta']['columns'],
        ));

        return self::SUCCESS;
    }

    private function handleCompare(string $path): int
    {
        if (! is_file($path)) {
            $this->error("No capture at {$path} — run capture first.");

            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error("Could not read {$path}.");

            return self::FAILURE;
        }

        $before = json_decode($contents, true);

        if (! is_array($before)) {
            $this->error("Malformed JSON in {$path}.");

            return self::FAILURE;
        }

        $meta = is_array($before['meta'] ?? null) ? $before['meta'] : null;
        $hashes = is_array($before['hashes'] ?? null) ? $before['hashes'] : null;
        $properties = is_array($before['properties'] ?? null) ? $before['properties'] : null;

        if ($meta === null || $meta === [] || $hashes === null || $hashes === [] || $properties === null || $properties === []) {
            $this->error("Capture at {$path} is missing or has empty meta/hashes/properties — refusing to compare.");

            return self::FAILURE;
        }

        $currentDatabase = (string) DB::connection()->getDatabaseName();
        $capturedDatabase = (string) ($meta['database'] ?? '');

        if ($capturedDatabase !== $currentDatabase) {
            $this->error("Refusing to compare: capture database `{$capturedDatabase}` does not match the current database `{$currentDatabase}`.");

            return self::FAILURE;
        }

        // Re-read live, before paying for the full hash pass: the whole
        // hash-equality proof is only valid if the session timezone that
        // rendered every TIMESTAMP literal into the hashed text has not
        // moved since capture (spec §9 — "order is the trick").
        $live = $this->liveTimeZones();
        $capturedSessionTz = (string) ($meta['session_time_zone'] ?? '');
        $capturedSystemTz = (string) ($meta['system_time_zone'] ?? '');

        if ($capturedSessionTz !== $live['session']) {
            $this->error("Refusing to compare: session_time_zone drifted from `{$capturedSessionTz}` (capture) to `{$live['session']}` (now).");

            return self::FAILURE;
        }

        if ($capturedSystemTz !== $live['system']) {
            $this->error("Refusing to compare: system_time_zone drifted from `{$capturedSystemTz}` (capture) to `{$live['system']}` (now).");

            return self::FAILURE;
        }

        $after = $this->state();

        $failures = array_merge($after['row_failures'], self::compareStates(
            ['hashes' => $hashes, 'properties' => $properties],
            ['hashes' => $after['hashes'], 'properties' => $after['properties']],
        ));

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures === []) {
            $this->info('Oracle PASS: values byte-identical; only the intended property changes occurred.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * The whole decision, and nothing but the decision: every rule is a
     * strict allow-list of the ONE sanctioned transition per attribute. An
     * unconverted `timestamp`/`utf8mb4_unicode_ci` column needs no special
     * no-op case — it simply never matches its transition rule and falls out
     * as a failure below (spec §9/§10.1). Pure and static so it is testable
     * on SQLite with literal fixture arrays; provenance (database identity,
     * timezone drift, file I/O) is handle()'s job, not this one's.
     *
     * @param  array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array{type: string, nullable: string, default: ?string, collation: ?string, extra: string}>}  $before
     * @param  array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array{type: string, nullable: string, default: ?string, collation: ?string, extra: string}>}  $after
     * @return list<string>
     */
    public static function compareStates(array $before, array $after): array
    {
        $failures = [];

        $beforeTables = array_keys($before['hashes']);
        $afterTables = array_keys($after['hashes']);

        foreach (array_diff($beforeTables, $afterTables) as $table) {
            $failures[] = "table vanished: `{$table}`";
        }

        foreach (array_diff($afterTables, $beforeTables) as $table) {
            $failures[] = "new hashed table: `{$table}`";
        }

        foreach ($before['hashes'] as $table => $expected) {
            $actual = $after['hashes'][$table] ?? null;

            if ($actual === null) {
                continue; // already reported as vanished above
            }

            if ($actual['rows'] !== $expected['rows'] || $actual['sha1'] !== $expected['sha1']) {
                $failures[] = "value drift in `{$table}`: rows {$expected['rows']} → {$actual['rows']}, sha1 {$expected['sha1']} → {$actual['sha1']}";
            }
        }

        $beforeColumns = array_keys($before['properties']);
        $afterColumns = array_keys($after['properties']);

        foreach (array_diff($beforeColumns, $afterColumns) as $column) {
            $failures[] = "column vanished: {$column}";
        }

        foreach (array_diff($afterColumns, $beforeColumns) as $column) {
            $failures[] = "new column: {$column}";
        }

        foreach ($before['properties'] as $key => $old) {
            $new = $after['properties'][$key] ?? null;

            if ($new === null) {
                continue; // already reported as vanished above
            }

            // The one sanctioned column in the whole schema (spec §10.1):
            // failed_jobs.failed_at loses its CURRENT_TIMESTAMP default and
            // DEFAULT_GENERATED extra on purpose. Every other column's
            // default/extra must be byte-identical.
            $sanctioned = $key === 'failed_jobs.failed_at';

            // EXACT match, not prefix: timestamp(6) is a different string
            // than timestamp and must NOT take the sanctioned path — the
            // migration's own pre-DDL guard refuses precision-bearing
            // columns, so one reaching here must fail loudly, not silently.
            if ($old['type'] === 'timestamp') {
                if ($new['type'] !== 'datetime') {
                    $failures[] = $new['type'] === 'timestamp'
                        ? "{$key}: timestamp column not converted"
                        : "{$key}: unexpected type transition ".self::formatValue($old['type']).' → '.self::formatValue($new['type']);
                }
            } elseif ($old['type'] !== $new['type']) {
                $failures[] = "{$key}: unexpected type transition ".self::formatValue($old['type']).' → '.self::formatValue($new['type']);
            }

            if ($old['nullable'] !== $new['nullable']) {
                $failures[] = "{$key}: nullable changed {$old['nullable']} → {$new['nullable']}";
            }

            if ($sanctioned) {
                if ($new['default'] !== null) {
                    $failures[] = "{$key}: sanctioned default was not dropped (still ".self::formatValue($new['default']).')';
                }
            } elseif ($old['default'] !== $new['default']) {
                $failures[] = "{$key}: default changed ".self::formatValue($old['default']).' → '.self::formatValue($new['default']);
            }

            if ($sanctioned) {
                if ($new['extra'] !== '') {
                    $failures[] = "{$key}: sanctioned extra was not dropped (still ".self::formatValue($new['extra']).')';
                }
            } elseif ($old['extra'] !== $new['extra']) {
                $failures[] = "{$key}: extra changed ".self::formatValue($old['extra']).' → '.self::formatValue($new['extra']);
            }

            $collationOk = $old['collation'] === $new['collation']
                || ($old['collation'] === 'utf8mb4_unicode_ci' && $new['collation'] === 'utf8mb4_0900_ai_ci');

            if (! $collationOk) {
                $failures[] = "{$key}: collation changed ".self::formatValue($old['collation']).' → '.self::formatValue($new['collation']);
            } elseif ($new['collation'] === 'utf8mb4_unicode_ci') {
                // Catches both a half-run AND a total no-op without a
                // dedicated branch for either: nothing above ever approves a
                // column still sitting on the collation this migration
                // exists to replace.
                $failures[] = "{$key}: column not converted (collation still `utf8mb4_unicode_ci`)";
            }
        }

        return $failures;
    }

    /**
     * @return array{
     *     meta: array{database: string, captured_at: string, session_time_zone: string, system_time_zone: string, tables: int, columns: int},
     *     hashes: array<string, array{rows: int, sha1: string}>,
     *     properties: array<string, array{type: string, nullable: string, default: ?string, collation: ?string, extra: string}>,
     *     row_failures: list<string>,
     * }
     */
    private function state(): array
    {
        $hashes = [];
        $rowFailures = [];

        $tables = DB::select("SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND (DATA_TYPE IN ('timestamp', 'datetime') OR COLLATION_NAME IS NOT NULL)");

        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        try {
            foreach ($tables as $table) {
                $columns = array_map(
                    fn (object $row): string => $row->c,
                    DB::select("SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                          AND (DATA_TYPE IN ('timestamp', 'datetime') OR COLLATION_NAME IS NOT NULL)
                        ORDER BY ORDINAL_POSITION", [$table->t]),
                );

                // Incremental hash over ordered rows: GROUP_CONCAT would silently
                // truncate at group_concat_max_len and report false equality.
                $context = hash_init('sha1');
                $rows = 0;

                // Ordered by the line's own hash, not `ORDER BY 1`: sorting by
                // the row text under the CURRENT collation is exactly what the
                // migration is about to change out from under us — a
                // trailing-space, emoji, or niqqud row can sort differently
                // under unicode_ci vs 0900_ai_ci and silently reorder the
                // incremental hash into a false drift. SHA1(line) sorts on
                // raw bytes, immune to the collation this run replaces.
                $sql = 'SELECT line FROM (SELECT '.self::rowExpression($columns)." line FROM `{$table->t}`) x ORDER BY SHA1(line)";

                foreach (DB::cursor($sql) as $row) {
                    $rows++;

                    if ($row->line === null) {
                        // CONCAT_WS returns NULL, not a truncated string, once the
                        // rendered row exceeds max_allowed_packet — every column is
                        // already COALESCE-wrapped, so this is never an ordinary NULL
                        // value. Feeding it to hash_update would silently hash the
                        // string "" for a row that was never actually seen.
                        $rowFailures[] = "unhashable row in `{$table->t}` (CONCAT_WS returned NULL — value exceeds max_allowed_packet?)";

                        continue;
                    }

                    hash_update($context, $row->line."\n");
                }

                $hashes[$table->t] = ['rows' => $rows, 'sha1' => hash_final($context)];
            }
        } finally {
            // LONGTEXT transcript columns ride this same loop; buffered mode
            // would materialize their entire result set before PHP sees the
            // first row. Restored unconditionally so a mid-loop exception
            // never leaves the connection in unbuffered mode for whatever
            // runs next.
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        $properties = [];

        foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_DEFAULT d, COLLATION_NAME coll, EXTRA e
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY 1, 2') as $row) {
            $properties["{$row->t}.{$row->c}"] = [
                'type' => $row->ty, 'nullable' => $row->n, 'default' => $row->d, 'collation' => $row->coll, 'extra' => $row->e,
            ];
        }

        $timeZones = $this->liveTimeZones();

        return [
            'meta' => [
                'database' => (string) DB::connection()->getDatabaseName(),
                'captured_at' => now()->toIso8601String(),
                'session_time_zone' => $timeZones['session'],
                'system_time_zone' => $timeZones['system'],
                'tables' => count($hashes),
                'columns' => count($properties),
            ],
            'hashes' => $hashes,
            'properties' => $properties,
            'row_failures' => $rowFailures,
        ];
    }

    /**
     * @return array{session: string, system: string}
     */
    private function liveTimeZones(): array
    {
        $row = DB::selectOne('SELECT @@session.time_zone s, @@system_time_zone y');

        return [
            'session' => (string) ($row->s ?? ''),
            'system' => (string) ($row->y ?? ''),
        ];
    }

    private static function formatValue(?string $value): string
    {
        return $value === null ? 'NULL' : "`{$value}`";
    }

    /**
     * @param  list<string>  $columns
     */
    public static function rowExpression(array $columns): string
    {
        $wrapped = implode(', ', array_map(
            fn (string $column): string => "COALESCE(CAST(`{$column}` AS CHAR), '␀')",
            $columns,
        ));

        return "CONCAT_WS('|', {$wrapped})";
    }
}
