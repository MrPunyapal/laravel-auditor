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

This option never runs migrations. It stores table, column, index and foreign-key shapes, omitting database names, data and default values. If the schema is unavailable, snapshot creation fails. Comparing one snapshot with a schema and one without fails rather than indicating a clean result.

The comparison is **structural evidence only**. It cannot establish production deployment safety, prove that a migration executed, or prove an authorization bypass. Follow up by inspecting the affected code and deployment sequence.
