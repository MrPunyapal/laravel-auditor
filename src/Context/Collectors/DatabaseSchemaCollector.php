<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LaravelAuditor\Context\ContextCollector;
use stdClass;
use Throwable;

/**
 * Reads the database schema read-only using the configured connection.
 *
 * Schema is inspected through read-only catalog queries. If the database
 * is unavailable the collector returns a safe diagnostic result instead
 * of failing the audit.
 */
final class DatabaseSchemaCollector implements ContextCollector
{
    public function __construct() {}

    public function name(): string
    {
        return 'database_schema';
    }

    public function description(): string
    {
        return 'Read the database schema: tables, columns, types, and indexes (read-only).';
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
            'tables' => $tables,
        ];
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
                ];
            }

            return $tables;
        }

        $schema = $connection->getSchemaBuilder();

        foreach ($schema->getTables() as $row) {
            $table = (string) $row['name'];

            if ($table === '') {
                continue;
            }

            $tables[] = [
                'name' => $table,
                'columns' => $this->columns($connection, $table, $driver),
                'indexes' => $this->indexes($connection, $table, $driver),
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

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
