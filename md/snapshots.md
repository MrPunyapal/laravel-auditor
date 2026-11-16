---
title: Context snapshots
description: Capture and compare reproducible Laravel route, authorization and optional database schema state.
order: 11
slug: snapshots
---

# Context snapshots

Capture before and after application metadata:

~~~bash
php artisan auditor:snapshot --output=before.json
# Make code changes, then:
php artisan auditor:snapshot --output=after.json
php artisan auditor:compare before.json after.json
~~~

Snapshots include route metadata, registered gates, policy mappings, policy filenames, and middleware aliases, without environment variables or application source.

To include **live database schema shape**, explicitly enable the read-only schema collector on both sides:

~~~bash
php artisan auditor:snapshot --schema --output=before.json
# Apply migrations to a throwaway test database after backing it up
php artisan auditor:snapshot --schema --output=after.json
php artisan auditor:compare before.json after.json
~~~

This option never runs migrations. It stores table, column, index and foreign-key shapes (including PostgreSQL schema names to distinguish same-named tables), omitting database names, data and default values. If the schema is unavailable, snapshot creation fails. Comparing one snapshot with a schema and one without fails rather than indicating a clean result.

SQLite expression indexes, partial indexes, and generated or hidden columns cannot be represented completely by this snapshot format. Collection fails explicitly when these are present instead of producing a potentially misleading comparison. Their SQL expressions are not stored in snapshots.

MySQL and MariaDB foreign keys within the current database omit the database qualifier. Cross-database foreign keys fail snapshot creation because preserving their target identity would expose database names.

The comparison is **structural evidence only**. It cannot establish production deployment safety, prove that a migration executed, or prove an authorization bypass. Follow up by inspecting the affected code and deployment sequence.

Route registration order is preserved independently of the sorted route list. A reorder is reported as a structural difference, not a verified vulnerability.

Keep snapshots private if route names or policy classes reveal internal application structure.

Routes include both declared middleware and the resolved route middleware stack, including group expansion and exclusions. Global HTTP middleware and changes inside middleware, gate, or policy implementations are outside this metadata snapshot. Policy mappings include explicitly registered mappings; automatic policy discovery is not enumerated.

Capture both snapshots with the same route-cache state. Cached routes represent the cached application configuration; rebuild the route cache after changing routes. Laravel can generate names for unnamed routes during caching, so cached and uncached snapshots may differ even without a source change.

## Migration risk indicators

Run `php artisan auditor:migration-risk before.json after.json` to review potential schema-change risks when both snapshots include `--schema`. See [Migration risk indicators](/migration-risk) for coverage and limitations. This does not execute or analyze migration PHP and does not determine deployment safety.
