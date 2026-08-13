---
name: laravel-audit-database
description: >
  Audit a Laravel application's database layer: schema, indexes, relationships,
  migrations, and query efficiency. Use when auditing the database layer.
metadata:
  agent: any
---

# Laravel Database Audit

Audit the database layer of the Laravel application. **Where possible, use structured schema information** (via the `database_schema` tool) rather than relying only on migrations.

## What to look for

- Missing or suspicious indexes when evidence supports them: frequently filtered/joined columns without indexes.
- Schema inconsistencies: columns that contradict domain invariants or usage.
- Inefficient relationships: relationship definitions that force expensive queries.
- Suspicious migrations: destructive operations, deadlocks, or migrations that drop data without a path back.
- Duplicate data modeling: the same fact stored in multiple tables/columns.
- Bad foreign key choices: missing FKs, wrong ON DELETE behavior, or FKs to the wrong tables.
- Nullable/non-nullable mismatches where they create risk.
- Query inefficiencies: full-table scans on large tables, missing index coverage for common filters.
- Relationship definitions inconsistent with schema or usage: `foreignId` that does not match the relationship, missing pivot table indexes, etc.

## Evidence requirements

Every database finding needs at least:

- The specific table/column (or migration file + line).
- The query pattern or relationship that is affected.
- Schema facts from the `database_schema` tool or migrations.

## False positives

- Small tables do not need indexes on every column.
- An index that already exists (or is covered by a composite index) is not missing.
- Do not flag nullable columns when the domain allows null.

## Severity guidance

- `high`: schema/relationship mismatch causing real bugs or data integrity risk.
- `medium`: clear inefficiency or inconsistency with real impact.
- `low`: minor schema hygiene.
- `info`: observations.
