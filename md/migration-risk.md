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

**No indicators does not mean safe.** This feature cannot determine whether a migration file ran, whether it is reversible, whether production has compatible data, whether traffic will be blocked, or how long DDL will take. It does not pair renamed objects: because a new table alone produces no risk indicator, a table rename appears as a removal indicator for the old table only. Check the snapshot comparison and migration source to identify the corresponding addition. Before/after snapshots do not capture column defaults or data.

## Database-specific cautions

- **MySQL / MariaDB:** DDL performance depends on server version, storage engine and `ALGORITHM`/`LOCK` support. A metadata-only change may still encounter metadata-lock waits.
- **PostgreSQL:** Many `ALTER TABLE` operations require strong locks. Index creation without `CONCURRENTLY` may block writes; concurrent index creation has its own restrictions.
- **SQLite:** Some schema changes rebuild tables. The snapshot collector rejects incomplete SQLite schema metadata, including advanced expression/partial indexes and generated columns.

Treat indicators as **review prompts**, not verified vulnerability findings and not automatic CI deployment gates. Review the actual migration source, plan/backfill and deployment sequence, and test on a realistic disposable database.

## References

- [Laravel migrations](https://laravel.com/docs/13.x/migrations)
- [MySQL 8.4 online DDL](https://dev.mysql.com/doc/refman/8.4/en/innodb-online-ddl-operations.html)
- [MariaDB ALTER TABLE](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table)
- [PostgreSQL ALTER TABLE](https://www.postgresql.org/docs/current/sql-altertable.html)
- [PostgreSQL CREATE INDEX](https://www.postgresql.org/docs/current/sql-createindex.html)
- [SQLite ALTER TABLE](https://sqlite.org/lang_altertable.html)

## Optional migration source and database-driver review

The structural comparison cannot tell which migration ran. To inspect recognized PHP calls in **explicitly selected** migration files, add one or more `--migration` options. Only files from Laravel's registered migration paths are accepted.

~~~bash
php artisan auditor:migration-risk before.json after.json \
  --migration=database/migrations/2026_11_17_000000_change_users.php \
  --driver-guidance \
  --output=migration-review.json
~~~

The scanner tokenizes migration PHP in `up()` without `require`, `include`, or executing the code. It recognizes common `Schema::drop`, `Schema::rename`, `Schema::table` blueprint changes, and direct `DB::statement` / `DB::unprepared` calls. It flags unresolved callbacks for manual review. It does **not** interpret conditions, aliases, custom helpers, macros, computed targets, arbitrary SQL, `down()`, or the full application call graph. It reports source occurrences, not executed operations.

The option is restricted to registered migration PHP files (at most 50 files, each at most 1 MiB). An invalid/unreadable migration or missing `up()` fails the command rather than returning a misleading empty report. Source code and SQL strings are not copied into the report.

`--driver-guidance` adds conditional review questions for MySQL, MariaDB, PostgreSQL and SQLite using the driver captured in the snapshots. It makes **no** claims about actual server version, lock duration, concurrent traffic, table sizes, rebuild cost, or deployment safety. Unsupported database drivers receive no inferred driver-specific advice.

All new flags are opt-in. Without them, the existing snapshot-risk JSON output remains unchanged.

### Reviewing actual deployment risk

A complete production review still needs migration source, data checks, schema constraints, transaction and locking policy, deployment/rollback plan, and realistic workload tests. In particular:

- **MySQL:** `INSTANT`, `INPLACE` and `COPY` eligibility varies by operation and storage engine; metadata locks can wait for active transactions.
- **MariaDB:** `ALGORITHM` and `LOCK` behavior is version-dependent; support for online table copies varies.
- **PostgreSQL:** many `ALTER TABLE` subcommands require strong locks; ordinary index builds may block writes, while `CONCURRENTLY` has additional restrictions. Constraint validation can sometimes be separated.
- **SQLite:** dropping a column rewrites data and may fail when triggers, indexes, views or constraints reference the column.

No indicators means **only** that this limited analysis found no recognized indicator. It is not a declaration that migrations or a deployment are safe.
