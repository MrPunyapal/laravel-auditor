<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Migrations;

use RuntimeException;

/**
 * Provides driver-specific review questions, not measured lock or downtime estimates.
 */
final class MigrationDriverAdvisor
{
    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public function review(array $analysis): array
    {
        $driver = $analysis['driver'] ?? null;
        $indicators = $analysis['indicators'] ?? null;

        if (! is_string($driver) || ! is_array($indicators)) {
            throw new RuntimeException('Database driver review requires complete structural migration indicators.');
        }

        $source = match ($driver) {
            'mysql' => 'https://dev.mysql.com/doc/refman/8.4/en/innodb-online-ddl-operations.html',
            'mariadb' => 'https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table',
            'pgsql' => 'https://www.postgresql.org/docs/current/sql-altertable.html',
            'sqlite' => 'https://sqlite.org/lang_altertable.html',
            default => null,
        };

        $questions = [];

        foreach ($indicators as $indicator) {
            if (! is_array($indicator) || ! is_string($indicator['code'] ?? null)) {
                throw new RuntimeException('Driver review received invalid structural indicator metadata.');
            }

            $code = $indicator['code'];
            $question = $this->question($driver, $code);

            if ($question === null) {
                continue;
            }

            $questions[] = [
                'indicator_code' => $code,
                'schema' => $indicator['schema'] ?? null,
                'table' => $indicator['table'] ?? null,
                'column' => $indicator['column'] ?? null,
                'check' => $question,
            ];
        }

        return [
            'driver' => $driver,
            'supported_guidance' => $source !== null,
            'assessment' => 'conditional_review_only',
            'database_version_verified' => false,
            'locking_verified' => false,
            'questions' => $questions,
            'reference' => $source,
            'limitations' => [
                'No database version, storage engine, row count, transaction state or traffic was measured.',
                'Specific ALTER behavior depends on database version, operation, statement and existing schema.',
                'No review question or absence of questions establishes deployment safety.',
            ],
        ];
    }

    private function question(string $driver, string $code): ?string
    {
        if ($driver === 'pgsql') {
            return match ($code) {
                'column_type_changed' => 'Review ALTER COLUMN TYPE lock requirements, cast compatibility and whether a table rewrite is needed.',
                'nullability_tightened', 'required_column_added' => 'Check current rows, defaults and whether an ALTER TABLE lock or table scan affects writes.',
                'index_added', 'unique_index_added', 'index_changed' => 'Determine whether CREATE INDEX CONCURRENTLY is appropriate; ordinary builds can block writes, and concurrent builds have restrictions.',
                'foreign_key_added' => 'Consider existing-row validation and whether NOT VALID followed by VALIDATE CONSTRAINT is applicable.',
                'column_removed', 'table_removed' => 'Review dependent objects and ALTER/DROP lock requirements; a dropped column may be metadata-only but still takes a lock.',
                default => null,
            };
        }

        if ($driver === 'mysql') {
            return match ($code) {
                'column_type_changed', 'nullability_tightened' => 'Check whether ALTER requires a table rebuild/copy and how concurrent writes and metadata locks behave on the deployed MySQL version.',
                'required_column_added', 'column_removed' => 'Check INSTANT eligibility, storage-engine limits and metadata lock waits; not all ALTER operations are instant.',
                'index_added', 'unique_index_added', 'index_changed' => 'Check existing duplicates, index build resources, ALGORITHM/LOCK support and metadata lock waits.',
                'foreign_key_added' => 'Validate existing rows and foreign-key requirements before selecting an ALTER algorithm and lock strategy.',
                'table_removed' => 'Confirm retained data and dependencies; DDL metadata locks may wait on existing transactions.',
                default => null,
            };
        }

        if ($driver === 'mariadb') {
            return match ($code) {
                'column_type_changed', 'nullability_tightened', 'required_column_added', 'column_removed' => 'Verify this MariaDB version and engine support the requested ALGORITHM/LOCK, including whether copying or rebuilding is required.',
                'index_added', 'unique_index_added', 'index_changed', 'foreign_key_added' => 'Check constraint validation, online DDL support and metadata lock waits for the deployed version.',
                'table_removed' => 'Verify dependent objects, existing data and metadata locking before deployment.',
                default => null,
            };
        }

        if ($driver === 'sqlite') {
            return match ($code) {
                'column_removed' => 'SQLite DROP COLUMN rewrites table contents and may be rejected when schema objects depend on the column.',
                'column_type_changed', 'nullability_tightened' => 'SQLite may require a table rebuild to express this change; check preserved indexes, triggers and foreign keys.',
                'required_column_added' => 'Check SQLite ADD COLUMN limitations, defaults and existing-row compatibility.',
                'index_added', 'unique_index_added', 'foreign_key_added' => 'Verify existing data and SQLite schema-alteration limitations; constraints may require table reconstruction.',
                'table_removed' => 'Confirm dependent schema objects and preserved data before dropping the table.',
                default => null,
            };
        }

        return null;
    }
}
