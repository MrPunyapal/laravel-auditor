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
        if (! is_string($before['driver'] ?? null) || ! is_string($after['driver'] ?? null) ||
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
            if (! is_array($table) || ! is_string($table['name'] ?? null) ||
                ! is_array($table['columns'] ?? null) || ! is_array($table['indexes'] ?? null) ||
                ! is_array($table['foreign_keys'] ?? null)) {
                throw new RuntimeException('Invalid table in schema snapshot.');
            }

            if (array_key_exists('schema', $table) && ! is_string($table['schema']) && $table['schema'] !== null) {
                throw new RuntimeException('Invalid table schema in schema snapshot.');
            }

            $identity = json_encode([$table['schema'] ?? null, $table['name']], JSON_THROW_ON_ERROR);

            if (isset($indexed[$identity])) {
                throw new RuntimeException('Duplicate table identity in schema snapshot.');
            }

            $indexed[$identity] = $table;
        }

        ksort($indexed, SORT_STRING);

        return $indexed;
    }
}
