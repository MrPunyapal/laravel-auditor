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

List applicable rules first:

```bash
php artisan auditor:rules --domain=database --applicable
```

## What to look for

- Missing or suspicious indexes when evidence supports them: frequently filtered/joined columns without indexes.
- Schema inconsistencies: columns that contradict domain invariants or usage.
- Inefficient relationships: relationship definitions that force expensive queries.
- Suspicious migrations: destructive operations, deadlocks, or migrations that drop data without a path back.
- Duplicate data modeling: the same fact stored in multiple tables/columns.
- Bad foreign key choices: missing FKs, wrong ON DELETE behavior, or FKs to the wrong tables. Use `database_schema` `foreign_keys` (not only migrations) as the source of truth.
- Check `database_schema.snapshot_complete` before treating SQLite schema details as complete. If it is `false`, generated/hidden columns or partial/expression indexes need direct verification.
- Nullable/non-nullable mismatches where they create risk.
- Query inefficiencies: full-table scans on large tables, missing index coverage for common filters.
- Relationship definitions inconsistent with schema or usage: `foreignId` that does not match the relationship, missing pivot table indexes, etc.


## Structural migration risk review

After capturing complete before-and-after snapshots with `php artisan auditor:snapshot --schema`, run:

~~~bash
php artisan auditor:migration-risk before.json after.json
~~~

Treat output as structural risk indicators only. Validate any table/column removals, nullability or type changes, new unique constraints, index builds and foreign key changes against migration code, actual data, and deployment order. New tables alone produce no risk indicator; a renamed table can therefore appear as a removal of the old table only. Check the snapshot comparison for the added table. A clean indicator list does **not** prove that deployment is safe. Snapshot comparisons cannot tell whether migrations executed or what locks and downtime will occur. Use a disposable database for before/after changes and do not run migrations against production for analysis.

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

## Optional deeper migration review

For complete before/after database schema snapshots, use `auditor:migration-risk before.json after.json`. Add repeatable `--migration=path/to/registered/migration.php` to inspect recognized source patterns in `up()` without executing PHP. Add `--driver-guidance` for conditional database-specific review questions.

Review dynamic SQL, helper methods, conditional execution, backfill, transaction behavior and rollback/deployment order manually. The inspector does not prove which migration ran and never certifies deployment safety. Source coverage is limited to explicitly selected registered migrations.
