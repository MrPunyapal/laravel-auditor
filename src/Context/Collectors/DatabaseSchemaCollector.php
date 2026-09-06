<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;
use stdClass;
use Throwable;

/**
 * Reads the database schema read-only using the configured connection.
 *
 * Schema is inspected through read-only catalog queries. If the database
 * is unavailable the collector returns a safe diagnostic result instead
 * of failing the audit.
 */
final class DatabaseSchemaCollector implements ContextCollector, FilterableCollector
{
    public function __construct() {}

    public function name(): string
    {
        return 'database_schema';
    }

    public function description(): string
    {
        return 'Read the database schema: tables, columns, types, indexes, and foreign keys (read-only). Optional filter: table (substring).';
    }

    public function filters(): array
    {
        return [
            'table' => 'Case-insensitive substring match on the table name, e.g. "user" matches users and user_profiles.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $connection = $this->connection();

        if ($connection === null) {
            return [
                'available' => false,
                'reason' => 'No database connection is configured.',
            ];
        }

        $driver = $this->driver($connection);

        try {
            $tables = $this->tables($connection, $driver);
        } catch (Throwable $e) {
            return [
                'available' => false,
                'reason' => 'Schema could not be read: '.$e->getMessage(),
            ];
        }

        return [
            'available' => true,
            'driver' => $driver,
            'connection' => $connection->getName(),
            'database' => $connection->getDatabaseName(),
            'tables' => $tables,
        ];
    }

    public function collectFiltered(array $arguments): array
    {
        $payload = $this->collect();

        if (($payload['available'] ?? false) !== true || ! isset($arguments['table'])) {
            return $payload;
        }

        $tables = array_values(array_filter(
            (array) $payload['tables'],
            fn (array $table): bool => str_contains(mb_strtolower((string) $table['name']), mb_strtolower($arguments['table'])),
        ));

        $payload['filtered'] = true;
        $payload['total_count'] = count($payload['tables']);
        $payload['tables'] = $tables;

        return $payload;
    }

    private function connection(): ?Connection
    {
        $default = (string) config('database.default', '');

        if ($default === '') {
            return null;
        }

        try {
            return DB::connection($default);
        } catch (Throwable) {
            return null;
        }
    }

    private function driver(Connection $connection): string
    {
        return $connection->getDriverName();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tables(Connection $connection, string $driver): array
    {
        $tables = [];

        if ($driver === 'sqlite') {
            $rows = $connection->table('sqlite_master')
                ->where('type', 'table')
                ->where('name', 'not like', 'sqlite_%')
                ->pluck('name');

            foreach ($rows as $table) {
                $tables[] = [
                    'name' => (string) $table,
                    'columns' => $this->columns($connection, (string) $table, $driver),
                    'indexes' => $this->indexes($connection, (string) $table, $driver),
                    'foreign_keys' => $this->foreignKeys($connection, (string) $table),
                ];
            }

            return $tables;
        }

        $schema = $connection->getSchemaBuilder();
        $listing = $schema->getCurrentSchemaListing();

        if (! self::hasSchemaScope($listing)) {
            return [];
        }

        foreach ($schema->getTables($listing) as $row) {
            $table = (string) $row['name'];

            if ($table === '') {
                continue;
            }

            $qualified = $row['schema_qualified_name'];

            $tables[] = [
                'name' => $table,
                'columns' => $this->columns($connection, $qualified, $driver),
                'indexes' => $this->indexes($connection, $qualified, $driver),
                'foreign_keys' => $this->foreignKeys($connection, $qualified),
            ];
        }

        usort($tables, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $tables;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(Connection $connection, string $table, string $driver): array
    {
        $columns = [];

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA table_info({$this->quoteIdentifier($table)})");
        } else {
            $schema = $connection->getSchemaBuilder();
            $rows = $schema->getColumns($table);
        }

        foreach ($rows as $row) {
            $row = (array) $row;

            $columns[] = [
                'name' => (string) ($row['name'] ?? $row['column_name'] ?? ''),
                'type' => (string) ($row['type'] ?? $row['data_type'] ?? ''),
                'nullable' => $driver === 'sqlite'
                    ? ((int) ($row['notnull'] ?? 1) === 0)
                    : (bool) ($row['nullable'] ?? $row['is_nullable'] ?? false),
                'default' => $row['default'] ?? $row['column_default'] ?? null,
            ];
        }

        return $columns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function indexes(Connection $connection, string $table, string $driver): array
    {
        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list({$this->quoteIdentifier($table)})");

            return array_values(array_map(static function (stdClass $row): array {
                $row = (array) $row;

                return [
                    'name' => (string) ($row['name'] ?? ''),
                    'unique' => (bool) ($row['unique'] ?? false),
                ];
            }, $rows));
        }

        $schema = $connection->getSchemaBuilder();
        $rows = $schema->getIndexes($table);

        return array_map(static function (array $row): array {
            return [
                'name' => (string) $row['name'],
                'columns' => array_map('strval', $row['columns']),
                'unique' => (bool) $row['unique'],
            ];
        }, $rows);
    }

    /**
     * @return list<array{name: string|null, columns: list<string>, foreign_schema: string|null, foreign_table: string, foreign_columns: list<string>, on_update: string|null, on_delete: string|null}>
     */
    private function foreignKeys(Connection $connection, string $table): array
    {
        $keys = [];

        foreach ($connection->getSchemaBuilder()->getForeignKeys($table) as $row) {
            $columns = [];

            foreach ($row['columns'] as $column) {
                $columns[] = (string) $column;
            }

            $foreignColumns = [];

            foreach ($row['foreign_columns'] as $column) {
                $foreignColumns[] = (string) $column;
            }

            $keys[] = [
                'name' => is_string($row['name'] ?? null) ? $row['name'] : null,
                'columns' => $columns,
                'foreign_schema' => is_string($row['foreign_schema'] ?? null) ? $row['foreign_schema'] : null,
                'foreign_table' => (string) $row['foreign_table'],
                'foreign_columns' => $foreignColumns,
                'on_update' => is_string($row['on_update'] ?? null) ? $row['on_update'] : null,
                'on_delete' => is_string($row['on_delete'] ?? null) ? $row['on_delete'] : null,
            ];
        }

        return $keys;
    }

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * Whether getTables() should be called for this schema listing.
     *
     * An empty listing means the connection has no current schema and must
     * not fall through to an unscoped catalog query. On MySQL that query
     * returns every visible database except the system schemas.
     *
     * @param  array<string>|null  $listing
     */
    public static function hasSchemaScope(?array $listing): bool
    {
        return $listing !== [];
    }
}
