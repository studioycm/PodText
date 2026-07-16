<?php

namespace App\Auth\LegacyRoleBackfill;

use Illuminate\Database\ConnectionInterface;

final readonly class LegacyRoleBackfillSchemaContract
{
    /** @param array<string, string> $tables */
    public function __construct(
        private ConnectionInterface $connection,
        private array $tables,
        private string $usersTable,
    ) {}

    /** @return array<string, mixed> */
    public function inspect(): array
    {
        $driver = $this->connection->getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new BackfillRefusalException('The database driver is unsupported for AUTHZ1-C schema inspection.');
        }

        $builder = $this->connection->getSchemaBuilder();
        $result = ['driver' => $driver, 'tables' => []];

        foreach ([...array_keys($this->tables), 'users'] as $key) {
            $table = $key === 'users' ? $this->usersTable : $this->tables[$key];

            if (! $builder->hasTable($table)) {
                $result['tables'][$key] = ['exists' => false, 'columns' => [], 'indexes' => [], 'foreign_keys' => []];

                continue;
            }

            $columns = array_map(fn (array $column): array => $this->normalizeColumn($column, $driver), $builder->getColumns($table));
            $indexes = array_map(fn (array $index): array => $this->normalizeIndex($index, $driver), $builder->getIndexes($table));
            $foreignKeys = array_map(fn (array $foreign): array => $this->normalizeForeignKey($foreign), $builder->getForeignKeys($table));

            if ($key === 'users') {
                $columns = array_values(array_filter($columns, fn (array $column): bool => in_array($column['name'], ['id', 'role'], true)));
                $indexes = array_values(array_filter($indexes, fn (array $index): bool => $index['columns'] === ['id'] || $index['columns'] === ['role']));
                $foreignKeys = [];
            }

            usort($columns, fn (array $left, array $right): int => $left['name'] <=> $right['name']);
            usort($indexes, fn (array $left, array $right): int => [$left['kind'], $left['columns']] <=> [$right['kind'], $right['columns']]);
            usort($foreignKeys, fn (array $left, array $right): int => [$left['columns'], $left['foreign_table'], $left['foreign_columns']] <=> [$right['columns'], $right['foreign_table'], $right['foreign_columns']]);

            $result['tables'][$key] = [
                'exists' => true,
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
            ];
        }

        ksort($result['tables'], SORT_STRING);

        return $result;
    }

    /** @return array<string, mixed> */
    public function expected(string $driver): array
    {
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new BackfillRefusalException('The database driver is unsupported for AUTHZ1-C schema expectations.');
        }

        $integer = fn (string $name, bool $nullable = false, mixed $default = null, bool $autoIncrement = false): array => [
            'name' => $name,
            'type' => 'integer',
            'length' => null,
            'unsigned' => $driver === 'mysql',
            'nullable' => $nullable,
            'default' => $default,
            'auto_increment' => $autoIncrement,
        ];
        $string = fn (string $name, ?int $length = 255, bool $nullable = false, mixed $default = null): array => [
            'name' => $name,
            'type' => 'string',
            'length' => $driver === 'mysql' ? $length : null,
            'unsigned' => false,
            'nullable' => $nullable,
            'default' => $default,
            'auto_increment' => false,
        ];
        $timestamp = fn (string $name): array => [
            'name' => $name,
            'type' => 'datetime',
            'length' => null,
            'unsigned' => false,
            'nullable' => true,
            'default' => null,
            'auto_increment' => false,
        ];
        $index = fn (string $kind, array $columns, ?string $type = null): array => compact('kind', 'columns', 'type');
        $foreign = fn (array $columns, string $table, array $foreignColumns): array => [
            'columns' => $columns,
            'foreign_table' => $table,
            'foreign_columns' => $foreignColumns,
            'on_update' => $driver === 'mysql' ? 'restrict' : 'no action',
            'on_delete' => 'cascade',
        ];
        $indexes = fn (array $values): array => tap($values, fn (array &$items) => usort($items, fn (array $left, array $right): int => [$left['kind'], $left['columns']] <=> [$right['kind'], $right['columns']]));
        $columns = fn (array $values): array => tap($values, fn (array &$items) => usort($items, fn (array $left, array $right): int => $left['name'] <=> $right['name']));

        $named = fn (): array => [
            'exists' => true,
            'columns' => $columns([$integer('id', autoIncrement: true), $string('name'), $string('guard_name'), $timestamp('created_at'), $timestamp('updated_at')]),
            'indexes' => $indexes([$index('primary', ['id'], $driver === 'mysql' ? 'btree' : null), $index('unique', ['name', 'guard_name'], $driver === 'mysql' ? 'btree' : null)]),
            'foreign_keys' => [],
        ];

        $tables = [
            'permissions' => $named(),
            'roles' => $named(),
            'model_has_permissions' => [
                'exists' => true,
                'columns' => $columns([$integer('permission_id'), $integer('model_id'), $string('model_type')]),
                'indexes' => $indexes([
                    $index('index', ['model_id', 'model_type'], $driver === 'mysql' ? 'btree' : null),
                    $index('primary', ['permission_id', 'model_id', 'model_type'], $driver === 'mysql' ? 'btree' : null),
                ]),
                'foreign_keys' => [$foreign(['permission_id'], 'permissions', ['id'])],
            ],
            'model_has_roles' => [
                'exists' => true,
                'columns' => $columns([$integer('role_id'), $integer('model_id'), $string('model_type')]),
                'indexes' => $indexes([
                    $index('index', ['model_id', 'model_type'], $driver === 'mysql' ? 'btree' : null),
                    $index('primary', ['role_id', 'model_id', 'model_type'], $driver === 'mysql' ? 'btree' : null),
                ]),
                'foreign_keys' => [$foreign(['role_id'], 'roles', ['id'])],
            ],
            'role_has_permissions' => [
                'exists' => true,
                'columns' => $columns([$integer('permission_id'), $integer('role_id')]),
                'indexes' => $indexes(array_values(array_filter([
                    $index('primary', ['permission_id', 'role_id'], $driver === 'mysql' ? 'btree' : null),
                    $driver === 'mysql' ? $index('index', ['role_id'], 'btree') : null,
                ]))),
                'foreign_keys' => [
                    $foreign(['permission_id'], 'permissions', ['id']),
                    $foreign(['role_id'], 'roles', ['id']),
                ],
            ],
            'users' => [
                'exists' => true,
                'columns' => $columns([
                    $integer('id', autoIncrement: true),
                    $string('role', 32, default: 'user'),
                ]),
                'indexes' => $indexes([
                    $index('index', ['role'], $driver === 'mysql' ? 'btree' : null),
                    $index('primary', ['id'], $driver === 'mysql' ? 'btree' : null),
                ]),
                'foreign_keys' => [],
            ],
        ];
        ksort($tables, SORT_STRING);

        return ['driver' => $driver, 'tables' => $tables];
    }

    /** @param array<string, mixed> $actual @return list<AnalysisIssue> */
    public function issues(array $actual): array
    {
        $driver = $actual['driver'] ?? null;

        if (! is_string($driver) || ! in_array($driver, ['sqlite', 'mysql'], true)) {
            return [new AnalysisIssue(AnalysisIssue::SCHEMA_COLUMN_PROPERTY_DRIFT)];
        }

        $expected = $this->expected($driver);
        $issues = [];

        foreach ($expected['tables'] as $key => $expectedTable) {
            $actualTable = $actual['tables'][$key] ?? null;

            if (! is_array($actualTable) || ($actualTable['exists'] ?? null) !== true) {
                $issues[] = new AnalysisIssue(AnalysisIssue::SCHEMA_MISSING_TABLE);

                continue;
            }

            $actualColumns = $actualTable['columns'] ?? [];
            $expectedColumns = $expectedTable['columns'];

            if (array_column($actualColumns, 'name') !== array_column($expectedColumns, 'name')) {
                $issues[] = new AnalysisIssue(in_array('team_id', array_column($actualColumns, 'name'), true) ? AnalysisIssue::SCHEMA_TEAM_COLUMN_PRESENT : AnalysisIssue::SCHEMA_COLUMN_DRIFT);
            } elseif (CanonicalJson::encode($actualColumns) !== CanonicalJson::encode($expectedColumns)) {
                $issues[] = new AnalysisIssue(AnalysisIssue::SCHEMA_COLUMN_PROPERTY_DRIFT);
            }

            $this->appendIndexIssues($actualTable['indexes'] ?? [], $expectedTable['indexes'], $issues);

            if (CanonicalJson::encode($actualTable['foreign_keys'] ?? []) !== CanonicalJson::encode($expectedTable['foreign_keys'])) {
                $issues[] = new AnalysisIssue(AnalysisIssue::SCHEMA_FOREIGN_KEY_DRIFT);
            }
        }

        return $issues;
    }

    /** @param list<array<string, mixed>> $actual @param list<array<string, mixed>> $expected @param list<AnalysisIssue> $issues */
    private function appendIndexIssues(array $actual, array $expected, array &$issues): void
    {
        foreach (['primary' => AnalysisIssue::SCHEMA_PRIMARY_KEY_DRIFT, 'unique' => AnalysisIssue::SCHEMA_UNIQUE_INDEX_DRIFT, 'index' => AnalysisIssue::SCHEMA_SECONDARY_INDEX_DRIFT] as $kind => $code) {
            $actualKind = array_values(array_filter($actual, fn (array $index): bool => ($index['kind'] ?? null) === $kind));
            $expectedKind = array_values(array_filter($expected, fn (array $index): bool => ($index['kind'] ?? null) === $kind));

            if (CanonicalJson::encode($actualKind) !== CanonicalJson::encode($expectedKind)) {
                $issues[] = new AnalysisIssue($code);
            }
        }
    }

    /** @param array<string, mixed> $column @return array<string, mixed> */
    private function normalizeColumn(array $column, string $driver): array
    {
        $rawType = strtolower((string) ($column['type'] ?? $column['type_name'] ?? ''));
        $typeName = strtolower((string) ($column['type_name'] ?? strtok($rawType, '(')));
        $type = match (true) {
            str_contains($typeName, 'int') => 'integer',
            in_array($typeName, ['varchar', 'char', 'string'], true) => 'string',
            in_array($typeName, ['timestamp', 'datetime'], true) => 'datetime',
            default => $typeName,
        };
        preg_match('/\((\d+)\)/', $rawType, $matches);
        $length = isset($matches[1]) ? (int) $matches[1] : null;
        $default = $column['default'] ?? null;

        if (is_string($default)) {
            $default = trim($default, "'\"");
        }

        return [
            'name' => (string) ($column['name'] ?? ''),
            'type' => $type,
            'length' => $driver === 'sqlite' ? null : $length,
            'unsigned' => $driver === 'mysql' && str_contains($rawType, 'unsigned'),
            'nullable' => ($column['nullable'] ?? null) === true,
            'default' => $default,
            'auto_increment' => ($column['auto_increment'] ?? null) === true,
        ];
    }

    /** @param array<string, mixed> $index @return array<string, mixed> */
    private function normalizeIndex(array $index, string $driver): array
    {
        return [
            'kind' => ($index['primary'] ?? false) ? 'primary' : (($index['unique'] ?? false) ? 'unique' : 'index'),
            'columns' => array_values($index['columns'] ?? []),
            'type' => $driver === 'mysql' ? strtolower((string) ($index['type'] ?? '')) : null,
        ];
    }

    /** @param array<string, mixed> $foreign @return array<string, mixed> */
    private function normalizeForeignKey(array $foreign): array
    {
        $normalizeAction = static fn (mixed $action): string => str_replace('_', ' ', strtolower((string) ($action ?: 'no action')));

        return [
            'columns' => array_values($foreign['columns'] ?? []),
            'foreign_table' => (string) ($foreign['foreign_table'] ?? ''),
            'foreign_columns' => array_values($foreign['foreign_columns'] ?? []),
            'on_update' => $normalizeAction($foreign['on_update'] ?? null),
            'on_delete' => $normalizeAction($foreign['on_delete'] ?? null),
        ];
    }
}
