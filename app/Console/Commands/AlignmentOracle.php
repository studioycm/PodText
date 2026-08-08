<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The mechanical before/after oracle for the alignment migration (spec §9):
 * value preservation is proven by hash equality, property change by an exact
 * expected diff — not by eyeballing.
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

        $path = storage_path('app/db-snapshots/oracle-'.DB::connection()->getDatabaseName().'.json');

        if ($this->argument('mode') === 'capture') {
            file_put_contents($path, json_encode($this->state(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Oracle captured to {$path}.");

            return self::SUCCESS;
        }

        $before = json_decode((string) file_get_contents($path), true);

        if (! is_array($before)) {
            $this->error("No capture at {$path} — run capture first.");

            return self::FAILURE;
        }

        return $this->compare($before, $this->state());
    }

    /**
     * @return array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array{type: string, nullable: string, default: ?string, collation: ?string}>}
     */
    private function state(): array
    {
        $hashes = [];

        $tables = DB::select('SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND (DATA_TYPE IN ("timestamp", "datetime") OR COLLATION_NAME IS NOT NULL)');

        foreach ($tables as $table) {
            $columns = array_map(
                fn (object $row): string => $row->c,
                DB::select('SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                      AND (DATA_TYPE IN ("timestamp", "datetime") OR COLLATION_NAME IS NOT NULL)
                    ORDER BY ORDINAL_POSITION', [$table->t]),
            );

            // Incremental hash over ordered rows: GROUP_CONCAT would silently
            // truncate at group_concat_max_len and report false equality.
            $context = hash_init('sha1');
            $rows = 0;

            foreach (DB::cursor('SELECT '.self::rowExpression($columns)." line FROM `{$table->t}` ORDER BY 1") as $row) {
                hash_update($context, $row->line."\n");
                $rows++;
            }

            $hashes[$table->t] = ['rows' => $rows, 'sha1' => hash_final($context)];
        }

        $properties = [];

        foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_DEFAULT d, COLLATION_NAME coll
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY 1, 2') as $row) {
            $properties["{$row->t}.{$row->c}"] = [
                'type' => $row->ty, 'nullable' => $row->n, 'default' => $row->d, 'collation' => $row->coll,
            ];
        }

        return ['hashes' => $hashes, 'properties' => $properties];
    }

    /**
     * @param  array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array<string, ?string>>}  $before
     * @param  array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array<string, ?string>>}  $after
     */
    private function compare(array $before, array $after): int
    {
        $failures = 0;

        foreach ($before['hashes'] as $table => $expected) {
            $actual = $after['hashes'][$table] ?? null;

            if ($actual !== $expected) {
                $this->error("VALUE DRIFT in `{$table}`: ".json_encode($expected).' → '.json_encode($actual));
                $failures++;
            }
        }

        foreach ($before['properties'] as $key => $old) {
            $new = $after['properties'][$key] ?? null;

            if ($new === null) {
                $this->error("Column vanished: {$key}");
                $failures++;

                continue;
            }

            $intended = str_starts_with((string) $old['type'], 'timestamp')
                && str_starts_with((string) $new['type'], 'datetime')
                && $new['nullable'] === $old['nullable']
                && ($new['collation'] === $old['collation']
                    || ($old['collation'] === 'utf8mb4_unicode_ci' && $new['collation'] === 'utf8mb4_0900_ai_ci'));

            $collationOnly = $old['type'] === $new['type']
                && $new['nullable'] === $old['nullable'] && $new['default'] === $old['default']
                && $old['collation'] === 'utf8mb4_unicode_ci' && $new['collation'] === 'utf8mb4_0900_ai_ci';

            if ($old !== $new && ! $intended && ! $collationOnly) {
                $this->error("UNINTENDED PROPERTY CHANGE {$key}: ".json_encode($old).' → '.json_encode($new));
                $failures++;
            }
        }

        if ($failures === 0) {
            $this->info('Oracle PASS: values byte-identical; only the intended property changes occurred.');

            return self::SUCCESS;
        }

        return self::FAILURE;
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
