---
title: Migration risk indicators
description: Review structural migration risk indicators using complete before-and-after database schema snapshots.
order: 13
slug: migration-risk
---

# Migration risk indicators

Laravel Auditor can identify **potential** migration risks from two complete database schema snapshots. This is a read-only review of differences already present in the captured schemas, not execution or prediction of migration PHP.

## Capture and compare

~~~bash
# Capture the starting state of a disposable or representative database
php artisan auditor:snapshot --schema --output=before.json

# Apply and test your proposed migration on that disposable database
# (Laravel Auditor never runs migrations itself)

php artisan auditor:snapshot --schema --output=after.json
php artisan auditor:migration-risk before.json after.json
~~~

Export the results to a file with `--output=risks.json`. Both snapshots **must** include the schema section, be complete, and use the same database driver. The command fails when evidence is missing or malformed.

## Structural indicators

The result identifies:

- Removed tables and columns: potential data loss or references that must be migrated elsewhere
- Added NOT NULL columns or tightened nullability: existing-row/backfill questions
- Column type changes: conversion, truncation or table rewrite questions
- Added, removed and modified indexes: query-plan, uniqueness and index-build questions
- Added and removed foreign keys: data-integrity, orphan-row and constraint-validation questions

Each indicator includes a code, review level, schema/table/column location, explanation and recommended verification. The `coverage` field is `snapshot_structure_only` and `deployment_safety_verified` is always `false`.

**No indicators does not mean safe.** This feature cannot determine whether a migration file ran, whether it is reversible, whether production has compatible data, whether traffic will be blocked, or how long DDL will take. Renames can resemble removals and additions. Before/after snapshots do not capture column defaults or data.

## Database-specific cautions

- **MySQL / MariaDB:** DDL performance depends on server version, storage engine and `ALGORITHM`/`LOCK` support. A metadata-only change may still encounter metadata-lock waits.
- **PostgreSQL:** Many `ALTER TABLE` operations require strong locks. Index creation without `CONCURRENTLY` may block writes; concurrent index creation has its own restrictions.
- **SQLite:** Some schema changes rebuild tables. The snapshot collector rejects incomplete SQLite schema metadata, including advanced expression/partial indexes and generated columns.

Treat indicators as **review prompts**, not verified vulnerability findings and not automatic CI deployment gates. Review the actual migration source, plan/backfill and deployment sequence, and test on a realistic disposable database.

## References

- [Laravel migrations](https://laravel.com/docs/13.x/migrations)
- [MySQL 8.4 online DDL](https://dev.mysql.com/doc/refman/8.4/en/innodb-online-ddl-operations.html)
- [PostgreSQL ALTER TABLE](https://www.postgresql.org/docs/current/sql-altertable.html)
- [PostgreSQL CREATE INDEX](https://www.postgresql.org/docs/current/sql-createindex.html)
- [SQLite ALTER TABLE](https://sqlite.org/lang_altertable.html)
