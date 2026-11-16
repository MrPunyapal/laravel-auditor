<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Migrations;

use LaravelAuditor\Context\Snapshots\SnapshotComparator;
use RuntimeException;

/**
 * Reports potential migration risks from structural snapshot differences.
 *
 * This is not an analysis of migration source, data, locking or deployment safety.
 */
final class MigrationRiskAnalyzer
{
    public function __construct(
        private readonly SnapshotComparator $comparator,
    ) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public function analyze(array $before, array $after): array
    {
        if (! is_array($before['sections']['schema'] ?? null) || ! is_array($after['sections']['schema'] ?? null)) {
            throw new RuntimeException('Migration risk analysis requires schema-enabled snapshots on both sides.');
        }

        // Validates snapshot versions, route metadata and complete schema shapes.
        $comparison = $this->comparator->compare($before, $after);
        $schema = $comparison['changes']['schema'];
        $indicators = [];

        foreach ($schema['removed'] as $table) {
            $this->add($indicators, 'table_removed', 'high', $table, null,
                'Table removed from schema.', 'Check whether its records were intentionally removed or preserved elsewhere.');
        }

        foreach ($schema['modified'] as $change) {
            $table = $change['after'];
            $old = $change['before'];
            $previousColumns = $this->byName($old['columns']);
            $currentColumns = $this->byName($table['columns']);

            foreach ($previousColumns as $name => $column) {
                if (! isset($currentColumns[$name])) {
                    $this->add($indicators, 'column_removed', 'high', $table, $name,
                        'Column removed from schema.', 'Confirm that existing values and application references are no longer required.');

                    continue;
                }

                $current = $currentColumns[$name];

                if ($column['type'] !== $current['type']) {
                    $this->add($indicators, 'column_type_changed', 'review', $table, $name,
                        'Column type changed.', 'Verify conversion, truncation, existing values and driver-specific table rewrite behavior.');
                }

                if ($column['nullable'] === true && $current['nullable'] === false) {
                    $this->add($indicators, 'nullability_tightened', 'review', $table, $name,
                        'Column changed to NOT NULL.', 'Check for existing NULL values and the backfill/deployment sequence.');
                }
            }

            foreach ($currentColumns as $name => $column) {
                if (! isset($previousColumns[$name]) && $column['nullable'] === false) {
                    $this->add($indicators, 'required_column_added', 'review', $table, $name,
                        'New NOT NULL column.', 'Verify defaults or backfill for existing rows; snapshot data does not include defaults.');
                }
            }

            $previousIndexes = $this->byName($old['indexes']);
            $currentIndexes = $this->byName($table['indexes']);

            foreach ($previousIndexes as $name => $index) {
                if (! isset($currentIndexes[$name])) {
                    $this->add($indicators, 'index_removed', 'review', $table, null,
                        'Index removed: '.$name.'.', 'Check query plans, uniqueness requirements and whether this is an index rename.');
                } elseif ($index !== $currentIndexes[$name]) {
                    $this->add($indicators, 'index_changed', 'review', $table, null,
                        'Index definition changed: '.$name.'.', 'Verify index build strategy, uniqueness, query plans and locking.');
                }
            }

            foreach ($currentIndexes as $name => $index) {
                if (isset($previousIndexes[$name])) {
                    continue;
                }

                if ($index['unique'] === true) {
                    $this->add($indicators, 'unique_index_added', 'review', $table, null,
                        'Unique index added: '.$name.'.', 'Check existing duplicate values, build time and concurrent writes.');
                } else {
                    $this->add($indicators, 'index_added', 'review', $table, null,
                        'Index added: '.$name.'.', 'Assess index build duration and concurrent-write behavior for the selected database.');
                }
            }

            $beforeKeys = $this->byContent($old['foreign_keys']);
            $afterKeys = $this->byContent($table['foreign_keys']);

            foreach (array_diff_key($beforeKeys, $afterKeys) as $key) {
                $this->add($indicators, 'foreign_key_removed', 'review', $table, null,
                    'Foreign key constraint removed.', 'Verify that referential integrity is preserved.', $this->foreignKeyEvidence($key));
            }

            foreach (array_diff_key($afterKeys, $beforeKeys) as $key) {
                $this->add($indicators, 'foreign_key_added', 'review', $table, null,
                    'Foreign key constraint added or changed.', 'Check existing orphaned rows, cascading actions and validation locks.', $this->foreignKeyEvidence($key));
            }
        }

        usort($indicators, static fn (array $a, array $b): int => strcmp(
            json_encode([$a['schema'], $a['table'], $a['column'], $a['code'], $a['message'], $a['constraint'] ?? null], JSON_THROW_ON_ERROR),
            json_encode([$b['schema'], $b['table'], $b['column'], $b['code'], $b['message'], $b['constraint'] ?? null], JSON_THROW_ON_ERROR),
        ));

        return [
            'format' => 'laravel-auditor-migration-risk',
            'version' => 1,
            'driver' => $schema['driver'],
            'coverage' => 'snapshot_structure_only',
            'deployment_safety_verified' => false,
            'has_indicators' => $indicators !== [],
            'summary' => [
                'high' => count(array_filter($indicators, static fn (array $item): bool => $item['level'] === 'high')),
                'review' => count(array_filter($indicators, static fn (array $item): bool => $item['level'] === 'review')),
            ],
            'indicators' => $indicators,
            'limitations' => [
                'No migration source or deployment order was analyzed.',
                'Renames may appear as removals and additions.',
                'No row counts, data contents, column defaults, application traffic or lock timings were inspected.',
                'No indicators does not mean a migration or deployment is safe.',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, array<string, mixed>>
     */
    private function byName(array $records): array
    {
        $result = [];

        foreach ($records as $record) {
            $result[$record['name']] = $record;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, array<string, mixed>>
     */
    private function byContent(array $records): array
    {
        $result = [];

        foreach ($records as $record) {
            $result[json_encode($record, JSON_THROW_ON_ERROR)] = $record;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $key
     * @return array<string, mixed>
     */
    private function foreignKeyEvidence(array $key): array
    {
        return [
            'name' => $key['name'],
            'columns' => array_values($key['columns']),
            'foreign_schema' => $key['foreign_schema'],
            'foreign_table' => $key['foreign_table'],
            'foreign_columns' => array_values($key['foreign_columns']),
            'on_update' => $key['on_update'],
            'on_delete' => $key['on_delete'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $indicators
     * @param  array<string, mixed>  $table
     * @param  array<string, mixed>|null  $constraint
     */
    private function add(array &$indicators, string $code, string $level, array $table, ?string $column, string $message, string $verification, ?array $constraint = null): void
    {
        $indicator = [
            'code' => $code,
            'level' => $level,
            'schema' => $table['schema'] ?? null,
            'table' => $table['name'],
            'column' => $column,
            'message' => $message,
            'verify' => $verification,
        ];

        if ($constraint !== null) {
            $indicator['constraint'] = $constraint;
        }

        $indicators[] = $indicator;
    }
}
