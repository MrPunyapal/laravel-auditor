<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Snapshots;

use RuntimeException;

/**
 * Compares captured database shape, not migration execution or production data.
 */
final class SchemaComparator
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public function compare(array $before, array $after): array
    {
        if (! is_string($before['driver'] ?? null) || $before['driver'] === '' ||
            ! is_string($after['driver'] ?? null) || $after['driver'] === '' ||
            ! is_array($before['tables'] ?? null) || ! array_is_list($before['tables']) ||
            ! is_array($after['tables'] ?? null) || ! array_is_list($after['tables'])) {
            throw new RuntimeException('Incomplete database schema snapshot.');
        }

        if ($before['driver'] !== $after['driver']) {
            throw new RuntimeException('Cannot compare database schemas from different drivers.');
        }

        $old = $this->indexTables($before['tables']);
        $new = $this->indexTables($after['tables']);
        $added = [];
        $removed = [];
        $modified = [];

        foreach ($new as $name => $table) {
            if (! isset($old[$name])) {
                $added[] = $table;
            } elseif ($old[$name] !== $table) {
                $modified[] = ['table' => $table['name'], 'schema' => $table['schema'] ?? null, 'before' => $old[$name], 'after' => $table];
            }
        }

        foreach ($old as $name => $table) {
            if (! isset($new[$name])) {
                $removed[] = $table;
            }
        }

        return [
            'driver' => $before['driver'],
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
        ];
    }

    /**
     * @param  list<mixed>  $tables
     * @return array<string, array<string, mixed>>
     */
    private function indexTables(array $tables): array
    {
        $indexed = [];

        foreach ($tables as $table) {
            if (! is_array($table) || ! is_string($table['name'] ?? null) || $table['name'] === '' ||
                ! is_array($table['columns'] ?? null) || ! is_array($table['indexes'] ?? null) ||
                ! is_array($table['foreign_keys'] ?? null)) {
                throw new RuntimeException('Invalid table in schema snapshot.');
            }

            if (array_key_exists('schema', $table) && ! is_string($table['schema']) && $table['schema'] !== null) {
                throw new RuntimeException('Invalid table schema in schema snapshot.');
            }

            $this->validateMetadata($table);

            $identity = json_encode([$table['schema'] ?? null, $table['name']], JSON_THROW_ON_ERROR);

            if (isset($indexed[$identity])) {
                throw new RuntimeException('Duplicate table identity in schema snapshot.');
            }

            $indexed[$identity] = $table;
        }

        ksort($indexed, SORT_STRING);

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private function validateMetadata(array $table): void
    {
        foreach (['columns', 'indexes', 'foreign_keys'] as $field) {
            if (! array_is_list($table[$field])) {
                throw new RuntimeException('Invalid metadata list in schema snapshot.');
            }
        }

        $names = [];

        foreach ($table['columns'] as $column) {
            if (! is_array($column) || ! is_string($column['name'] ?? null) || $column['name'] === '' ||
                ! is_string($column['type'] ?? null) || ! is_bool($column['nullable'] ?? null)) {
                throw new RuntimeException('Invalid column in schema snapshot.');
            }

            if (isset($names[$column['name']])) {
                throw new RuntimeException('Duplicate column in schema snapshot.');
            }

            $names[$column['name']] = true;
        }

        $names = [];

        foreach ($table['indexes'] as $index) {
            if (! is_array($index) || ! is_string($index['name'] ?? null) || $index['name'] === '' ||
                ! $this->columnList($index['columns'] ?? null) || ! is_bool($index['unique'] ?? null)) {
                throw new RuntimeException('Invalid index in schema snapshot.');
            }

            if (isset($names[$index['name']])) {
                throw new RuntimeException('Duplicate index in schema snapshot.');
            }

            $names[$index['name']] = true;
        }

        foreach ($table['foreign_keys'] as $key) {
            if (! is_array($key) || ! $this->columnList($key['columns'] ?? null) ||
                ! $this->columnList($key['foreign_columns'] ?? null) ||
                count($key['columns']) !== count($key['foreign_columns']) ||
                ! is_string($key['foreign_table'] ?? null) || $key['foreign_table'] === '') {
                throw new RuntimeException('Invalid foreign key in schema snapshot.');
            }

            foreach (['name', 'foreign_schema', 'on_update', 'on_delete'] as $field) {
                if (! array_key_exists($field, $key) || ($key[$field] !== null && ! is_string($key[$field]))) {
                    throw new RuntimeException('Invalid foreign key metadata in schema snapshot.');
                }
            }
        }
    }

    private function columnList(mixed $columns): bool
    {
        if (! is_array($columns) || ! array_is_list($columns) || $columns === []) {
            return false;
        }

        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                return false;
            }
        }

        return true;
    }
}
